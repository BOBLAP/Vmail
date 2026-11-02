import { test, expect, type Locator } from './support/test';
import { seed } from './support/config';

const addresses = ['maurice.lechat@toto.com', 'toto@toto.com', 'tata@tata.com', 'titi@titi.fr'];
const named = 'Maurice <maurice.lechat@toto.com>, toto@toto.com, tata@tata.com, Gertrude X <titi@titi.fr>';

test.beforeAll(() => { seed('seed-mail', 'clear-drafts'); });

async function paste(input: Locator, text: string) {
    await input.focus();
    await input.evaluate((node, text) => {
        const clipboard = new DataTransfer();
        clipboard.setData('text/plain', text);
        node.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
    }, text);
}

for (const [name, list] of [['names', named], ['commas', addresses.join(', ')], ['semicolons', addresses.join('; ')], ['lines', addresses.join('\n')]]) {
    test(`pasting ${name} creates individual recipients in To, Cc and Bcc`, async ({ page }) => {
        await page.goto('/mail/inbox');
        await page.getByRole('link', { name: 'Compose', exact: true }).first().click();
        const dock = page.locator('#compose_dock');
        await dock.getByRole('button', { name: 'Cc', exact: true }).click();
        await dock.getByRole('button', { name: 'Bcc', exact: true }).click();
        for (const field of ['toField', 'ccField', 'bccField']) {
            const row = dock.locator(`[data-compose--compose-target="${field}"]`);
            await paste(row.locator('.ts-control input'), list);
            await expect(row.locator('.ts-control .item')).toHaveCount(4);
            await paste(row.locator('.ts-control input'), addresses.join(';'));
            await expect(row.locator('.ts-control .item')).toHaveCount(4);
        }
    });
}

test('create, edit, insert and delete a private recipient group', async ({ page }) => {
    const name = `Group TOTO ${Date.now()}`;
    await page.goto('/settings?section=recipient-groups');
    await page.getByLabel('Group name').fill(name);
    await page.getByLabel('Email addresses').fill(named);
    await page.getByRole('button', { name: 'Save group', exact: true }).click();
    let card = page.locator('[data-recipient-group]').filter({ hasText: name });
    await expect(card).toContainText('4 emails');
    await page.screenshot({ path: 'test-results/recipient-groups-settings.png', fullPage: true });
    await card.getByRole('link', { name: 'Edit', exact: true }).click();
    await expect(page.getByLabel('Email addresses')).toHaveValue(addresses.join('\n'));
    await page.getByLabel('Group name').fill(`${name} edited`);
    await page.getByRole('button', { name: 'Save group', exact: true }).click();
    await page.goto('/mail/inbox');
    await page.getByRole('link', { name: 'Compose', exact: true }).first().click();
    const dock = page.locator('#compose_dock');
    await dock.getByRole('button', { name: 'Cc', exact: true }).click();
    await dock.getByRole('button', { name: 'Bcc', exact: true }).click();
    for (const field of ['toField', 'ccField', 'bccField']) {
        const row = dock.locator(`[data-compose--compose-target="${field}"]`);
        const picker = row.getByRole('combobox', { name: 'Add a group', exact: true });
        await picker.selectOption({ label: `${name} edited (4 emails)` });
        await expect(row.locator('.ts-control .item')).toHaveCount(4);
        await picker.selectOption({ label: `${name} edited (4 emails)` });
        await expect(row.locator('.ts-control .item')).toHaveCount(4);
    }
    await page.screenshot({ path: 'test-results/recipient-groups-compose.png', fullPage: true });
    const saved = page.waitForResponse(response => response.url().includes('/compose/draft') && response.request().method() === 'POST');
    await dock.locator('[data-compose--compose-toolbar-target="editor"]').fill('Recipients inserted from a group');
    expect((await saved).ok()).toBeTruthy();
    await page.goto('/settings?section=recipient-groups');
    card = page.locator('[data-recipient-group]').filter({ hasText: `${name} edited` });
    const id = await card.getAttribute('data-recipient-group');
    const refused = await page.request.post(`/settings/recipient-groups/${id}/delete`, { form: { _token: 'wrong' } });
    expect(refused.status()).toBe(403);
    // Submit with the real CSRF token; confirmation UI is covered by its shared tests.
    const response = await page.request.post(`/settings/recipient-groups/${id}/delete`, {
        form: { _token: (await card.locator('input[name="_token"]').inputValue()) },
    });
    expect(response.ok()).toBeTruthy();
    await page.reload();
    await expect(page.locator(`[data-recipient-group="${id}"]`)).toHaveCount(0);
});

test('an invalid group member stays visible and does not create a group', async ({ page }) => {
    await page.goto('/settings?section=recipient-groups');
    await page.getByLabel('Group name').fill('Invalid group');
    await page.getByLabel('Email addresses').fill('a@example.com; broken');
    await page.getByRole('button', { name: 'Save group', exact: true }).click();
    await expect(page.getByText('Check the addresses.', { exact: false })).toBeVisible();
    await expect(page.getByLabel('Email addresses')).toHaveValue('a@example.com; broken');
});

test('invalid pasted fragments stay available for correction', async ({ page }) => {
    await page.goto('/mail/inbox');
    await page.getByRole('link', { name: 'Compose', exact: true }).first().click();
    const row = page.locator('#compose_dock [data-compose--compose-target="toField"]');
    await paste(row.locator('.ts-control input'), 'a@example.com; broken; b@example.com');
    await expect(row.locator('.ts-control .item')).toHaveCount(2);
    await expect(row.locator('.ts-control input')).toHaveValue('broken');
    await expect(page.locator('#compose_dock [data-compose--compose-target="errors"]')).toContainText('broken');
});
