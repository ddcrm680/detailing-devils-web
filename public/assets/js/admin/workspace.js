import '../common/dom.js';

/** UI only: no persistence, API calls or publication decisions live here. */
export function fieldSnapshot(entries) {
    return JSON.stringify(
        Array.from(entries, ([name, value]) => [
            name,
            typeof value === 'string' ? value : value.name,
        ])
    );
}

export function previewCopy(fields) {
    const title = fields.title || '';
    const description = fields.description || '';
    return {
        title,
        description,
        socialTitle: fields.og_title || title,
        socialDescription: fields.og_description || description,
    };
}

export function mountAdmin(doc = document, win = window) {
    if (doc.body.dataset.adminMounted) return;
    doc.body.dataset.adminMounted = 'true';
    doc.body.classList.add('js-admin');
    const $ = win.$;
    const $$ = win.$$;
    const toggle = $('[data-nav-toggle]', doc);
    const sidebar = $('#admin-sidebar', doc);
    const backdrop = $('[data-menu-backdrop]', doc);
    if (toggle && sidebar && backdrop) {
        const mobile = win.matchMedia('(max-width: 900px)');
        let open = false;
        const update = () => {
            const expanded = mobile.matches && open;
            sidebar.classList.toggle('is-open', expanded);
            sidebar.inert = mobile.matches && !expanded;
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded ? 'Close navigation' : 'Open navigation');
            backdrop.hidden = !expanded;
        };
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            open = !open;
            update();
            if (open) $('a', sidebar)?.focus();
        });
        const close = () => {
            open = false;
            update();
            toggle.focus();
        };
        backdrop.addEventListener('click', close);
        doc.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && open) close();
        });
        sidebar.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !mobile.matches || !open) return;
            const links = Array.from($$('a, button', sidebar));
            if (event.shiftKey && doc.activeElement === links[0]) {
                event.preventDefault();
                links.at(-1)?.focus();
            } else if (!event.shiftKey && doc.activeElement === links.at(-1)) {
                event.preventDefault();
                links[0]?.focus();
            }
        });
        mobile.addEventListener('change', () => {
            open = false;
            update();
        });
        update();
    }
    const form = $('[data-draft-form]', doc);
    if (!form) return;
    const initial = fieldSnapshot(new FormData(form));
    let dirty = false;
    let saving = false;
    const update = () => {
        dirty = fieldSnapshot(new FormData(form)) !== initial;
        const state = $('[data-save-state]', doc);
        if (state) {
            state.hidden = false;
            state.textContent = dirty ? 'Unsaved changes' : 'No unsaved changes';
            state.classList.toggle('is-dirty', dirty);
        }
        $$('[data-unsaved-notice]', doc).forEach((node) => {
            node.hidden = !dirty;
        });
        const values = Object.fromEntries(new FormData(form));
        const copy = previewCopy(values);
        for (const [selector, value] of Object.entries({
            '[data-search-title]': copy.title,
            '[data-search-description]': copy.description,
            '[data-social-title]': copy.socialTitle,
            '[data-social-description]': copy.socialDescription,
        })) {
            const node = $(selector, doc);
            if (node) node.textContent = value;
        }
        $$('[data-count-for]', doc).forEach((node) => {
            const input = form.elements.namedItem(node.dataset.countFor);
            if (input) node.textContent = `${input.value.length} / ${input.maxLength} characters`;
        });
        const source = form.elements.namedItem('image')?.selectedOptions[0]?.dataset.previewSource;
        const image = $('[data-social-image]', doc);
        const placeholder = $('[data-image-placeholder]', doc);
        if (image && placeholder) {
            image.hidden = !source;
            placeholder.hidden = !!source;
            if (source) {
                if (image.getAttribute('src') !== source) image.src = source;
            } else {
                image.removeAttribute('src');
            }
        }
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', (event) => {
        // Preview opens a new tab and does not save the current editor.
        if ((event.submitter?.formTarget || form.target) !== '_blank') saving = true;
    });
    win.addEventListener('beforeunload', (event) => {
        if (!dirty || saving) return;
        event.preventDefault();
        event.returnValue = '';
    });
    update();
}

if (typeof document !== 'undefined') mountAdmin();
