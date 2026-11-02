import { Controller } from '@hotwired/stimulus';

/** Polls edits from another tab and schedules local start/end transitions. */
export default class extends Controller {
    static values = { url: String, state: Object };

    connect() {
        this.state = this.stateValue;
        this.render();
        this.onVisible = () => { if (!document.hidden) this.refresh(); };
        document.addEventListener('visibilitychange', this.onVisible);
        this.poll = setInterval(() => { if (!document.hidden) this.refresh(); }, 60_000);
        this.refresh();
    }

    disconnect() {
        clearInterval(this.poll);
        clearTimeout(this.boundary);
        this.request?.abort();
        document.removeEventListener('visibilitychange', this.onVisible);
    }

    async refresh() {
        this.request?.abort();
        this.request = new AbortController();
        try {
            const response = await fetch(this.urlValue, { signal: this.request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            this.state = await response.json();
            this.render();
        } catch { /* Keep the last schedule during a temporary network failure. */ }
    }

    render() {
        clearTimeout(this.boundary);
        const now = Date.now();
        const start = Date.parse(this.state.startsAt);
        const end = Date.parse(this.state.endsAt);
        const active = this.state.enabled && start <= now && now < end;
        this.element.classList.toggle('auto-responder-active', !!active);
        for (const tile of this.element.querySelectorAll('[data-auto-responder-banner]')) {
            tile.hidden = !active;
            tile.classList.toggle('hidden', !active);
            tile.setAttribute('aria-label', this.state.label || '');
            tile.title = this.state.label || '';
            const text = tile.querySelector('[data-auto-responder-label]');
            if (text) text.textContent = this.state.label || '';
        }
        const next = [start, end].filter(time => time > now).sort((a, b) => a - b)[0];
        if (next && this.state.enabled) this.boundary = setTimeout(() => { this.render(); this.refresh(); }, Math.min(next - now + 10, 2_147_483_647));
    }
}
