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

export function previewCopy(fields, platform = 'og') {
    const title = fields.title || '';
    const description = fields.description || '';
    return {
        title,
        description,
        socialTitle:
            (platform === 'twitter' && fields.twitter_title) || fields.og_title || title,
        socialDescription:
            (platform === 'twitter' && fields.twitter_description)
            || fields.og_description
            || description,
    };
}

export function confirmationCopy(action) {
    return action === 'restore'
        ? {
            title: 'Restore This Version?',
            description: 'This will publish the saved values as a new revision. Previous versions are kept.',
            button: 'Restore',
        }
        : {
            title: 'Publish Changes?',
            description: 'This will update the live SEO for this page using the saved draft.',
            button: 'Publish',
        };
}

export function mountSubmissionFeedback(doc, win) {
    const $ = win.$;
    const active = new Map();
    let previewChannel;
    const connectPreview = () => {
        if (!win.BroadcastChannel || previewChannel) return;
        previewChannel = new win.BroadcastChannel('dd:admin-preview');
        previewChannel.addEventListener('message', (event) => {
            if (event.data?.type !== 'ready') return;
            Array.from(active).forEach(([form, state]) => { if (state.preview) reset(form); });
        });
    };
    const reset = (form) => {
        const state = active.get(form);
        if (!state) return;
        win.clearTimeout(state.timer);
        win.hideLoader?.(state.button);
        state.buttons.forEach(([button, disabled]) => { button.disabled = disabled; });
        state.button?.removeAttribute('aria-busy');
        form.removeAttribute('aria-busy');
        active.delete(form);
    };
    win.addEventListener('pageshow', () => Array.from(active.keys()).forEach(reset));
    win.addEventListener('pagehide', () => {
        Array.from(active.keys()).forEach(reset);
        previewChannel?.close();
        previewChannel = null;
    });
    win.addEventListener('focus', () => {
        Array.from(active).forEach(([form, state]) => { if (state.preview) reset(form); });
    });
    doc.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.tagName !== 'FORM' || form.method.toLowerCase() !== 'post' || event.defaultPrevented) return;
        if (active.has(form)) {
            event.preventDefault();
            return;
        }
        const button = event.submitter || $('button[type="submit"]', form);
        const preview = (event.submitter?.formTarget || form.target) === '_blank';
        if (preview) connectPreview();
        const state = { button, buttons: [], preview };
        active.set(form, state);
        // Keep native form serialization and all fields intact before disabling buttons.
        win.queueMicrotask(() => {
            if (event.defaultPrevented) {
                reset(form);
                return;
            }
            state.buttons = Array.from(form.elements)
                .filter((control) => control.type === 'submit' || control.hasAttribute('data-upload-cancel'))
                .map((control) => [control, control.disabled]);
            win.showLoader?.(button, preview ? 'Opening preview…' : 'Please wait…');
            state.buttons.forEach(([control]) => { control.disabled = true; });
            form.setAttribute('aria-busy', 'true');
            button?.setAttribute('aria-busy', 'true');
            if (preview) {
                state.timer = win.setTimeout(() => {
                    reset(form);
                    win.showNotification?.({
                        type: 'info', title: 'Check the preview tab',
                        html: 'The preview has not reported back yet. Check its tab before trying again.',
                    });
                }, 30000);
            }
        });
    });
    if ($('[data-preview-response], [data-admin-failure], [data-validation-errors]', doc)) {
        // Native _blank previews retain noopener; this same-origin channel carries no page data.
        const reportPreview = () => {
            connectPreview();
            previewChannel?.postMessage({ type: 'ready' });
            previewChannel?.close();
            previewChannel = null;
        };
        if (doc.readyState === 'complete') reportPreview();
        else win.addEventListener('load', reportPreview, { once: true });
    }
    const success = $('[data-admin-notice="success"]', doc);
    const error = $('[data-admin-notice="error"]', doc);
    const failure = $('[data-admin-failure]', doc);
    if (success) win.showNotification?.({ type: 'success', title: 'Success', html: success.textContent.trim() });
    else if (failure) win.showNotification?.({
        type: 'error', title: $('h1', failure).textContent,
        html: $('p', failure).textContent,
    });
    else if (error && !$('[data-upload-errors]', doc)) win.showNotification?.({
        type: 'error', title: 'Please review the highlighted fields',
        html: 'Your changes have not been saved. Review the inline messages before trying again.',
    });
}

export function mountAdmin(doc = document, win = window) {
    if (doc.body.dataset.adminMounted) return;
    doc.body.dataset.adminMounted = 'true';
    doc.body.classList.add('js-admin');
    const $ = win.$;
    const $$ = win.$$;
    if (doc.addEventListener && doc.querySelectorAll) mountSubmissionFeedback(doc, win);
    const toggle = $('[data-nav-toggle]', doc);
    const sidebar = $('#admin-sidebar', doc);
    const backdrop = $('[data-menu-backdrop]', doc);
    const filters = $('[data-filter-panel]', doc);
    if (filters) {
        const desktopFilters = win.matchMedia('(min-width: 1024px)');
        const syncFilters = () => {
            const focusedInside = filters.contains(doc.activeElement);
            filters.open = desktopFilters.matches;
            if (!desktopFilters.matches && focusedInside) filters.querySelector('summary').focus();
        };
        desktopFilters.addEventListener('change', syncFilters);
        syncFilters();
    }
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
    for (const scope of ['preview', 'overview']) {
        const tabList = $(`[data-${scope}-tabs]`, doc);
        if (!tabList) continue;
        const tabs = Array.from($$(`[data-${scope}-tab]`, tabList));
        const panels = Array.from($$(`[data-${scope}-panel]`, doc));
        const selectTab = (selected, updateHash = false) => {
            const key = selected.dataset[`${scope}Tab`];
            tabs.forEach((tab) => {
                const active = tab === selected;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset[`${scope}Panel`] !== key;
                if (!panel.hidden && updateHash && scope === 'overview') {
                    win.history.replaceState(null, '', `#${panel.id}`);
                }
            });
        };
        tabList.setAttribute('role', 'tablist');
        tabs.forEach((tab, index) => {
            tab.setAttribute('role', 'tab');
            tab.addEventListener('click', () => selectTab(tab, true));
            tab.addEventListener('keydown', (event) => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
                else if (event.key === 'Home') next = 0;
                else if (event.key === 'End') next = tabs.length - 1;
                else return;
                event.preventDefault();
                selectTab(tabs[next], true);
                tabs[next].focus();
            });
        });
        panels.forEach((panel) => {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', `${panel.dataset[`${scope}Panel`]}-${scope}-tab`);
            panel.tabIndex = 0;
        });
        const syncHash = () => {
            const target = win.location.hash ? doc.getElementById(win.location.hash.slice(1)) : null;
            const panel = panels.find((item) => item === target || (target && item.contains(target)));
            selectTab(tabs.find((tab) => tab.dataset.overviewTab === panel?.dataset.overviewPanel) || tabs[0]);
        };
        if (scope === 'overview') {
            win.addEventListener('hashchange', syncHash);
            syncHash();
            if ($('[data-upload-errors], [data-library-errors]', doc)) {
                selectTab(tabs.find((tab) => tab.dataset.overviewTab === 'images'));
            }
        } else selectTab(tabs[0]);
        tabList.hidden = false;
    }
    const uploadDialog = $('[data-upload-dialog]', doc);
    if (uploadDialog && typeof uploadDialog.showModal === 'function') {
        const openButton = $('[data-upload-open]', doc);
        const cancelButton = $('[data-upload-cancel]', doc);
        const content = $('[data-upload-content]', doc);
        uploadDialog.appendChild(content);
        $('[data-upload-fallback]', doc).hidden = true;
        openButton.hidden = false;
        cancelButton.hidden = false;
        const openUpload = () => {
            uploadDialog.showModal();
            ($('[data-upload-validation]', uploadDialog) || $('#label', uploadDialog)).focus();
        };
        openButton.addEventListener('click', openUpload);
        cancelButton.addEventListener('click', () => uploadDialog.close());
        uploadDialog.addEventListener('cancel', (event) => {
            if ($('[data-upload-form]', uploadDialog).getAttribute('aria-busy') === 'true') event.preventDefault();
        });
        uploadDialog.addEventListener('close', () => openButton.focus());
        uploadDialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;
            const controls = Array.from($$(
                'input:not([type="hidden"]):not([disabled]), button:not([disabled])',
                uploadDialog
            ));
            const atStart = doc.activeElement === controls[0]
                || doc.activeElement === $('[data-upload-validation]', uploadDialog);
            if (event.shiftKey && atStart) {
                event.preventDefault();
                controls.at(-1)?.focus();
            } else if (!event.shiftKey && doc.activeElement === controls.at(-1)) {
                event.preventDefault();
                controls[0]?.focus();
            }
        });
        if (uploadDialog.hasAttribute('data-upload-errors')) {
            // Let the redirect's fragment navigation finish before focusing the errors.
            const reopenUpload = () => win.requestAnimationFrame(openUpload);
            if (doc.readyState === 'complete') reopenUpload();
            else win.addEventListener('load', reopenUpload, { once: true });
        }
    }
    const form = $('[data-draft-form]', doc);
    if (!form) return;
    const searchPreview = $('.search-preview', doc);
    const pageDetails = $('.page-details', doc);
    if (searchPreview && pageDetails) {
        const originalParent = searchPreview.parentElement;
        const originalNext = searchPreview.nextSibling;
        const phonePreview = win.matchMedia('(max-width: 640px)');
        const placePreview = () => {
            if (phonePreview.matches) pageDetails.appendChild(searchPreview);
            else originalParent.insertBefore(searchPreview, originalNext);
        };
        phonePreview.addEventListener('change', placePreview);
        placePreview();
    }
    const accepted = new WeakSet();
    const confirming = new WeakSet();
    $$('form[data-confirm]', doc).forEach((target) => {
        target.addEventListener('submit', async (event) => {
            if (accepted.delete(target)) return;
            event.preventDefault();
            if (confirming.has(target) || target.getAttribute('aria-busy') === 'true' || !target.reportValidity()) return;
            const trigger = event.submitter;
            const copy = confirmationCopy(target.dataset.confirm);
            confirming.add(target);
            try {
                const confirmed = win.Swal
                    ? (await win.Swal.fire({
                        titleText: copy.title,
                        text: copy.description,
                        icon: target.dataset.confirm === 'restore' ? 'warning' : 'question',
                        showCancelButton: true,
                        confirmButtonText: copy.button,
                        cancelButtonText: 'Cancel',
                        focusCancel: true,
                        buttonsStyling: false,
                        customClass: { confirmButton: 'button', cancelButton: 'button secondary' },
                    })).isConfirmed
                    : win.confirm(`${copy.title} ${copy.description}`);
                if (!confirmed) return;
                accepted.add(target);
                target.requestSubmit(trigger);
                accepted.delete(target);
            } finally {
                confirming.delete(target);
                if (!target.hasAttribute('aria-busy')) trigger?.focus();
            }
        });
    });
    $$('[data-open-panel]', doc).forEach((link) => {
        link.addEventListener('click', () => {
            const panel = doc.getElementById(link.dataset.openPanel);
            if (!panel) return;
            panel.open = true;
            panel.querySelector('input:not([type="hidden"]), button')?.focus({ preventScroll: true });
        });
    });
    form.addEventListener('invalid', (event) => {
        let details = event.target.closest('details');
        while (details) {
            details.open = true;
            details = details.parentElement.closest('details');
        }
    }, true);
    const initial = fieldSnapshot(new FormData(form));
    let dirty = false;
    let saving = false;
    let socialPlatform = 'og';
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
        const copy = previewCopy(values, socialPlatform);
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
    $$('[data-social-platform]', doc).forEach((button) => {
        button.addEventListener('click', () => {
            socialPlatform = button.dataset.socialPlatform;
            $$('[data-social-platform]', doc).forEach((item) => {
                item.setAttribute('aria-pressed', String(item === button));
            });
            update();
        });
    });
    form.addEventListener('submit', (event) => {
        // Preview opens a new tab and does not save the current editor.
        queueMicrotask(() => {
            if (!event.defaultPrevented && (event.submitter?.formTarget || form.target) !== '_blank') saving = true;
        });
    });
    win.addEventListener('beforeunload', (event) => {
        if (!dirty || saving) return;
        event.preventDefault();
        event.returnValue = '';
    });
    update();
}

if (typeof document !== 'undefined') mountAdmin();
