import { test, expect, type Page } from './support/test';
import { APP_TIMEZONE, seed } from './support/config';

test.beforeAll(() => { seed('seed-mail', 'clear-drafts'); });

function wallClock(date: Date): string {
    return new Intl.DateTimeFormat('sv-SE', {
        timeZone: APP_TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).format(date).replace(' ', 'T');
}

async function settings(page: Page) {
    await page.goto('/settings?section=auto-responder');
    const stop = page.getByRole('button', { name: 'Turn off automatic replies now', exact: true });
    if (await stop.count()) await stop.click();
}

async function configure(page: Page, start: Date, end: Date, html = false) {
    await page.locator('#auto_responder_enabled').check({ force: true });
    await page.locator('#auto_responder_startsAt').fill(wallClock(start));
    await page.locator('#auto_responder_endsAt').fill(wallClock(end));
    await page.locator('#auto_responder_subject').fill('Away for a few days');
    await page.locator('#auto_responder_format').selectOption(html ? 'html' : 'text', { force: true });
    if (html) {
        const editor = page.locator('[data-settings--auto-responder-target="editor"]');
        await editor.fill('Away with formatted text');
        await editor.press('Control+A');
        await page.getByRole('button', { name: 'Bold', exact: true }).click();
    } else {
        await page.locator('#auto_responder_message').fill('I am away. I will reply when I return.');
    }
}

test('plain replies activate red tiles, show the deadline, and can be stopped', async ({ page }) => {
    await settings(page);
    const normal = await page.locator('.main-pane').first().evaluate(el => getComputedStyle(el).backgroundColor);
    await configure(page, new Date(Date.now() - 300_000), new Date(Date.now() + 3_600_000));
    await page.getByRole('button', { name: 'Save automatic replies', exact: true }).click();
    await expect(page.locator('body')).toHaveClass(/auto-responder-active/);
    await expect(page.locator('#sidebar [data-auto-responder-banner]')).toContainText(/Automatic replies active until \d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/);
    const active = await page.locator('.main-pane').first().evaluate(el => getComputedStyle(el).backgroundColor);
    expect(active).not.toBe(normal);
    await page.screenshot({ path: 'test-results/auto-responder-active-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('header [data-auto-responder-banner]')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    await page.screenshot({ path: 'test-results/auto-responder-active-mobile.png', fullPage: true });
    const denied = await page.request.post('/settings/auto-responder/stop', { form: { _token: 'wrong' } });
    expect(denied.status()).toBe(403);
    await page.getByRole('button', { name: 'Turn off automatic replies now', exact: true }).click();
    await expect(page.locator('body')).not.toHaveClass(/auto-responder-active/);
    const state = await page.request.get('/settings/auto-responder/status');
    expect((await state.json()).active).toBe(false);
    expect(state.headers()['cache-control']).toContain('no-store');
});

test('HTML formatting is saved and reopened as HTML', async ({ page }) => {
    await settings(page);
    await configure(page, new Date(Date.now() - 300_000), new Date(Date.now() + 3_600_000), true);
    await page.getByRole('button', { name: 'Save automatic replies', exact: true }).click();
    await expect(page.locator('body')).toHaveClass(/auto-responder-active/);
    await expect(page.locator('[data-settings--auto-responder-target="editor"]')).toContainText('Away with formatted text');
    await expect(page.locator('[data-settings--auto-responder-target="editor"] b, [data-settings--auto-responder-target="editor"] strong')).toHaveCount(1);
    await page.getByRole('button', { name: 'Turn off automatic replies now', exact: true }).click();
});

test('scheduled start and end update an already open page', async ({ page }) => {
    await settings(page);
    const now = Date.now();
    await configure(page, new Date(now + 15_000), new Date(now + 25_000));
    await page.getByRole('button', { name: 'Save automatic replies', exact: true }).click();
    await expect(page.locator('body')).not.toHaveClass(/auto-responder-active/);
    await expect(page.locator('body')).toHaveClass(/auto-responder-active/, { timeout: 20_000 });
    await expect(page.locator('body')).not.toHaveClass(/auto-responder-active/, { timeout: 15_000 });
    await page.getByRole('button', { name: 'Turn off automatic replies now', exact: true }).click();
});

test('invalid periods keep the form and do not enable the responder', async ({ page }) => {
    await settings(page);
    await configure(page, new Date(Date.now() + 3_600_000), new Date(Date.now() + 1_800_000));
    await page.getByRole('button', { name: 'Save automatic replies', exact: true }).click();
    await expect(page.getByText('The end must follow the start', { exact: false })).toBeVisible();
    await expect(page.locator('body')).not.toHaveClass(/auto-responder-active/);
    await expect(page.locator('#auto_responder_message')).toHaveValue('I am away. I will reply when I return.');
});
