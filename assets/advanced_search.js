// Only the fields this dialog edits are replaced; starred state and
// free text survive opening and submitting it. Keep quoted values as one token.
export const TEXT_FILTERS = ["from", "to", "cc", "subject", "label", "after", "before"];

export function readAdvancedSearch(query) {
    const fields = {};
    const rest = [];
    const tokens = query.match(/(?:[^\s"]|"(?:\\.|[^"\\])*")+/g) ?? [];

    for (const token of tokens) {
        const match = token.match(/^(from|to|cc|subject|label|after|before):(.+)$/i);
        if (match) {
            let value = match[2];
            if (value.startsWith('"') && value.endsWith('"')) {
                try { value = JSON.parse(value); } catch { value = value.slice(1, -1); }
            }
            fields[match[1].toLowerCase()] = value;
        } else if (/^has:attachments?$/i.test(token)) {
            fields.attachment = true;
        } else if (/^is:(unread|read)$/i.test(token)) {
            // The dialog cannot represent contradictory read states.
            fields.unread = /^is:unread$/i.test(token);
            fields.read = !fields.unread;
        } else {
            rest.push(token);
        }
    }
    return { fields, rest };
}

export function writeAdvancedSearch(fields, rest = []) {
    const tokens = [...rest];
    for (const name of TEXT_FILTERS) {
        const value = (fields[name] ?? "").trim();
        if (value) {
            // JSON quoting also escapes embedded quotes and backslashes; the
            // server parser understands this same representation.
            tokens.push(`${name}:${/[\s"\\']/u.test(value) ? JSON.stringify(value) : value}`);
        }
    }
    if (fields.attachment) tokens.push("has:attachment");
    if (fields.unread) tokens.push("is:unread");
    else if (fields.read) tokens.push("is:read");
    return tokens.join(" ");
}
