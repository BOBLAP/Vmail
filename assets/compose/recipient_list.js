/** Split address lists without splitting quoted names or angle addresses. */
export function parseRecipientList(raw) {
    const parts = [];
    let part = '', quoted = false, escaped = false, depth = 0;
    for (const char of raw) {
        if (escaped) { part += char; escaped = false; continue; }
        if (quoted && char === '\\') { part += char; escaped = true; continue; }
        if (char === '"') quoted = !quoted;
        if (!quoted && char === '<') depth++;
        if (!quoted && char === '>') depth = Math.max(0, depth - 1);
        if (!quoted && depth === 0 && /[,;\r\n]/.test(char)) {
            parts.push(part.trim()); part = '';
        } else part += char;
    }
    parts.push(part.trim());
    const recipients = [], invalid = [], seen = new Set();
    for (const text of parts.filter(Boolean)) {
        const named = /^("(?:[^"\\]|\\.)*"|[^"<>]*)\s*<([^<>]+)>$/.exec(text);
        const email = (named ? named[2] : text).trim().toLowerCase();
        if (!/^[^@\s<>,;"()]+@[^@\s<>,;"()]+\.[^@\s<>,;"()]+$/.test(email)) {
            invalid.push(text); continue;
        }
        if (seen.has(email)) continue;
        seen.add(email);
        const name = named?.[1].trim().replace(/^"(.*)"$/, '$1').replace(/\\(["\\])/g, '$1');
        recipients.push({ email, label: name ? `${name} <${email}>` : email });
    }
    return { recipients, invalid };
}

/** Add real address options, reusing learned contacts and avoiding duplicates. */
export function addRecipients(select, recipients) {
    for (const recipient of recipients) {
        const existing = Object.values(select.options).find(option =>
            parseRecipientList(option[select.settings.labelField] ?? '').recipients[0]?.email === recipient.email
            || String(option[select.settings.valueField]).toLowerCase() === recipient.email,
        );
        const value = existing?.[select.settings.valueField] ?? recipient.email;
        if (!existing) select.addOption({
            [select.settings.valueField]: value,
            [select.settings.labelField]: recipient.label,
        });
        select.addItem(String(value));
    }
}
