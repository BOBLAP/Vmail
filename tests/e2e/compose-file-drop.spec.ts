import { test, expect } from "./support/test";
import { seed } from "./support/config";

const PNG = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==";
const ROOT = '#compose_dock';
const EDITOR = '[data-compose--compose-target="body"]';

test.beforeAll(() => seed('clear-drafts'));

test('paste retains its own caret and both drop zones route files correctly', async ({ page }) => {
    test.setTimeout(600000);
    await page.goto('/mail/inbox');
    await page.getByRole('link', { name: 'Compose', exact: true }).first().click();
    const editor = page.locator(`${ROOT} ${EDITOR}`);
    await expect(editor).toBeVisible({ timeout: 180000 });
    await editor.evaluate(node => {
        node.innerHTML = '<p>beforeafter</p><p>ending</p>';
        (node as HTMLElement).focus();
        const range = document.createRange();
        range.setStart(node.firstChild!.firstChild!, 6);
        range.collapse(true);
        window.getSelection()!.removeAllRanges();
        window.getSelection()!.addRange(range);
        node.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // The upload waits while a new selection replaces the toolbar's saved range.
    // An image must still land at the paste position, not at the later blur.
    let release!: () => void;
    const gate = new Promise<void>(resolve => { release = resolve; });
    let uploading = false;
    await page.route('**/compose/inline-image/*', async route => {
        uploading = true;
        await gate;
        await route.continue();
    });
    await editor.evaluate((node, png) => {
        const data = new DataTransfer();
        data.items.add(new File([Uint8Array.from(atob(png), c => c.charCodeAt(0))], 'pasted.png', { type: 'image/png' }));
        node.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
    }, PNG);
    await expect.poll(() => uploading, { timeout: 180000 }).toBe(true);
    await editor.evaluate(node => {
        const range = document.createRange();
        range.selectNodeContents(node.lastChild!);
        range.collapse(false);
        window.getSelection()!.removeAllRanges();
        window.getSelection()!.addRange(range);
        node.dispatchEvent(new FocusEvent('blur'));
    });
    release();
    await expect(editor.locator('p').first().locator('img[data-cid]')).toHaveCount(1, { timeout: 180000 });
    expect(await editor.locator('p').first().evaluate(node => Array.from(node.childNodes).map(n => n.nodeName === 'IMG' ? '[image]' : n.textContent).join(''))).toBe('before[image]after');
    await page.unroute('**/compose/inline-image/*');

    // Dropping on a button bubbles to its attachment zone, including images.
    const buttons = page.locator(`${ROOT} [data-compose-drop-zone="attachments"]`);
    await buttons.locator('[data-action="click->compose--compose#openFilePicker"]').evaluate((node, png) => {
        const data = new DataTransfer();
        data.items.add(new File([Uint8Array.from(atob(png), c => c.charCodeAt(0))], 'attached.png', { type: 'image/png' }));
        data.items.add(new File(['document'], 'buttons.txt', { type: 'text/plain' }));
        node.dispatchEvent(new DragEvent('dragover', { dataTransfer: data, bubbles: true, cancelable: true }));
        node.dispatchEvent(new DragEvent('drop', { dataTransfer: data, bubbles: true, cancelable: true }));
    }, PNG);
    const attachments = page.locator(`${ROOT} [data-compose--compose-target="attachments"]`);
    await expect(attachments).toContainText('attached.png', { timeout: 180000 });
    await expect(attachments).toContainText('buttons.txt');
    await expect(editor.locator('img')).toHaveCount(1);

    // Mixed drops: pointer inside the second paragraph, old caret in the first.
    await editor.evaluate((node, png) => {
        const range = document.createRange();
        range.setStart(node.lastChild!.firstChild!, 3);
        range.setEnd(node.lastChild!.firstChild!, 4);
        const rect = range.getBoundingClientRect();
        const oldCaret = document.createRange();
        oldCaret.setStart(node.firstChild!.firstChild!, 0);
        oldCaret.collapse(true);
        window.getSelection()!.removeAllRanges();
        window.getSelection()!.addRange(oldCaret);
        const data = new DataTransfer();
        data.items.add(new File([Uint8Array.from(atob(png), c => c.charCodeAt(0))], 'dropped.png', { type: 'image/png' }));
        data.items.add(new File(['file'], 'body.txt', { type: 'text/plain' }));
        node.dispatchEvent(new DragEvent('drop', { dataTransfer: data, clientX: rect.left + 1, clientY: rect.top + rect.height / 2, bubbles: true, cancelable: true }));
    }, PNG);
    await expect(editor.locator('p').last().locator('img[data-cid]')).toHaveCount(1, { timeout: 180000 });
    await expect(attachments).toContainText('body.txt', { timeout: 180000 });
    await expect(attachments).not.toContainText('dropped.png');
    await expect(editor.locator('img')).toHaveCount(2);
    expect(await editor.innerHTML()).not.toContain('data:image');
    await expect(page.locator(`${ROOT} [data-compose-drop-active]`)).toHaveCount(0);
});
