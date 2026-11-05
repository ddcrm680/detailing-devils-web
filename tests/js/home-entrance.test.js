import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('public/assets/js/modules/home-entrance.js', 'utf8');
const key = 'dd.home-entrance.dismissed.v1';

function setup({ saved = false, readFails = false, writeFails = false, styled = true, reduced = false } = {}) {
    let now = 0;
    let nextId = 0;
    const timers = new Map();
    const storage = new Map(saved ? [[key, '1']] : []);
    const writes = [];
    const classes = new Set();
    const events = new Map();
    const element = () => ({
        hidden: true,
        dataset: {},
        style: { removeProperty(name) { delete this[name]; } },
        textContent: '',
        classes: new Set(),
        addEventListener(name, handler) { events.set(`${this.id}:${name}`, handler); },
        removeEventListener(name) { events.delete(`${this.id}:${name}`); },
        focus() { this.focused = true; },
        click() { events.get(`${this.id}:click`)?.(); },
        remove() { this.removed = true; },
        get classList() {
            return {
                add: value => this.classes.add(value),
                remove: value => this.classes.delete(value),
                toggle: (value, enabled) => enabled ? this.classes.add(value) : this.classes.delete(value),
            };
        },
    });
    const entrance = element();
    entrance.id = 'entrance';
    entrance.style.display = 'none';
    const button = element();
    button.id = 'button';
    const typed = element();
    const quote = { textContent: 'Every finish earned by hand. Protected to last. Built for those who refuse dull.' };
    const bar = element();
    const percentage = element();
    const focus = element();
    entrance.querySelector = selector => ({
        button,
        '.home-entrance__typed': typed,
        '.home-entrance__accessible-quote': quote,
        '.home-entrance__bar b': bar,
        '.home-entrance__percentage': percentage,
    })[selector];
    const document = {
        activeElement: focus,
        documentElement: { classList: { add: value => classes.add(value), remove: value => classes.delete(value) } },
        getElementById: id => id === 'home-entrance' ? entrance : { style: { width: '0%' } },
        addEventListener: (name, handler) => events.set(`document:${name}`, handler),
        removeEventListener: name => events.delete(`document:${name}`),
    };
    const window = {
        localStorage: {
            getItem(name) {
                if (readFails) throw new Error('Storage denied');
                return storage.get(name) ?? null;
            },
            setItem(name, value) {
                if (writeFails) throw new Error('Storage denied');
                writes.push(name);
                storage.set(name, value);
            },
        },
        matchMedia: () => ({ matches: reduced }),
        addEventListener: (name, handler) => events.set(`window:${name}`, handler),
        removeEventListener: name => events.delete(`window:${name}`),
    };
    const schedule = (handler, delay, interval) => {
        const id = ++nextId;
        timers.set(id, { handler, at: now + delay, interval });
        return id;
    };
    const context = vm.createContext({
        window, document,
        getComputedStyle: () => ({ position: styled ? 'fixed' : 'static' }),
        performance: { now: () => now },
        setTimeout: (handler, delay) => schedule(handler, delay, 0),
        setInterval: (handler, delay) => schedule(handler, delay, delay),
        clearTimeout: id => timers.delete(id),
        clearInterval: id => timers.delete(id),
    });
    const run = () => vm.runInContext(source, context);
    run();
    const advance = milliseconds => {
        const end = now + milliseconds;
        while (true) {
            const pending = [...timers].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
            if (!pending) break;
            const [id, timer] = pending;
            now = timer.at;
            if (timer.interval) timer.at += timer.interval;
            else timers.delete(id);
            timer.handler();
        }
        now = end;
    };
    return { entrance, button, typed, quote, percentage, focus, classes, events, storage, writes, timers, advance, run };
}

test('first visit allows immediate ENTER and records exactly one dismissal', () => {
    const state = setup();
    assert.equal(state.entrance.hidden, false);
    assert.equal(state.entrance.style.display, undefined);
    assert.equal(state.entrance.focused, true);
    assert.equal(state.button.focused, undefined);
    state.events.get('button:click')();
    state.events.get('button:click')();
    assert.deepEqual(state.writes, [key]);
    assert.equal(state.classes.size, 0);
    assert.equal(state.focus.focused, true);
    state.advance(800);
    assert.equal(state.entrance.removed, true);
    assert.equal(state.timers.size, 0);
});

test('the entrance stays open after animation and loading finish until ENTER', () => {
    const state = setup();
    state.advance(60000);
    assert.equal(state.storage.has(key), false);
    assert.equal(state.typed.textContent, state.quote.textContent);
    assert.equal(state.entrance.removed, undefined);
    assert.equal(state.classes.has('home-entrance-active'), true);
    assert.equal(state.timers.size, 0);
    state.events.get('button:click')();
    assert.equal(state.storage.get(key), '1');
    assert.equal(state.percentage.textContent, '100%');
    assert.equal(state.classes.size, 0);
    state.advance(800);
    assert.equal(state.entrance.removed, true);
    assert.equal(state.timers.size, 0);
});

test('a returning browser skips the entrance without timers or scroll locking', () => {
    const state = setup({ saved: true });
    assert.equal(state.entrance.hidden, true);
    assert.equal(state.entrance.removed, true);
    assert.equal(state.entrance.style.display, 'none');
    assert.equal(state.classes.size, 0);
    assert.equal(state.timers.size, 0);
    assert.equal(state.events.size, 0);
});

test('unavailable storage fails open', () => {
    const state = setup({ readFails: true });
    assert.equal(state.entrance.hidden, true);
    assert.equal(state.entrance.removed, true);
    assert.equal(state.entrance.style.display, 'none');
    assert.equal(state.classes.size, 0);
    assert.equal(state.timers.size, 0);
});

test('a write failure never prevents dismissal', () => {
    const state = setup({ writeFails: true });
    assert.doesNotThrow(() => state.events.get('button:click')());
    state.advance(800);
    assert.equal(state.entrance.removed, true);
    assert.equal(state.classes.size, 0);
});

test('missing CSS fails open', () => {
    const state = setup({ styled: false });
    assert.equal(state.entrance.hidden, true);
    assert.equal(state.timers.size, 0);
});

test('reduced motion shows the quote immediately and removes the entrance without a fade', () => {
    const state = setup({ reduced: true });
    assert.equal(state.typed.textContent, state.quote.textContent);
    state.events.get('button:click')();
    state.advance(0);
    assert.equal(state.entrance.removed, true);
});

test('keyboard focus stays on ENTER and Escape does not dismiss', () => {
    const state = setup();
    let prevented = false;
    const handleKey = state.events.get('document:keydown');
    handleKey({ key: 'Tab', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    handleKey({ key: 'Escape' });
    assert.equal(state.storage.has(key), false);
    assert.equal(state.classes.has('home-entrance-active'), true);
});

test('keyboard ENTER activates the button from the initially focused entrance', () => {
    const state = setup();
    let prevented = false;
    state.events.get('document:keydown')({
        key: 'Enter',
        preventDefault() { prevented = true; },
    });
    assert.equal(prevented, true);
    assert.equal(state.storage.get(key), '1');
    state.advance(800);
    assert.equal(state.entrance.removed, true);
});

test('leaving without ENTER does not register a dismissal handler or marker', () => {
    const state = setup();
    assert.equal(state.events.has('window:pagehide'), false);
    assert.equal(state.storage.has(key), false);
});

test('loading the script twice does not duplicate initialization', () => {
    const state = setup();
    const timers = state.timers.size;
    const events = state.events.size;
    state.run();
    assert.equal(state.timers.size, timers);
    assert.equal(state.events.size, events);
});
