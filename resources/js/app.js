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

window.Alpine = Alpine;
Alpine.start();
