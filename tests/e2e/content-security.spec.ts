import { test, expect } from "./support/test";

test("HTTPS-only preference defaults on, persists, and rejects missing CSRF", async ({ page }) => {
    test.setTimeout(300_000);
    await page.goto('/settings?section=security');
    const on = page.locator('input[name="httpsOnly"][value="1"]');
    await expect(on).toBeChecked();
    const navigation = page.locator('a[href*="section=security"]').first();
    const images = page.locator('a[href*="section=images"]').first();
    expect((await navigation.boundingBox())!.y).toBeLessThan((await images.boundingBox())!.y);
    const response = await page.request.post('/settings/security/content', { form: { httpsOnly: '0' }, maxRedirects: 0 });
    expect(response.status()).toBe(403);
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('form[action$="/settings/security/content"] label:has(input[value="0"])').click()]);
    await expect(page.locator('input[name="httpsOnly"][value="0"]')).toBeChecked();
    await page.reload();
    await expect(page.locator('input[name="httpsOnly"][value="0"]')).toBeChecked();
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.locator('form[action$="/settings/security/content"] label:has(input[value="1"])').click()]);
    await expect(on).toBeChecked();
    await page.reload();
    await expect(on).toBeChecked();
});
