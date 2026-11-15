import { seed } from "./support/config";
import { expect, test } from "./support/test";

test("moves a mailbox to Priority, saves its interval and exposes both manual scopes", async ({ page }) => {
    seed("seed-mail");
    await page.goto("/settings?section=sync-frequency");

    const priority = page.locator('[data-sync-group="priority"]');
    const defaults = page.locator('[data-sync-group="default"]');
    const chooser = page.getByRole("combobox", { name: "Retrieval group for E2E Mailbox" });
    const card = page.locator("li").filter({ has: chooser });
    const originalGroup = await chooser.inputValue();
    const originalInterval = await priority.getByRole("spinbutton").inputValue();
    const interval = priority.getByRole("spinbutton");
    const save = page.getByRole("button", { name: "Save", exact: true });

    await chooser.selectOption("default");
    await expect(defaults.locator(card)).toBeVisible();
    await defaults.locator(card).locator('[draggable="true"]').dragTo(priority);
    await expect(priority.locator(chooser)).toHaveValue("priority");
    await interval.fill("7");
    const saved = page.waitForResponse(response => response.url().endsWith("/settings/sync-frequency") && response.status() === 303);
    await save.click();
    await saved;
    await page.reload();
    await expect(priority.locator(chooser)).toHaveValue("priority");
    await expect(interval).toHaveValue("7");

    await page.route(/\/mail\/sync(?:\/priority)?$/, route => route.fulfill({ json: { dispatched: 0 } }));
    for (const [label, path] of [
        ["Retrieve priority mailboxes", "/mail/sync/priority"],
        ["Retrieve all mailboxes", "/mail/sync"],
    ]) {
        const request = page.waitForRequest(req => new URL(req.url()).pathname === path && req.method() === "POST");
        await page.getByRole("button", { name: label, exact: true }).click();
        await request;
    }

    await chooser.selectOption(originalGroup);
    await interval.fill(originalInterval);
    await save.click();
});
