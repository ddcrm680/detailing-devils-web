import test from 'node:test';
import assert from 'node:assert/strict';
globalThis.window = {};
const { fieldSnapshot, previewCopy, mountAdmin } =
    await import('../../public/assets/js/admin/workspace.js');
const sharedDom = { $: window.$, $$: window.$$ };
delete globalThis.window;

function node() {
    const classes = new Set();
    return {
        ...sharedDom,
        dataset: {},
        events: {},
        attributes: {},
        hidden: true,
        inert: false,
        textContent: '',
        classList: {
            add: (v) => classes.add(v),
            toggle: (v, on) => (on ? classes.add(v) : classes.delete(v)),
            contains: (v) => classes.has(v),
        },
        addEventListener(type, handler) {
            this.events[type] = handler;
        },
        setAttribute(key, value) {
            this.attributes[key] = value;
        },
        focus() {
            this.focused = true;
        },
    };
}

test('editor snapshots retain checkbox pairs and detect changes without trimming approved copy', () => {
    const initial = [
        ['title', ' Approved title '],
        ['sitemap', '0'],
        ['sitemap', '1'],
    ];
    assert.equal(fieldSnapshot(initial), fieldSnapshot(initial));
    assert.notEqual(fieldSnapshot(initial), fieldSnapshot(initial.slice(0, -1)));
    assert.notEqual(
        fieldSnapshot(initial),
        fieldSnapshot([['title', 'Approved title'], ...initial.slice(1)])
    );
});

test('live previews preserve exact text and use sharing fallbacks', () => {
    const copy = previewCopy({
        title: '<script>literal text</script>',
        description: 'Exact & approved',
        og_title: '',
        og_description: 'Sharing copy',
    });
    assert.equal(copy.title, '<script>literal text</script>');
    assert.equal(copy.socialTitle, copy.title);
    assert.equal(copy.socialDescription, 'Sharing copy');
    assert.deepEqual(previewCopy({}), {
        title: '',
        description: '',
        socialTitle: '',
        socialDescription: '',
    });
});

test('admin mounting is idempotent and responsive navigation supports Escape and inert state', () => {
    const body = node(),
        toggle = node(),
        sidebar = node(),
        backdrop = node(),
        link = node();
    sidebar.querySelector = () => link;
    sidebar.querySelectorAll = () => [link];
    const doc = {
        body,
        events: {},
        querySelector: (s) =>
            ({
                '[data-nav-toggle]': toggle,
                '#admin-sidebar': sidebar,
                '[data-menu-backdrop]': backdrop,
            })[s],
        addEventListener(type, handler) {
            this.events[type] = handler;
        },
    };
    const media = {
        matches: true,
        addEventListener(type, handler) {
            this.change = handler;
        },
    };
    mountAdmin(doc, { ...sharedDom, matchMedia: () => media });
    const original = toggle.events.click;
    mountAdmin(doc, {
        ...sharedDom,
        matchMedia: () => {
            throw Error('Double mount');
        },
    });
    assert.equal(toggle.events.click, original);
    assert.equal(sidebar.inert, true);
    toggle.events.click();
    assert.equal(sidebar.inert, false);
    assert.equal(toggle.attributes['aria-expanded'], 'true');
    assert.equal(backdrop.hidden, false);
    assert.equal(link.focused, true);
    doc.events.keydown({ key: 'Escape' });
    assert.equal(sidebar.inert, true);
    assert.equal(toggle.focused, true);
    media.matches = false;
    media.change();
    assert.equal(sidebar.inert, false);
});

test('preview retains unsaved warning while saving allows normal server navigation', () => {
    const OriginalFormData = globalThis.FormData;
    const body = node(),
        form = node(),
        state = node(),
        warning = node(),
        win = node();
    form.entries = [['title', 'Saved']];
    form.elements = { namedItem: () => null };
    const doc = {
        body,
        querySelector: (s) => ({ '[data-draft-form]': form, '[data-save-state]': state })[s],
        querySelectorAll: (s) => (s === '[data-unsaved-notice]' ? [warning] : []),
    };
    globalThis.FormData = class {
        constructor(form) {
            this.entries = form.entries;
        }
        [Symbol.iterator]() {
            return this.entries[Symbol.iterator]();
        }
    };
    try {
        mountAdmin(doc, win);
        assert.equal(state.textContent, 'No unsaved changes');
        form.entries = [['title', 'Changed']];
        form.events.input();
        assert.equal(state.textContent, 'Unsaved changes');
        assert.equal(warning.hidden, false);
        let blocked = 0;
        const event = () => ({
            preventDefault() {
                blocked++;
            },
        });
        win.events.beforeunload(event());
        form.events.submit({ submitter: { formTarget: '_blank' } });
        win.events.beforeunload(event());
        assert.equal(blocked, 2);
        form.events.submit({ submitter: { formTarget: '' } });
        win.events.beforeunload(event());
        assert.equal(blocked, 2);
    } finally {
        globalThis.FormData = OriginalFormData;
    }
});

test('repeated input and change events retain the approved image without duplicate image loads', () => {
    const OriginalFormData = globalThis.FormData;
    const body = node(),
        form = node(),
        image = node(),
        placeholder = node(),
        win = node();
    let source = '',
        loads = 0;
    Object.defineProperty(image, 'src', {
        set(value) {
            source = value;
            loads++;
        },
        get() {
            return source;
        },
    });
    image.getAttribute = () => source;
    image.removeAttribute = () => {
        source = '';
    };
    const option = { dataset: { previewSource: '/admin/seo/images/approved' } };
    form.entries = [['image', 'https://www.detailingdevils.com/approved.jpg']];
    form.elements = {
        namedItem: (name) => (name === 'image' ? { selectedOptions: [option] } : null),
    };
    const doc = {
        body,
        querySelector: (s) =>
            ({
                '[data-draft-form]': form,
                '[data-social-image]': image,
                '[data-image-placeholder]': placeholder,
            })[s],
        querySelectorAll: () => [],
    };
    globalThis.FormData = class {
        constructor(form) {
            this.entries = form.entries;
        }
        [Symbol.iterator]() {
            return this.entries[Symbol.iterator]();
        }
    };
    try {
        mountAdmin(doc, win);
        form.events.input();
        form.events.change();
        assert.equal(source, '/admin/seo/images/approved');
        assert.equal(loads, 1);
        assert.equal(image.hidden, false);
        delete option.dataset.previewSource;
        form.events.change();
        assert.equal(source, '');
        assert.equal(image.hidden, true);
        assert.equal(placeholder.hidden, false);
    } finally {
        globalThis.FormData = OriginalFormData;
    }
});
