import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import { test } from 'node:test';
const source = readFileSync(new URL('../../assets/controllers/mail/mail_pane_controller.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '');
const { default: Pane } = await import(`data:text/javascript,${encodeURIComponent('class Controller {}\n' + source)}`);
function pane() {
    const p = new Pane();
    p._navigationUrls = ['https://mail.test/1', 'https://mail.test/2', 'https://mail.test/3'];
    p._selectedPath = '/1';
    p.buttons = [-1, 1].map(n => ({ dataset: { mailDirection: String(n) }, disabled: false }));
    p.readingTarget = { querySelectorAll: () => p.buttons };
    return p;
}
test('navigation boundaries, missing result and loading disable the appropriate buttons', () => {
    const p = pane();
    for (const [path, expected] of [['/1', [true, false]], ['/2', [false, false]], ['/3', [false, true]], ['/missing', [true, true]]]) {
        p._selectedPath = path;
        p._updateNavigation();
        assert.deepEqual(p.buttons.map(b => b.disabled), expected);
    }
    p._selectedPath = '/2'; p._navigating = true; p._updateNavigation();
    assert.deepEqual(p.buttons.map(b => b.disabled), [true, true]);
});
test('next uses result order, ignores concurrent clicks and recovers from a failed request', async () => {
    const p = pane();
    const visits = [];
    globalThis.history = { pushState: (...args) => visits.push(args[2]) };
    const event = { preventDefault() {}, currentTarget: p.buttons[1] };
    let finish;
    p._loadMessage = url => new Promise(resolve => { finish = () => { p._selectedPath = new URL(url).pathname; resolve(); }; });
    const pending = p.navigate(event);
    await p.navigate(event);
    finish(); await pending;
    assert.deepEqual(visits, ['https://mail.test/2']);
    p._loadMessage = async () => {};
    await p.navigate(event);
    assert.equal(visits.length, 1);
    assert.equal(p._navigating, false);
});
