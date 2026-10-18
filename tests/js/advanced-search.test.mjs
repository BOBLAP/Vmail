import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readAdvancedSearch, writeAdvancedSearch } from '../../assets/advanced_search.js';

test('keeps free text and unsupported dialog filters while replacing edited filters', () => {
    const { fields, rest } = readAdvancedSearch('quarterly from:old after:2026-01-01 is:starred label:"Team work" has:attachment');
    assert.equal(fields.label, 'Team work');
    assert.equal(fields.attachment, true);
    assert.equal(writeAdvancedSearch({ ...fields, from: 'new', attachment: false }, rest),
        'quarterly is:starred from:new label:"Team work" after:2026-01-01');
});

test('quotes values that contain spaces, operators, literal quotes or backslashes', () => {
    const fields = { from: 'Alice Smith', to: 'bob@example.test', cc: 'carol', subject: 'report "Q4" \\ draft', label: "Clients'", unread: true, attachment: true };
    const query = writeAdvancedSearch(fields);
    assert.deepEqual(readAdvancedSearch(query), { fields: { ...fields, read: false }, rest: [] });
    assert.equal(writeAdvancedSearch({ subject: 'invoice from:someone' }), 'subject:"invoice from:someone"');
});

test('does not emit empty fields or contradictory read filters', () => {
    assert.equal(writeAdvancedSearch({ from: '  ', read: true, unread: true }), 'is:unread');
    assert.equal(writeAdvancedSearch({}), '');
    assert.deepEqual(readAdvancedSearch('is:unread is:read').fields, { unread: false, read: true });
});

test('loads, replaces and clears date filters without keeping stale date tags', () => {
    const { fields, rest } = readAdvancedSearch('invoice after:2026-01-01 before:2026-09-01 is:starred');
    assert.equal(fields.after, '2026-01-01');
    assert.equal(fields.before, '2026-09-01');
    assert.equal(writeAdvancedSearch({ ...fields, after: '', before: '2026-10-01' }, rest),
        'invoice is:starred before:2026-10-01');
    assert.equal(writeAdvancedSearch({ after: '', before: '' }, rest), 'invoice is:starred');
});
