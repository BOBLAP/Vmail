import { expect } from '@playwright/test';
import { test } from './support/test';

const advanced = '[data-controller="mail--advanced-search"]';
const box = '[data-mail--search-target="input"]';
const trigger = '[data-ui--modal-src-value$="/mail/search/advanced"]';

test('advanced fields become search operators and survive reload', async ({ page }, testInfo) => {
    await page.goto('/mail/inbox');
    await page.locator(box).fill('quarterly after:2026-01-01');
    await page.locator(trigger).click();
    const form = page.locator(advanced);
    await expect(form).toBeVisible();
    for (const [name, value] of Object.entries({ from: 'alice', to: 'bob', cc: 'carol', subject: 'Q4 report', label: 'Team work' })) {
        await form.locator(`[name="${name}"]`).fill(value);
    }
    await form.locator('[name="attachment"]').check();
    await form.locator('[name="read"]').check();
    await form.locator('[name="unread"]').check();
    await expect(form.locator('[name="read"]')).not.toBeChecked();
    await page.screenshot({ path: testInfo.outputPath('advanced-search.png') });
    await form.locator('button[type="submit"]').click();
    const query = 'quarterly after:2026-01-01 from:alice to:bob cc:carol subject:"Q4 report" label:"Team work" has:attachment is:unread';
    await expect(page).toHaveURL(new RegExp('/mail/search\\?q='));
    expect(new URL(page.url()).searchParams.get('q')).toBe(query);
    await expect(page.locator(box)).toHaveValue(query);
    await page.reload();
    await page.locator(trigger).click();
    await expect(page.locator(advanced).locator('[name="subject"]')).toHaveValue('Q4 report');
    await expect(page.locator(advanced).locator('[name="attachment"]')).toBeChecked();
    await page.keyboard.press('Escape');
    await expect(page.locator('#modal-backdrop')).toBeHidden();
    await expect(page.locator(trigger)).toBeFocused();
});

test('mobile button opens the advanced form and cancelling preserves the query', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/mail/inbox');
    await page.locator('[data-ui--mobile-search-target="toggle"]').click();
    await page.locator(box).fill('from:alice');
    await page.locator(trigger).click();
    await expect(page.locator(advanced)).toBeVisible();
    await expect(page.locator(advanced).locator('[name="from"]')).toHaveValue('alice');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('advanced-search-mobile.png') });
    await page.locator(advanced).locator('button[type="button"]').click();
    await expect(page.locator(box)).toHaveValue('from:alice');
    await expect(page).not.toHaveURL(/\/mail\/search\?/);
});
