import { test, expect } from "../support/test";

test("Message panel opacity previews and persists without fading text", async ({ page }) => {
    test.setTimeout(300_000);
    await page.setViewportSize({ width: 1500, height: 1000 });
    await page.goto('/settings?section=appearance');
    await page.locator('[data-theme-name="nord"]').click();
    if (!(await page.locator('[data-panel-invert-switch]').isChecked())) {
        await page.locator('label:has([data-panel-invert-switch])').click();
    }
    const contrast = page.locator('[data-settings--appearance-field="messagePanelContrast"]');
    const opacity = page.locator('[data-settings--appearance-field="messagePanelOpacity"]');
    expect((await opacity.boundingBox())!.y).toBeGreaterThan((await contrast.boundingBox())!.y);
    const saved = page.waitForResponse(r => r.url().endsWith('/settings/appearance') && r.request().method() === 'POST' && r.ok() && r.request().postDataJSON()?.messagePanelOpacity === '35');
    await opacity.evaluate(el => { (el as HTMLInputElement).value = '35'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    const preview = page.locator('[data-message-panel]').first();
    await expect.poll(() => preview.evaluate(el => getComputedStyle(el).backgroundColor)).toMatch(/0\.35\)/);
    await expect.poll(() => preview.evaluate(el => getComputedStyle(el).opacity)).toBe('1');
    await expect(page.locator('[data-panel-opacity-output]')).toHaveText('35%');
    await saved;
    await page.reload();
    await expect(opacity).toHaveValue('35');
    await page.goto('/mail/inbox');
    const panel = page.locator('[data-message-panel]').first();
    await expect.poll(() => panel.evaluate(el => getComputedStyle(el).backgroundColor)).toMatch(/0\.35\)/);
    await expect.poll(() => panel.evaluate(el => getComputedStyle(el).opacity)).toBe('1');
    await page.goto('/settings?section=appearance');
    const restored = page.waitForResponse(r => r.url().endsWith('/settings/appearance') && r.request().method() === 'POST' && r.ok() && r.request().postDataJSON()?.messagePanelOpacity === '100');
    await opacity.evaluate(el => { (el as HTMLInputElement).value = '100'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    await restored;
});
