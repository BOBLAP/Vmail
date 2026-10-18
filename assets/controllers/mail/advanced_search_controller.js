import { Controller } from "@hotwired/stimulus";
import { readAdvancedSearch, writeAdvancedSearch } from "../../advanced_search.js";

export default class extends Controller {
    static targets = ["field", "query"];

    connect() {
        const input = document.querySelector('[data-mail--search-target="input"]');
        const { fields, rest } = readAdvancedSearch(input?.value ?? "");
        this.rest = rest;
        for (const field of this.fieldTargets) {
            if (field.type === "checkbox") field.checked = Boolean(fields[field.name]);
            else field.value = fields[field.name] ?? "";
        }
    }

    changeState(event) {
        if (!event.target.checked) return;
        for (const field of this.fieldTargets) {
            if (["read", "unread"].includes(field.name) && field !== event.target) field.checked = false;
        }
    }

    submit(event) {
        event.preventDefault();
        const fields = {};
        for (const field of this.fieldTargets) {
            fields[field.name] = field.type === "checkbox" ? field.checked : field.value;
        }
        const query = writeAdvancedSearch(fields, this.rest);
        this.queryTarget.value = query;
        // Close through the shared shell, preserving its focus and cleanup.
        this.element.dispatchEvent(new CustomEvent("ui--modal:close", { bubbles: true }));
        document.dispatchEvent(new CustomEvent("mail:advanced-search", { detail: { query } }));
    }
}
