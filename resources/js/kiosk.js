// Supervised kiosk flow: pick name → employee code → (policies) → rules → questions → sign-off.
export default (config) => ({
    workers: config.workers,
    departments: config.departments,
    t: config.t,
    screen: 'pick',
    department: null,
    search: '',
    worker: null,
    code: '',
    error: '',
    busy: false,
    declared: false,
    content: { policies: [], slides: [], questions: [], declaration: '' },
    slide: 0,
    q: 0,
    answers: {},
    result: null,
    countdown: 5,
    timer: null,
    exitOpen: false,

    get filtered() {
        const needle = this.norm(this.search);
        return this.workers.filter(
            (w) => (this.department === null || w.department_id === this.department) && (!needle || this.norm(w.name).includes(needle)),
        );
    },

    // Case- and accent-insensitive, so "παπα" finds "Παπαδόπουλος".
    norm(s) {
        return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    },

    initials(name) {
        return name.split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
    },

    choose(w) {
        this.worker = w;
        this.code = '';
        this.error = '';
        this.screen = 'code';
    },

    key(k) {
        this.error = '';
        if (k === '⌫') this.code = this.code.slice(0, -1);
        else if (k === '✓') this.verify();
        else if (this.code.length < 12) this.code += k;
    },

    async post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || this.t.error);
        return data;
    },

    async verify() {
        if (!this.code || this.busy) return;
        this.busy = true;
        try {
            const data = await this.post(config.urls.verify, { user_id: this.worker.id, code: this.code });
            this.content = data;
            this.slide = 0;
            this.q = 0;
            this.answers = {};
            this.declared = false;
            this.screen = data.already_complete ? 'already' : data.policies.length ? 'policies' : 'slides';
        } catch (e) {
            this.error = e.message;
            this.code = '';
        } finally {
            this.busy = false;
        }
    },

    nextSlide() {
        if (this.slide < this.content.slides.length - 1) this.slide++;
        else this.screen = 'quiz';
        window.scrollTo(0, 0);
    },

    nextQuestion() {
        if (this.q < this.content.questions.length - 1) {
            this.q++;
            window.scrollTo(0, 0);
        } else this.submit();
    },

    async submit() {
        this.busy = true;
        this.error = '';
        try {
            this.result = await this.post(config.urls.submit, { answers: this.answers });
            this.screen = 'result';
            window.scrollTo(0, 0);
            if (this.result.passed) this.startCountdown();
        } catch (e) {
            this.error = e.message;
        } finally {
            this.busy = false;
        }
    },

    async retry() {
        this.busy = true;
        try {
            const data = await this.post(config.urls.questions);
            this.content.questions = data.questions;
            this.answers = {};
            this.q = 0;
            this.result = null;
            this.screen = 'quiz';
            window.scrollTo(0, 0);
        } catch (e) {
            this.reset();
        } finally {
            this.busy = false;
        }
    },

    startCountdown() {
        this.countdown = 5;
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (--this.countdown <= 0) this.reset();
        }, 1000);
    },

    reset() {
        clearInterval(this.timer);
        Object.assign(this, {
            screen: 'pick', worker: null, code: '', error: '', search: '', declared: false,
            slide: 0, q: 0, answers: {}, result: null, busy: false,
            content: { policies: [], slides: [], questions: [], declaration: '' },
        });
        window.scrollTo(0, 0);
    },
});
