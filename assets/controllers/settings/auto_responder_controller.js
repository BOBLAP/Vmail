import { Controller } from '@hotwired/stimulus';

/** Shares formatting with the composer; only the selected format is submitted. */
export default class extends Controller {
    static targets = ['format', 'plain', 'rich', 'message', 'editor'];

    connect() {
        this.format = this.formatTarget.value;
        this.show();
    }

    changeFormat() {
        const next = this.formatTarget.value;
        if (next === this.format) return;
        if (next === 'html') {
            this.editorTarget.textContent = this.messageTarget.value;
            this.editorTarget.innerHTML = this.editorTarget.innerHTML.replace(/\n/g, '<br>');
        } else {
            this.messageTarget.value = this.editorTarget.innerText;
        }
        this.format = next;
        this.show();
    }

    show() {
        const html = this.format === 'html';
        this.plainTarget.hidden = html;
        this.richTarget.hidden = !html;
        // A hidden required textarea would block submission before the submit event.
        this.messageTarget.required = !html;
    }

    prepare() {
        if (this.format === 'html') this.messageTarget.value = this.editorTarget.innerHTML;
    }
}
