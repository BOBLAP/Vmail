import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const source = (await readFile(new URL('../assets/schedule_options.js', import.meta.url), 'utf8'))
    .replace('import { prefersHour12 } from "./clock_format.js";', 'const prefersHour12 = () => false;');
const { instantOf } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('custom schedules retain seconds in the configured timezone', () => {
    assert.equal(instantOf('2026-10-24T09:30:37', 'Europe/Paris'), Date.parse('2026-10-24T07:30:37Z'));
    assert.equal(instantOf('2026-10-24T09:30', 'Europe/Paris'), Date.parse('2026-10-24T07:30:00Z'));
    assert.ok(Number.isNaN(instantOf('2026-10-24T09:30:99', 'Europe/Paris')));
});

test('seconds preserve the existing daylight saving resolution', () => {
    for (const date of ['2026-10-25T02:30', '2027-03-28T02:30']) {
        assert.equal(instantOf(`${date}:37`, 'Europe/Paris'), instantOf(date, 'Europe/Paris') + 37_000);
    }
});
