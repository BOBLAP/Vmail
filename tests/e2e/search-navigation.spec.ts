import { expect } from '@playwright/test';
import { test } from './support/test';

test('navigate search results in order and return to the same search', async ({ page }) => {
    await page.goto('/mail/search?q=in%3Ainbox');
    const links = page.locator('#inbox-list-frame li > a[data-action="click->mail--mail-pane#open"]');
    const urls = await links.evaluateAll(nodes => nodes.map(node => (node as HTMLAnchorElement).href));
    expect(urls.length).toBeGreaterThan(1);
    const searchUrl = page.url();
    await links.first().click();
    const previous = page.locator('[data-mail-direction="-1"]');
    const next = page.locator('[data-mail-direction="1"]');
    await expect(previous).toBeDisabled();
    for (let index = 1; index < urls.length; index++) {
        await next.click();
        await expect(page).toHaveURL(urls[index]);
    }
    await expect(next).toBeDisabled();
    await previous.click();
    await expect(page).toHaveURL(urls[urls.length - 2]);
    await page.goBack();
    await expect(page).toHaveURL(urls[urls.length - 1]);
    await expect(next).toBeDisabled();
    await page.locator('[data-action="click->mail--mail-pane#close"]').click();
    await expect(page).toHaveURL(searchUrl);
});
