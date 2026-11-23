import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import kiosk from './kiosk';

Alpine.plugin(collapse);

// Slide deck used by training modules.
Alpine.data('deck', (count) => ({
    i: 0,
    count,
    get last() {
        return this.i === this.count - 1;
    },
    next() {
        if (!this.last) this.i++;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    prev() {
        if (this.i > 0) this.i--;
    },
}));

// One-question-at-a-time quiz. Answers live in hidden inputs and are graded on the server.
Alpine.data('quiz', (ids) => ({
    ids,
    i: 0,
    answers: {},
    get current() {
        return this.ids[this.i];
    },
    get answered() {
        return Object.keys(this.answers).length;
    },
    get last() {
        return this.i === this.ids.length - 1;
    },
    pick(id, letter) {
        this.answers[id] = letter;
    },
    next() {
        if (!this.last) this.i++;
    },
    prev() {
        if (this.i > 0) this.i--;
    },
}));

Alpine.data('kiosk', kiosk);

// ISMS toolkit: instant filtering by type and search words. The server folds each item's text
// the same way (ToolkitItem::fold), so matching ignores case and Greek accents.
const fold = (text) =>
    String(text ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Mn}+/gu, '')
        .replace(/ς/g, 'σ')
        .trim();

Alpine.data('toolkit', ({ type = '', q = '', items = [] }) => ({
    type,
    q,
    items,
    get words() {
        return fold(this.q).split(/\s+/).filter(Boolean);
    },
    matches(item) {
        if (this.type && this.type !== item.type) return false;
        return this.words.every((w) => item.text.includes(w));
    },
    visible(id) {
        const item = this.items.find((i) => i.id === id);
        return item ? this.matches(item) : false;
    },
    get visibleCount() {
        return this.items.filter((i) => this.matches(i)).length;
    },
    pick(t) {
        this.type = this.type === t ? '' : t;
        this.sync();
    },
    reset() {
        this.type = '';
        this.q = '';
        this.sync();
    },
    // Keep the address bar in step so the filtered view can be shared or reloaded.
    sync() {
        const url = new URL(window.location.href);
        this.type ? url.searchParams.set('type', this.type) : url.searchParams.delete('type');
        this.q.trim() ? url.searchParams.set('q', this.q.trim()) : url.searchParams.delete('q');
        window.history.replaceState(null, '', url);
    },
    // A related-document pill: clear the filters, then open and highlight the linked item.
    jump(code) {
        this.reset();
        this.$nextTick(() => window.dispatchEvent(new CustomEvent('toolkit-jump', { detail: code })));
    },
}));

window.Alpine = Alpine;
Alpine.start();
