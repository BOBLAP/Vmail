import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';

const source = await readFile(new URL('../assets/compose/recipient_list.js', import.meta.url), 'utf8');
const { parseRecipientList, addRecipients } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const emails = ['maurice.lechat@toto.com', 'toto@toto.com', 'tata@tata.com', 'titi@titi.fr'];

for (const text of [
    'Maurice <maurice.lechat@toto.com>, toto@toto.com, tata@tata.com, Gertrude X <titi@titi.fr>',
    emails.join(', '), emails.join('; '), emails.join('\r\n'),
]) {
    test(`four addresses: ${text}`, () => {
        const result = parseRecipientList(text);
        assert.deepEqual(result.recipients.map(item => item.email), emails);
        assert.deepEqual(result.invalid, []);
    });
}

test('quoted separators, escaped quotes and case-insensitive duplicates', () => {
    const result = parseRecipientList('"Lechat, Maurice; X" <MAURICE@toto.com>; maurice@toto.com; "Gert\\"rude" <titi@titi.fr>');
    assert.deepEqual(result.recipients, [
        { email: 'maurice@toto.com', label: 'Lechat, Maurice; X <maurice@toto.com>' },
        { email: 'titi@titi.fr', label: 'Gert"rude <titi@titi.fr>' },
    ]);
});

test('invalid fragments remain available to correct', () => {
    assert.deepEqual(parseRecipientList('a@example.com; broken; b@example.com').invalid, ['broken']);
    assert.equal(parseRecipientList('A <a@example.com>, B <b@example.com').recipients.length, 1);
    assert.equal(parseRecipientList('"Unclosed <a@example.com>').recipients.length, 0);
});

test('groups reuse contact IDs and never add a duplicate recipient', () => {
    const select = {
        settings: { labelField: 'text', valueField: 'value' },
        options: { 12: { value: '12', text: 'Maurice <maurice.lechat@toto.com>' } },
        items: ['12'],
        addOption(option) { this.options[option.value] = option; },
        addItem(value) { if (!this.items.includes(value)) this.items.push(value); },
    };
    const recipients = parseRecipientList(emails.join(';')).recipients;
    addRecipients(select, recipients);
    addRecipients(select, recipients);
    assert.deepEqual(select.items, ['12', ...emails.slice(1)]);
});
