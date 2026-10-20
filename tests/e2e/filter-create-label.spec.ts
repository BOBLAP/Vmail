import { test, expect as baseExpect } from './support/test';
import { seed } from './support/config';
import { choose } from './support/select';

const expect = baseExpect.configure({ timeout: 60_000 });
test.use({ actionTimeout: 120_000, navigationTimeout: 120_000 });
test.beforeAll(() => seed('seed-label'));

for (const nested of [false, true]) {
    test(`create a ${nested ? 'nested' : 'root'} label while editing a filter`, async ({ page }) => {
        await page.goto('/settings?section=filters');
        await page.locator('#settings-filter-list').getByRole('link', { name: 'New filter' }).click();
        const editor = page.locator('#filter-editor');
        const name = `Rule label ${nested} ${Date.now()}`;
        await editor.getByLabel('Name', { exact: true }).fill(name);
        const condition = editor.locator('[data-rule-value]').first();
        await condition.fill('keep@example.test');
        await editor.getByRole('button', { name: 'Add action' }).click();
        await editor.getByRole('button', { name: 'Create label', exact: true }).click();
        const modal = page.locator('#modal');
        await expect(modal.locator('[name="label[name]"]')).toHaveValue(name);
        const parent = modal.locator('select[name="label[parent]"]');
        await expect(parent).toHaveValue('');
        let parentName = '';
        if (nested) {
            parentName = await parent.locator('option').nth(1).innerText();
            await choose(modal, 'Nest under', parentName);
        }
        await modal.getByRole('button', { name: 'Cancel', exact: true }).click();
        await expect(editor.getByLabel('Name', { exact: true })).toHaveValue(name);
        await expect(condition).toHaveValue('keep@example.test');
        await editor.getByRole('button', { name: 'Create label', exact: true }).click();
        if (nested) await choose(modal, 'Nest under', parentName);
        await modal.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.locator('#modal-backdrop')).toBeHidden();
        await expect(condition).toHaveValue('keep@example.test');
        const action = editor.locator('input[name="actions"]');
        const selected = JSON.parse(await action.inputValue())[0].labelId;
        expect(selected).toBeGreaterThan(0);
        const option = editor.locator('[data-rules--rule-builder-target="actionList"] select').nth(1).locator(`option[value="${selected}"]`);
        await expect(option).toHaveText(nested ? `${parentName}/${name}` : name);
        await editor.getByRole('button', { name: 'Save', exact: true }).click();
        const row = page.locator('#settings-filter-list li', { hasText: name });
        await expect(row).toBeVisible();
        await row.getByRole('link', { name: /^Edit filter/ }).click();
        await expect(action).toHaveValue(new RegExp(`"labelId":${selected}`));
    });
}
