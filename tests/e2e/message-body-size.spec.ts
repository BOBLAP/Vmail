import { test, expect } from './support/test';
import { mailRow, seed } from './support/config';

test('message height is independent of density and survives reload', async ({ page }) => {
    seed('seed-rendering');
    for (const [size, height] of [['compact', 80], ['cosy', 200], ['comfortable', 1080], ['adaptive', null]] as const) {
        await page.goto('/settings?section=appearance');
        if (!await page.locator(`input[name="messageBodySize"][value="${size}"]`).isChecked()) {
            const response = page.waitForResponse(r => r.url().endsWith('/settings/appearance') && r.request().method() === 'POST');
            await page.locator(`label:has(input[name="messageBodySize"][value="${size}"])`).click();
            expect((await response).ok()).toBe(true);
        }
        await page.goto('/mail/inbox');
        await mailRow(page, 'E2E Long Body').click();
        const frame = page.locator('[data-mail--message-frame-target="frame"]').first();
        const assertSize = async () => {
            if (height === null) {
                await expect.poll(async () => (await frame.boundingBox())?.height ?? 0, { timeout: 20000 }).toBeGreaterThan(1080);
            } else {
                await expect(frame).toHaveCSS('height', `${height}px`);
            }
        };
        await assertSize();
        await page.reload();
        await assertSize();
        if (height === null) {
            for (const pixels of [80, 200, 1080]) {
                await page.evaluate(px => document.documentElement.style.setProperty('--message-body-height', `${px}px`), pixels);
                await expect(frame).toHaveCSS('height', `${pixels}px`);
            }
            await page.evaluate(() => document.documentElement.style.setProperty('--message-body-height', 'auto'));
            await assertSize();
        }
    }
});
