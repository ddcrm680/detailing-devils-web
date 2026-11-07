import test from 'node:test';
import assert from 'node:assert/strict';
globalThis.window = {};
const { fieldSnapshot, previewCopy, confirmationCopy, mountAdmin, mountSubmissionFeedback } =
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
        getAttribute(key) {
            return this.attributes[key] ?? null;
        },
        hasAttribute(key) {
            return Object.hasOwn(this.attributes, key);
        },
        removeAttribute(key) {
            delete this.attributes[key];
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

test('Twitter preview uses its own copy with the existing Open Graph fallback order', () => {
    const fields = { title: 'Search title', description: 'Search copy', og_title: 'OG title', og_description: 'OG copy', twitter_title: 'X title' };
    assert.equal(previewCopy(fields, 'twitter').socialTitle, 'X title');
    assert.equal(previewCopy(fields, 'twitter').socialDescription, 'OG copy');
    assert.equal(previewCopy(fields).socialTitle, 'OG title');
    assert.equal(previewCopy({ title: '<script>literal</script>' }, 'twitter').socialTitle, '<script>literal</script>');
});

test('confirmation copy distinguishes saved-draft publication from audited restoration', () => {
    assert.equal(confirmationCopy('publish').button, 'Publish');
    assert.match(confirmationCopy('publish').description, /saved draft/);
    assert.equal(confirmationCopy('restore').button, 'Restore');
    assert.match(confirmationCopy('restore').description, /Previous versions are kept/);
});

test('responsive filters collapse below 1024px without replacing form controls or selected values', () => {
    const body = node(), panel = node(), summary = node();
    const media = { matches: false, addEventListener(type, callback) { this.change = callback; } };
    panel.contains = () => false;
    panel.querySelector = () => summary;
    panel.selectedValue = 'published';
    const doc = { body, querySelector: selector => selector === '[data-filter-panel]' ? panel : null };
    mountAdmin(doc, { ...sharedDom, matchMedia: () => media });
    assert.equal(panel.open, false);
    panel.open = true;
    assert.equal(panel.selectedValue, 'published');
    media.matches = true;
    media.change();
    assert.equal(panel.open, true);
    media.matches = false;
    panel.contains = () => true;
    media.change();
    assert.equal(panel.open, false);
    assert.equal(summary.focused, true);
    assert.equal(panel.selectedValue, 'published');
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

test('preview retains unsaved warning while saving allows normal server navigation', async () => {
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
        await new Promise(queueMicrotask);
        win.events.beforeunload(event());
        assert.equal(blocked, 2);
        form.events.submit({ submitter: { formTarget: '' } });
        await new Promise(queueMicrotask);
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

test('live preview tabs support keyboard selection without replacing fields or leaving content hidden without JavaScript', () => {
    const OriginalFormData = globalThis.FormData;
    const form = node(), body = node(), tabs = node(), search = node(), social = node();
    const searchPanel = node(), socialPanel = node();
    search.dataset.previewTab = 'search';
    social.dataset.previewTab = 'social';
    searchPanel.dataset.previewPanel = 'search';
    socialPanel.dataset.previewPanel = 'social';
    tabs.querySelectorAll = () => [search, social];
    form.elements = { namedItem: () => null };
    const doc = {
        body,
        querySelector: selector => ({ '[data-draft-form]': form, '[data-preview-tabs]': tabs })[selector],
        querySelectorAll: selector => selector === '[data-preview-panel]' ? [searchPanel, socialPanel] : [],
    };
    globalThis.FormData = class {
        *[Symbol.iterator]() { yield ['title', 'Approved title']; }
    };
    try {
        mountAdmin(doc, node());
        assert.equal(tabs.hidden, false);
        assert.equal(tabs.attributes.role, 'tablist');
        assert.equal(search.attributes['aria-selected'], 'true');
        assert.equal(searchPanel.hidden, false);
        assert.equal(socialPanel.hidden, true);
        let prevented = 0;
        search.events.keydown({ key: 'ArrowRight', preventDefault() { prevented++; } });
        assert.equal(social.focused, true);
        assert.equal(social.tabIndex, 0);
        assert.equal(search.tabIndex, -1);
        assert.equal(social.attributes['aria-selected'], 'true');
        assert.equal(socialPanel.hidden, false);
        assert.equal(searchPanel.hidden, true);
        assert.equal(socialPanel.attributes['aria-labelledby'], 'social-preview-tab');
        social.events.keydown({ key: 'Home', preventDefault() { prevented++; } });
        assert.equal(search.attributes['aria-selected'], 'true');
        social.events.click();
        assert.equal(socialPanel.hidden, false);
        assert.equal(prevented, 2);
    } finally {
        globalThis.FormData = OriginalFormData;
    }
});


test('overview tabs work before the editor form, retain hash destinations and support keyboard navigation', () => {
    const list = node(), tabs = [node(), node(), node()], panels = [node(), node(), node()];
    const keys = ['pages', 'images', 'review'];
    keys.forEach((key, index) => {
        tabs[index].dataset.overviewTab = key;
        panels[index].dataset.overviewPanel = key;
        panels[index].id = ['pages-panel', 'image-library', 'seo-review'][index];
        panels[index].contains = () => false;
    });
    list.querySelectorAll = () => tabs;
    const win = node();
    win.location = { hash: '#image-library' };
    win.history = { replaceState(a, b, value) { win.location.hash = value; } };
    const doc = {
        body: node(),
        querySelector: selector => selector === '[data-overview-tabs]' ? list : null,
        querySelectorAll: selector => selector === '[data-overview-panel]' ? panels : [],
        getElementById: id => panels.find(panel => panel.id === id),
    };
    mountAdmin(doc, win);
    assert.equal(list.hidden, false);
    assert.equal(tabs[1].attributes['aria-selected'], 'true');
    assert.equal(panels[0].hidden, true);
    assert.equal(panels[1].hidden, false);
    tabs[1].events.keydown({ key: 'ArrowRight', preventDefault() {} });
    assert.equal(tabs[2].focused, true);
    assert.equal(win.location.hash, '#seo-review');
    tabs[0].events.click();
    assert.equal(panels[0].hidden, false);
    assert.equal(win.location.hash, '#pages-panel');
    win.location.hash = '#image-library';
    win.events.hashchange();
    assert.equal(panels[1].hidden, false);
    assert.equal(tabs[1].tabIndex, 0);
    assert.equal(panels[1].attributes['aria-labelledby'], 'images-overview-tab');
});


test('upload errors reopen after redirect loading, keep values and restore focus on cancellation', () => {
    const dialog = node(), opener = node(), cancel = node(), content = node();
    const label = node(), submit = node(), validation = node(), fallback = node();
    label.value = 'Existing label';
    dialog.hasAttribute = name => name === 'data-upload-errors';
    dialog.appendChild = child => { dialog.child = child; };
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; dialog.events.close(); };
    dialog.querySelector = selector => selector === '[data-upload-validation]' ? validation : label;
    dialog.querySelectorAll = () => [label, submit];
    const doc = {
        body: node(),
        readyState: 'loading',
        querySelector: selector => ({
            '[data-upload-dialog]': dialog,
            '[data-upload-open]': opener,
            '[data-upload-cancel]': cancel,
            '[data-upload-content]': content,
            '[data-upload-fallback]': fallback,
        })[selector],
    };
    const win = node();
    win.requestAnimationFrame = callback => callback();
    mountAdmin(doc, win);
    assert.equal(dialog.open, undefined);
    assert.equal(fallback.hidden, true);
    assert.equal(opener.hidden, false);
    assert.equal(dialog.child, content);
    win.events.load();
    assert.equal(dialog.open, true);
    assert.equal(validation.focused, true);
    doc.activeElement = label;
    let prevented = false;
    dialog.events.keydown({ key: 'Tab', shiftKey: true, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(submit.focused, true);
    cancel.events.click();
    assert.equal(dialog.open, false);
    assert.equal(opener.focused, true);
    assert.equal(label.value, 'Existing label');
});


function submissionFixture(preview = false) {
    const button = node(), file = node(), form = node(), win = node(), doc = node();
    const tasks = [], notifications = [];
    button.type = 'submit';
    button.formTarget = preview ? '_blank' : '';
    button.disabled = false;
    file.type = 'file';
    file.disabled = false;
    form.tagName = 'FORM';
    form.method = 'post';
    form.elements = [file, button];
    form.target = '';
    doc.querySelector = () => null;
    win.location = { origin: 'https://local.test' };
    win.queueMicrotask = callback => tasks.push(callback);
    win.setTimeout = callback => { win.timeout = callback; return 1; };
    win.clearTimeout = () => {};
    win.showLoader = control => { win.loads = (win.loads || 0) + 1; control.disabled = true; };
    win.hideLoader = control => { win.resets = (win.resets || 0) + 1; control.disabled = false; };
    win.showNotification = notification => notifications.push(notification);
    mountSubmissionFeedback(doc, win);
    const submit = () => {
        const event = {
            target: form, submitter: button, defaultPrevented: false,
            preventDefault() { this.defaultPrevented = true; },
        };
        doc.events.submit(event);
        return event;
    };
    return {button, file, form, doc, win, tasks, notifications, submit};
}

test('native submissions reuse the shared loader, block repeats and recover on history restoration', () => {
    const f = submissionFixture();
    f.submit();
    assert.equal(f.submit().defaultPrevented, true);
    f.tasks.shift()();
    assert.equal(f.win.loads, 1);
    assert.equal(f.button.disabled, true);
    assert.equal(f.file.disabled, false);
    assert.equal(f.form.getAttribute('aria-busy'), 'true');
    assert.equal(f.notifications.length, 0);
    f.win.events.pageshow();
    assert.equal(f.button.disabled, false);
    assert.equal(f.form.hasAttribute('aria-busy'), false);
});

test('cancelled submissions never start loading or announce success', () => {
    const f = submissionFixture();
    const event = f.submit();
    event.preventDefault();
    f.tasks.shift()();
    assert.equal(f.win.loads || 0, 0);
    assert.equal(f.button.disabled, false);
    assert.equal(f.notifications.length, 0);
    assert.equal(f.submit().defaultPrevented, false);
});

test('preview feedback uses a same-origin channel without needing an opener and has a labelled timeout', () => {
    const f = submissionFixture(true);
    f.win.BroadcastChannel = class {
        constructor(name) { f.win.channelName = name; }
        addEventListener(type, callback) { f.win.channelMessage = callback; }
        close() {}
    };
    f.submit();
    f.tasks.shift()();
    f.win.channelMessage({data: {type: 'unrelated'}});
    assert.equal(f.button.disabled, true);
    f.win.channelMessage({data: {type: 'ready'}});
    assert.equal(f.win.channelName, 'dd:admin-preview');
    assert.equal(f.button.disabled, false);
    f.submit();
    f.tasks.shift()();
    f.win.timeout();
    assert.equal(f.button.disabled, false);
    assert.equal(f.notifications[0].type, 'info');
    assert.equal(f.notifications[0].title, 'Check the preview tab');
});
