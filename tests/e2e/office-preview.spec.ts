import { test, expect } from "./support/test";
import { seed, consoleCommand, TEST_USER } from "./support/config";

// Real conversion through the Gotenberg service in compose.test.yaml.
test.beforeAll(() => {
    seed("seed-mail");
    consoleCommand(`app:test:seed-attachment --office --email=${TEST_USER.email}`);
});

test("Office attachments use the shared reader while downloads retain the originals", async ({ page }) => {
    test.setTimeout(300_000);
    await page.goto('/mail/inbox');
    await page.locator('#message-list li').filter({ hasText: 'E2E Attachment' }).first().click();
    for (const extension of ['docx', 'xlsx', 'pptx', 'pdf']) {
        const filename = extension === 'pdf' ? 'e2e-document.pdf' : `e2e-office.${extension}`;
        await page.getByRole('button', { name: filename, exact: true }).click();
        const viewer = page.locator('#modal-backdrop [data-controller~="mail--pdf-viewer"]');
        await expect(viewer.locator('[data-mail--pdf-viewer-target="stack"] canvas').first()).toBeVisible({ timeout: 45000 });
        const width = await viewer.locator('[data-mail--pdf-viewer-target="stack"] canvas').first().evaluate(el => (el as HTMLCanvasElement).width);
        expect(width).toBeGreaterThan(0);
        if (extension !== 'pdf') {
            await expect(viewer.locator('[data-action="mail--pdf-sign#open"]')).toHaveCount(0);
            const source = await viewer.getAttribute('data-mail--pdf-viewer-src-value');
            if (extension === 'docx') {
                const denied = await page.request.post(source!, { maxRedirects: 0 });
                // A remembered session may be sent to reauthenticate instead of receiving 403.
                expect([302, 403]).toContain(denied.status());
            }
            const original = source!.replace('/preview.pdf', '?download=1');
            const download = await page.request.get(original);
            expect((await download.body()).subarray(0, 2).toString()).toBe('PK');
        } else {
            await expect(viewer.locator('[data-action="mail--pdf-sign#open"]')).toBeVisible();
        }
        await page.keyboard.press('Escape');
        await expect(page.locator('#modal-backdrop')).not.toBeVisible();
    }
});

test("conversion errors keep the original download available", async ({ page }) => {
    test.setTimeout(180_000);
    await page.goto('/mail/inbox');
    await page.locator('#message-list li').filter({ hasText: 'E2E Attachment' }).first().click();
    await page.route('**/mail/attachment/*/preview.pdf', route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Preview is temporarily unavailable.' }) }));
    await page.getByRole('button', { name: 'e2e-office.docx', exact: true }).click();
    await expect(page.locator('[data-mail--pdf-viewer-target="status"]')).toContainText('Preview is temporarily unavailable.', { timeout: 45000 });
    await expect(page.locator('#modal-backdrop a[download]')).toBeVisible();
});

test("attachment tiles download originals and offer a ZIP without opening the reader", async ({ page }) => {
    await page.goto('/mail/inbox');
    await page.locator('#message-list li').filter({ hasText: 'E2E Attachment' }).first().click();
    for (const extension of ['docx', 'xlsx', 'pptx', 'pdf']) {
        const filename = extension === 'pdf' ? 'e2e-document.pdf' : `e2e-office.${extension}`;
        const face = page.getByRole('button', { name: filename, exact: true });
        await face.hover();
        const link = face.locator('..').locator('.attachment-direct-download');
        await expect(link).toBeVisible();
        const pending = page.waitForEvent('download');
        await link.click();
        expect((await pending).suggestedFilename()).toBe(filename);
        await expect(page.locator('#modal-backdrop')).not.toBeVisible();
    }
    const all = page.locator('[data-attachments-download-all]');
    await expect(all).toHaveCount(1);
    const response = await page.request.get((await all.getAttribute('href'))!);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/zip');
    const body = await response.body();
    expect(body.subarray(0, 2).toString()).toBe('PK');
    for (const name of ['e2e-office.docx', 'e2e-office.xlsx', 'e2e-office.pptx', 'e2e-document.pdf']) {
        expect(body.includes(Buffer.from(name))).toBe(true);
    }
    const pending = page.waitForEvent('download');
    await all.click();
    expect((await pending).suggestedFilename()).toMatch(/^attachments-\d+\.zip$/);
    await expect(page.locator('#modal-backdrop')).not.toBeVisible();
});
