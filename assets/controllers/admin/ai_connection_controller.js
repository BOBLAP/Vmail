import { Controller } from "@hotwired/stimulus";

/**
 * Puts away the fields that mean nothing for the chosen provider.
 *
 * Targets:
 *   provider    — the select that says which dialect the model host speaks
 *   ollamaOnly  — every block that is about something only Ollama can be asked:
 *                 how long a model is held, and loading one ahead of time
 *
 * WHY HIDE THEM AT ALL
 * ────────────────────
 * A keep-alive typed for an OpenAI-compatible endpoint is saved, shown back,
 * and sent nowhere — that dialect has no field for it. A setting that is
 * visibly there and silently does nothing is worse than one that is not there:
 * somebody tunes it, sees no difference, and concludes the endpoint is slow.
 *
 * HIDDEN, NOT REMOVED, AND NOT DISABLED. The values stay in the form and are
 * submitted and stored as they were, so switching to the other provider and
 * back loses nothing. Disabling would drop them from the submission and the
 * server would read that as "cleared".
 *
 * THE SERVER RENDERS THE FIRST STATE. Each block arrives with `hidden` already
 * set for the saved provider, so nothing flickers on load and the page is right
 * with JavaScript off — it then shows whatever the saved provider needs and
 * simply does not react to the select until the form is saved.
 *
 * The blocks are plain wrappers with no display class of their own, which is
 * what lets the attribute work: a `flex` on the same element would win over
 * `hidden`'s user-agent `display: none`, the cascade trap the warm-up button's
 * icons document.
 */
export default class extends Controller {
    static targets = ["provider", "ollamaOnly"];

    connect() {
        this.refresh();
    }

    refresh() {
        if (false === this.hasProviderTarget) {
            return;
        }

        const ollama = "ollama" === this.providerTarget.value;

        for (const block of this.ollamaOnlyTargets) {
            block.hidden = false === ollama;
        }
    }
}
