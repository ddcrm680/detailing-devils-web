import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/modules/studio-detail.js', import.meta.url), 'utf8');

function fixture(count, { library = true, reduced = false } = {}) {
    const events = {};
    const slides = Array.from({ length: count }, () => ({ setAttribute(name, value) { this[name] = value; } }));
    const previous = { addEventListener(name, handler) { this[name] = handler; } };
    const next = { addEventListener(name, handler) { this[name] = handler; } };
    const navigation = { hidden: true };
    const media = { matches: reduced, addEventListener(name, handler) { this[name] = handler; } };
    const tablet = { matches: false, addEventListener(name, handler) { this[name] = handler; } };
    const mobile = { matches: false, addEventListener(name, handler) { this[name] = handler; } };
    const controls = { '[data-services-navigation]': navigation, '[data-services-previous]': previous, '[data-services-next]': next };
    const services = {
        querySelectorAll() { return slides; },
        querySelector(selector) { return controls[selector]; },
        addEventListener(name, handler) { events[name] = handler; },
    };
    let slider = null;
    class Glide {
        constructor(element, settings) {
            assert.equal(element, services);
            this.settings = settings;
            this.index = 0;
            this.handlers = {};
            slider = this;
        }
        on(names, handler) { names.forEach(name => { this.handlers[name] = handler; }); }
        mount() { this.handlers['mount.after'](); }
        go(command) {
            const requested = command.startsWith('=') ? Number(command.slice(1)) : this.index + (command === '>' ? 1 : -1);
            this.index = Math.max(0, Math.min(requested, count - this.settings.perView));
            this.handlers['run.after']();
        }
        update(settings) {
            Object.assign(this.settings, settings);
            if (settings.startAt !== undefined) this.index = settings.startAt;
            this.handlers.update();
        }
    }
    const root = { querySelectorAll() { return []; }, querySelector(selector) { return selector === '[data-services-slider]' ? services : null; } };
    vm.runInNewContext(source, { document: { querySelector() { return root; } }, window: { matchMedia(query) { return query.includes('1024') ? tablet : query.includes('600') ? mobile : media; }, Glide: library ? Glide : undefined } });
    return {
        slider, slides, previous, next, navigation, media,
        resize(width) {
            const changedTablet = tablet.matches !== (width <= 1024);
            const changedMobile = mobile.matches !== (width <= 600);
            tablet.matches = width <= 1024;
            mobile.matches = width <= 600;
            if (changedTablet) tablet.change();
            if (changedMobile) mobile.change();
        },
        key(key) { let prevented = false; events.keydown({ key, preventDefault() { prevented = true; } }); return prevented; },
    };
}

test('services mount without any gallery with four/two/one responsive settings', () => {
    const f = fixture(6);
    assert.equal(f.slider.settings.perView, 4);
    assert.equal(f.slider.settings.autoplay, false);
    assert.equal(f.slider.settings.keyboard, false);
    assert.equal(f.navigation.hidden, false);
    assert.equal(f.previous.disabled, true);
    assert.equal(f.next.disabled, false);
    assert.deepEqual(f.slides.map(slide => slide['aria-hidden']), ['false', 'false', 'false', 'false', 'true', 'true']);
    f.resize(768);
    assert.equal(f.slider.settings.perView, 2);
    f.resize(390);
    assert.equal(f.slider.settings.perView, 1);
});

test('service arrows and scoped keyboard respect the last visible group', () => {
    const f = fixture(6);
    f.next.click();
    assert.equal(f.slider.index, 1);
    assert.equal(f.previous.disabled, false);
    assert.equal(f.key('ArrowRight'), true);
    assert.equal(f.slider.index, 2);
    assert.equal(f.next.disabled, true);
    f.previous.click();
    assert.equal(f.slider.index, 1);
    assert.equal(f.key('Enter'), false);
});

test('short lists hide controls when they fit and reveal them on mobile', () => {
    assert.equal(fixture(1).slider, null);
    assert.equal(fixture(1).navigation.hidden, true);
    const f = fixture(2);
    assert.equal(f.navigation.hidden, true);
    assert.equal(f.key('ArrowRight'), false);
    f.resize(390);
    assert.equal(f.navigation.hidden, false);
    f.next.click();
    assert.equal(f.next.disabled, true);
});

test('returning from the last mobile service clamps the desktop position safely', () => {
    const f = fixture(6);
    f.resize(390);
    f.slider.go('=5');
    f.resize(1440);
    assert.equal(f.slider.index, 2);
    assert.equal(f.next.disabled, true);
});

test('reduced motion and missing Glide preserve safe service rendering', () => {
    const f = fixture(6, { reduced: true });
    assert.equal(f.slider.settings.animationDuration, 0);
    f.media.matches = false;
    f.media.change();
    assert.equal(f.slider.settings.animationDuration, 380);
    assert.equal(fixture(6, { library: false }).slider, null);
});
