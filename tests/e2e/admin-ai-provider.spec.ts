import { test, expect } from "./support/test";
import { TEST_ADMIN, login, seedUser } from "./support/config";

/**
 * Admin → AI puts away the controls the chosen provider cannot use.
 *
 * The keep-alive fields and "Warm up now" are about Ollama's memory. For an
 * OpenAI-compatible endpoint they are saved, shown back and sent nowhere, so the
 * page hides them — the moment the provider is chosen, not after a save.
 *
 * Checked in a browser because it is one attribute against the cascade: the
 * blocks are hidden with `hidden`, which loses to any display class on the same
 * element. A wrapper that picked up a `flex` would stay on screen while every
 * server-side test went on passing.
 *
 * NOTHING IS SAVED. The provider is installation-wide, and this spec runs
 * beside others; it changes the select and reads the page.
 */
const ADMIN = TEST_ADMIN;

test.use({ storageState: { cookies: [], origins: [] } });

test.beforeAll(() => {
    seedUser({ email: ADMIN.email, password: ADMIN.password, admin: true });
});

test("the Ollama-only controls follow the provider select", async ({ page }) => {
    await login(page, ADMIN.email, ADMIN.password);
    await page.goto("/admin?section=ai");

    const provider = page.locator("#ai_settings_provider");

    // The card folds itself away once a host is on file; whichever state this
    // installation is in, the fields have to be on screen to be looked at.
    const card = page.locator("details").filter({ has: provider });

    if (null === (await card.getAttribute("open"))) {
        await card.locator("summary").first().click();
    }

    await expect(provider).toBeVisible();

    const chatKeepAlive = page.locator("#ai_settings_chatKeepAlive");
    const searchKeepAlive = page.locator("#ai_settings_embeddingKeepAlive");
    const warmUp = page.locator('[data-controller="admin--ai-warmup"] button');

    await provider.selectOption("ollama");

    await expect(chatKeepAlive).toBeVisible();
    await expect(searchKeepAlive).toBeVisible();
    await expect(warmUp).toBeVisible();

    await provider.selectOption("openai");

    await expect(chatKeepAlive).toBeHidden();
    await expect(searchKeepAlive).toBeHidden();
    await expect(warmUp).toBeHidden();

    // Hidden and still part of the form: a disabled field is left out of the
    // submission, and the server would read that as the value being cleared.
    await expect(chatKeepAlive).toBeEnabled();

    // The fields that mean the same for both stay where they are.
    await expect(page.locator("#ai_settings_baseUrl")).toBeVisible();
    await expect(page.locator("#ai_settings_chatModel")).toBeVisible();
    await expect(page.locator("#ai_settings_embeddingModel")).toBeVisible();

    await provider.selectOption("ollama");

    await expect(chatKeepAlive).toBeVisible();
    await expect(warmUp).toBeVisible();
});
