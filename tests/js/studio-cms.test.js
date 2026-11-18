import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = {};
const { updatePhoneRows, generatedStudioSlug, mountStudioSlug, photoSnapshot, mountStudioSlider, mountStudioUrl, studioUrlConfirmation } = await import('../../public/assets/js/admin/studios.js');
const escapeHtml = globalThis.window.escapeHtml;
delete globalThis.window;

function urlFixture({ dirty = false, valid = true, answer = true } = {}) {
    const events = {};
    const dialogs = [];
    const notifications = [];
    let requests = 0;
    let focused = 0;
    const button = { focus() { focused++; } };
    const form = {
        dataset: { studioName: 'Detailing Devils Head Office', currentUrl: 'https://www.detailingdevils.com/detailing-studios/old', urlPrefix: 'https://www.detailingdevils.com/detailing-studios/' },
        elements: { slug: { value: 'new' } },
        addEventListener(name, fn) { events[name] = fn; },
        getAttribute() { return null; },
        reportValidity() { return valid; },
        requestSubmit(submitter) {
            assert.equal(submitter, button);
            const event = submitEvent();
            events.submit(event);
            if (!event.defaultPrevented) requests++;
        },
    };
    const win = {
        escapeHtml,
        addEventListener() {},
        Swal: { async fire(options) { dialogs.push(options); return { isConfirmed: answer }; } },
        showNotification(options) { notifications.push(options); },
    };
    const submitEvent = () => ({ submitter: button, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
    mountStudioUrl(form, () => dirty, win);
    return { form, win, dialogs, notifications, submit: () => events.submit(submitEvent()), requests: () => requests, focused: () => focused };
}

test('URL confirmation shows both trusted URLs and submits only after confirmation', async () => {
    const f = urlFixture();
    await f.submit();
    assert.equal(f.requests(), 1);
    assert.equal(f.dialogs[0].titleText, 'Change Studio Page URL?');
    assert.ok(f.dialogs[0].html.includes('<strong>Detailing Devils Head Office.</strong>'));
    assert.ok(f.dialogs[0].html.includes('<dt>Current URL</dt>'));
    assert.ok(f.dialogs[0].html.includes('<dt>New URL</dt>'));
    assert.ok(f.dialogs[0].html.includes(f.form.dataset.currentUrl));
    assert.ok(f.dialogs[0].html.includes(`${f.form.dataset.urlPrefix}new`));
    assert.ok(f.dialogs[0].html.includes('Your previous Studio Page URL will automatically redirect to the new URL.'));
    assert.equal(f.dialogs[0].focusCancel, true);
    assert.equal(f.dialogs[0].showCloseButton, true);
    assert.equal(f.dialogs[0].customClass.popup, 'studio-url-dialog');
});

test('URL confirmation safely escapes studio names and URL text', async () => {
    const f = urlFixture();
    f.form.dataset.studioName = '<img src=x onerror="alert(1)"> & Studio';
    f.form.dataset.currentUrl += '?text=<script>alert(1)</script>&other=1';
    await f.submit();
    assert.ok(f.dialogs[0].html.includes(escapeHtml(f.form.dataset.studioName)));
    assert.ok(f.dialogs[0].html.includes(escapeHtml(f.form.dataset.currentUrl)));
    assert.ok(!f.dialogs[0].html.includes('<img'));
    assert.ok(!f.dialogs[0].html.includes('<script>'));
});

test('native confirmation fallback keeps the same confirm and cancel submission behavior', async () => {
    for (const answer of [false, true]) {
        const f = urlFixture();
        delete f.win.Swal;
        f.win.confirm = message => {
            const copy = studioUrlConfirmation(f.form.dataset.currentUrl, f.form.dataset.urlPrefix, 'new', f.form.dataset.studioName);
            assert.equal(message, `${copy.title} ${copy.text}`);
            assert.ok(message.includes('Current URL:'));
            assert.ok(message.includes('New URL:'));
            return answer;
        };
        await f.submit();
        assert.equal(f.requests(), Number(answer));
    }
});

test('Studio Page URL preview uses the canonical prefix and preserves customized segments', () => {
    const events = {}, input = { value: '', dispatchEvent() {}, focus() {}, addEventListener(type, fn) { events.segment = fn; } };
    const mode = { value: 'auto' }, output = {}, preview = {}, edit = {};
    const name = { value: 'Jaipur', addEventListener(type, fn) { events[type] = fn; } };
    edit.addEventListener = (type, fn) => { events.customize = fn; };
    const elements = { '[name="slug"]': input, '[name="slug_mode"]': mode, '[data-slug-preview]': preview, '[data-slug-output]': output, '[data-slug-edit]': edit };
    const field = { dataset: { new: 'true', slugs: '["jaipur"]', urlPrefix: 'https://www.detailingdevils.com/detailing-studios/' }, querySelector(selector) { return elements[selector]; } };
    mountStudioSlug({ querySelector(selector) { return selector === '[data-slug-editor]' ? field : name; } });
    assert.equal(input.value, 'jaipur-2');
    assert.equal(output.textContent, 'https://www.detailingdevils.com/detailing-studios/jaipur-2');
    assert.equal(input.hidden, true);
    events.customize();
    input.value = 'jaipur-3';
    name.value = 'Renamed studio';
    events.input();
    assert.equal(mode.value, 'manual');
    assert.equal(input.hidden, false);
    assert.equal(input.value, 'jaipur-3');
    events.segment();
    assert.equal(output.textContent, 'https://www.detailingdevils.com/detailing-studios/jaipur-3');
    assert.equal(preview.hidden, false);
});

test('Change URL preview updates without submitting and clears on cancel', () => {
    const f = urlFixture(), events = {}, preview = {};
    f.form.ownerDocument = { querySelector() { return preview; } };
    f.form.elements.slug.addEventListener = (type, fn) => { events[type] = fn; };
    f.form.addEventListener = (type, fn) => { events[type] = fn; };
    mountStudioUrl(f.form, () => false, f.win);
    f.form.elements.slug.value = 'jaipur-3';
    events.input();
    assert.equal(preview.hidden, false);
    assert.equal(preview.textContent, `${f.form.dataset.urlPrefix}jaipur-3`);
    events.reset();
    assert.equal(preview.hidden, true);
    assert.equal(f.requests(), 0);
});

test('cancelled, invalid and unsaved Studio edits prevent URL submission', async () => {
    for (const options of [{ answer: false }, { valid: false }, { dirty: true }]) {
        const f = urlFixture(options);
        await f.submit();
        assert.equal(f.requests(), 0);
        if (options.dirty) assert.match(f.notifications[0].title, /Save studio changes/);
    }
});

test('URL dialogs prevent duplicate requests and remain retryable after a failed confirmation', async () => {
    const f = urlFixture();
    let resolve;
    f.win.Swal.fire = () => new Promise(done => { resolve = done; });
    const pending = f.submit();
    await f.submit();
    assert.equal(f.requests(), 0);
    resolve({ isConfirmed: true });
    await pending;
    assert.equal(f.requests(), 1);
    f.win.Swal.fire = async () => { throw new Error('Fixture failure'); };
    await f.submit();
    assert.equal(f.requests(), 1);
    assert.equal(f.notifications.at(-1).type, 'error');
    f.win.Swal.fire = async () => ({ isConfirmed: true });
    await f.submit();
    assert.equal(f.requests(), 2);
});

function phone(value) {
    const nodes = {
        input: { name: '', value },
        '[data-phone-label]': { textContent: '' },
        '[data-phone-actions]': { hidden: true },
        '[data-phone-move="-1"]': { disabled: false },
        '[data-phone-move="1"]': { disabled: false },
        '[data-phone-remove]': { disabled: false },
    };
    return { querySelector: (selector) => nodes[selector], nodes };
}

test('phone positions serialize in order and only the first is labelled primary', () => {
    const rows = [phone('+91 98765 43210'), phone('+91 99887 76655')];
    assert.equal(updatePhoneRows({ querySelectorAll: () => rows }), 2);
    assert.equal(rows[0].nodes.input.name, 'phones[0]');
    assert.equal(rows[1].nodes.input.name, 'phones[1]');
    assert.equal(rows[0].nodes.input.id, 'studio-phone-0');
    assert.equal(rows[1].nodes['[data-phone-label]'].htmlFor, 'studio-phone-1');
    assert.equal(rows[0].nodes['[data-phone-label]'].textContent, 'Phone 1 · Primary');
    assert.equal(rows[1].nodes['[data-phone-label]'].textContent, 'Phone 2');
    assert.equal(rows[0].nodes['[data-phone-move="-1"]'].disabled, true);
    assert.equal(rows[1].nodes['[data-phone-move="1"]'].disabled, true);
    assert.equal(rows[0].nodes['[data-phone-actions]'].hidden, false);
});

test('reordering or removing phones does not rewrite their display values', () => {
    const first = phone('+91 98765 43210');
    const second = phone('+91 99887 76655');
    updatePhoneRows({ querySelectorAll: () => [second, first] });
    assert.equal(second.nodes.input.name, 'phones[0]');
    assert.equal(second.nodes.input.value, '+91 99887 76655');
    assert.equal(first.nodes.input.value, '+91 98765 43210');
    updatePhoneRows({ querySelectorAll: () => [first] });
    assert.equal(first.nodes.input.name, 'phones[0]');
    assert.equal(first.nodes['[data-phone-remove]'].disabled, true);
    assert.equal(first.nodes['[data-phone-move="1"]'].disabled, true);
});

test('phone validation descriptions stay attached to the correct row after reordering', () => {
    const row = phone('invalid');
    const error = {};
    row.nodes['[data-phone-error]'] = error;
    row.nodes.input.setAttribute = (key, value) => { row.nodes.input[key] = value; };
    updatePhoneRows({ querySelectorAll: () => [phone('+91 98765 43210'), row] });
    assert.equal(error.id, 'phone-error-1');
    assert.equal(row.nodes.input['aria-describedby'], 'phone-error-1');
    assert.equal(row.nodes['[data-phone-label]'].htmlFor, 'studio-phone-1');
    assert.equal(row.nodes.input.value, 'invalid');
});

test('slug previews normalize names and choose stable collision suffixes', () => {
    assert.equal(generatedStudioSlug(' Detailing Devils Jaipur! '), 'detailing-devils-jaipur');
    assert.equal(generatedStudioSlug('Caf\u00e9 & Cars'), 'cafe-cars');
    assert.equal(generatedStudioSlug('Foo&Bar'), 'foobar');
    assert.equal(generatedStudioSlug('Jaipur', ['jaipur', 'jaipur-2']), 'jaipur-3');
    assert.equal(generatedStudioSlug('!!!'), 'studio');
    assert.ok(generatedStudioSlug('long name '.repeat(30)).length <= 180);
});

test('saved photo snapshots detect metadata changes and ignore equivalent numeric order', () => {
    const original = { alt_text: 'Reception', sort_order: 2, is_cover: false };
    assert.equal(photoSnapshot(original), photoSnapshot({ ...original, sort_order: '02' }));
    assert.notEqual(photoSnapshot(original), photoSnapshot({ ...original, alt_text: 'Reception ' }));
    assert.notEqual(photoSnapshot(original), photoSnapshot({ ...original, sort_order: '' }));
    assert.notEqual(photoSnapshot(original), photoSnapshot({ ...original, is_cover: true }));
});

function sliderFixture(reduced = false, count = 3) {
    const node = () => ({
        events: {}, attributes: {}, dataset: {},
        addEventListener(type, fn) { this.events[type] = fn; },
        setAttribute(key, value) { this.attributes[key] = value; },
    });
    const stage = node(), media = node(), toggle = node(), icon = node();
    const photos = Array.from({ length: count }, node);
    const slides = Array.from({ length: count }, node);
    const clones = [node(), node()];
    const arrows = [node(), node()];
    arrows[0].dataset.photoStep = '-1';
    arrows[1].dataset.photoStep = '1';
    stage.closest = () => media;
    stage.querySelectorAll = selector => selector.includes(':not(') ? slides : selector === '.glide__slide--clone' ? clones : arrows;
    media.querySelector = () => toggle;
    media.contains = element => photos.includes(element) || element === stage || element === toggle;
    toggle.querySelector = () => icon;
    const doc = Object.assign(node(), {
        hidden: false, activeElement: null,
        querySelector: () => stage, querySelectorAll: () => photos,
    });
    const motion = Object.assign(node(), { matches: reduced });
    let instance;
    const win = Object.assign(node(), {
        matchMedia: () => motion, queueMicrotask: fn => fn(),
        Glide: class {
            constructor(element, options) { instance = this; this.index = 0; this.options = options; this.events = {}; }
            on(events, fn) { events.forEach(event => { this.events[event] = fn; }); }
            mount() { this.events['mount.after'](); }
            play(interval) { this.playing = true; this.interval = interval; }
            pause() { this.playing = false; }
            update(options) { Object.assign(this.options, options); }
            go(direction) {
                this.index = direction[0] === '=' ? Number(direction.slice(1)) : (this.index + (direction === '>' ? 1 : count - 1)) % count;
                this.events['run.after']();
            }
        },
    });
    mountStudioSlider(doc, win);
    return { instance, doc, win, motion, stage, media, toggle, icon, photos, slides, clones, arrows };
}

test('preview uses Glide autoplay and pauses for hover, focus and explicit pause', () => {
    const f = sliderFixture();
    assert.equal(f.instance.options.type, 'carousel');
    assert.equal(f.instance.options.animationDuration, 450);
    assert.equal(f.instance.interval, 4500);
    assert.equal(f.instance.playing, true);
    f.media.events.pointerenter({ pointerType: 'mouse' });
    assert.equal(f.instance.playing, false);
    f.media.events.pointerleave();
    assert.equal(f.instance.playing, true);
    f.media.events.pointerenter({ pointerType: 'touch' });
    assert.equal(f.instance.playing, true);
    f.doc.activeElement = f.photos[0];
    f.media.events.focusin();
    assert.equal(f.instance.playing, false);
    f.doc.activeElement = null;
    f.media.events.focusout();
    assert.equal(f.instance.playing, true);
    f.toggle.events.click();
    assert.equal(f.instance.playing, false);
    assert.equal(f.toggle.attributes['aria-label'], 'Play slideshow');
    f.media.events.pointerleave();
    assert.equal(f.instance.playing, false);
    f.toggle.events.click();
    assert.equal(f.instance.playing, true);
});

test('reduced motion, hidden documents and page history never leave autoplay running', () => {
    const f = sliderFixture(true);
    assert.equal(f.instance.playing, false);
    assert.equal(f.instance.options.animationDuration, 0);
    assert.equal(f.toggle.hidden, true);
    f.arrows[1].events.click();
    assert.equal(f.instance.index, 1);
    f.motion.matches = false;
    f.motion.events.change();
    assert.equal(f.instance.playing, true);
    assert.equal(f.toggle.hidden, false);
    f.doc.hidden = true;
    f.doc.events.visibilitychange();
    assert.equal(f.instance.playing, false);
    f.doc.hidden = false;
    f.doc.events.visibilitychange();
    assert.equal(f.instance.playing, true);
    f.win.events.pagehide();
    assert.equal(f.instance.playing, false);
    f.win.events.pageshow();
    assert.equal(f.instance.playing, true);
    f.toggle.events.click();
    f.motion.matches = true;
    f.motion.events.change();
    f.motion.matches = false;
    f.motion.events.change();
    assert.equal(f.instance.playing, false);
});

test('arrows, thumbnails and scoped keyboard navigation synchronize only original slides', () => {
    const f = sliderFixture();
    f.photos[2].events.click();
    assert.equal(f.photos[2].attributes['aria-pressed'], 'true');
    assert.equal(f.slides[2].attributes['aria-hidden'], 'false');
    assert.ok(f.clones.every(clone => clone.attributes['aria-hidden'] === 'true'));
    let prevented = false;
    f.media.events.keydown({ key: 'ArrowRight', preventDefault() { prevented = true; } });
    assert.equal(f.instance.index, 0);
    assert.equal(prevented, true);
    f.arrows[0].events.click();
    assert.equal(f.instance.index, 2);
    assert.equal(f.doc.events.keydown, undefined);
    assert.equal(sliderFixture(false, 1).instance, undefined);
    assert.equal(sliderFixture(false, 0).instance, undefined);
});
