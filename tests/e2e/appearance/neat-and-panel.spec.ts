import { test, expect } from "../support/test";

test("Neat rows and inverted panel preview, persistence and real mail list", async ({ page }) => {
    test.setTimeout(300_000);
    await page.setViewportSize({ width: 1500, height: 1000 });
    await page.goto("/settings?section=appearance");
    await page.locator('label:has(input[name="listDensity"][value="neat"])').click();
    await expect(page.locator('html')).toHaveClass(/list-neat/);
    const preview = page.locator('[data-preview-row]').first();
    await expect.poll(async () => (await preview.boundingBox())?.height).toBe(30);
    await expect.poll(async () => (await preview.locator('[data-row-avatar-face]').boundingBox())?.height).toBe(22);
    await page.locator('[data-theme-name="nord"]').click();
    if (!(await page.locator('[data-panel-invert-switch]').isChecked())) {
        await page.locator('label:has([data-panel-invert-switch])').click();
    }
    const slider = page.locator('[data-settings--appearance-field="messagePanelContrast"]');
    await slider.evaluate(el => { (el as HTMLInputElement).value = '0'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    const panel = page.locator('[data-message-panel]').first();
    await expect.poll(() => panel.evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgb(128, 128, 128)');
    const saved = page.waitForResponse(r => r.url().endsWith('/settings/appearance') && r.request().method() === 'POST' && r.ok() && r.request().postDataJSON()?.messagePanelContrast === '100' && r.request().postDataJSON()?.invertMessagePanel === '1' && r.request().postDataJSON()?.listDensity === 'neat');
    await slider.evaluate(el => { (el as HTMLInputElement).value = '100'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    await expect.poll(() => panel.evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgb(255, 255, 255)');
    await saved;
    await page.reload();
    await expect(page.locator('html')).toHaveClass(/list-neat/);
    await expect(page.locator('[data-panel-invert-switch]')).toBeChecked();
    await expect(slider).toHaveValue('100');
    await page.goto('/mail/inbox');
    const row = page.locator('[data-controller~="mail--message-row"]').first();
    await expect(row).toBeVisible();
    await expect.poll(async () => (await row.boundingBox())?.height).toBe(30);
    await expect.poll(() => page.locator('[data-message-panel]').first().evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgb(255, 255, 255)');
    await expect(row.locator('[data-row-neat-text]')).toBeVisible();
    await expect(row.locator('[data-neat-separator]')).toHaveText('|');
    await expect(row.locator('[data-neat-snippet]')).toBeVisible();
    await expect(row.locator('[data-row-content]')).toBeHidden();
    await expect(row.locator('[data-row-standard-labels]').first()).toBeHidden();
    await expect(row.locator('[data-row-standard-labels]').last()).toBeHidden();
    await page.setViewportSize({ width: 800, height: 900 });
    await expect.poll(async () => (await row.boundingBox())?.height).toBe(30);
    await page.goto('/settings?section=appearance');
    await page.locator('label:has(input[name="listDensity"][value=""])').click();
    await page.locator('label:has([data-panel-invert-switch])').click();
    await page.locator('[data-theme-name="paper"]').click();
    await page.waitForTimeout(700);
});
