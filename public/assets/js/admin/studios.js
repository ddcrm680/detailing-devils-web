import { fieldSnapshot } from './workspace.js';

export function updatePhoneRows(list) {
    const rows = Array.from(list.querySelectorAll('[data-phone-row]'));
    rows.forEach((row, index) => {
        const input = row.querySelector('input');
        const label = row.querySelector('[data-phone-label]');
        const error = row.querySelector('[data-phone-error]');
        input.name = `phones[${index}]`;
        input.id = `studio-phone-${index}`;
        label.htmlFor = input.id;
        label.textContent = `Phone ${index + 1}${index === 0 ? ' · Primary' : ''}`;
        if (error) {
            error.id = `phone-error-${index}`;
            input.setAttribute('aria-describedby', error.id);
        }
        row.querySelector('[data-phone-actions]').hidden = false;
        row.querySelector('[data-phone-move="-1"]').disabled = index === 0;
        row.querySelector('[data-phone-move="1"]').disabled = index === rows.length - 1;
        row.querySelector('[data-phone-remove]').disabled = rows.length === 1;
    });
    return rows.length;
}

export function mountGalleryUpload(form, doc = document, win = window) {
    const files = form.querySelector('[data-gallery-files]');
    const previews = form.querySelector('[data-gallery-previews]');
    const errors = form.querySelector('#upload-errors');
    const count = form.querySelector('[data-selected-count]');
    const dropzone = form.querySelector('[data-gallery-dropzone]');
    const button = form.querySelector('[type="submit"]');
    const hasCover = form.dataset.hasCover === 'true';
    let queue = [];
    let cover = null;
    let busy = false;
    let controls = [];
    const clearErrors = () => {
        errors.replaceChildren();
        previews.querySelectorAll('.invalid-feedback').forEach((node) => node.remove());
        previews.querySelectorAll('[aria-invalid]').forEach((node) => {
            node.removeAttribute('aria-invalid');
            node.removeAttribute('aria-describedby');
        });
    };
    const render = () => {
        previews.replaceChildren();
        const transfer = new win.DataTransfer();
        queue.forEach((entry, index) => {
            transfer.items.add(entry.file);
            const card = doc.createElement('article');
            card.className = 'studio-selected-image';
            card.dataset.pendingIndex = index;
            const image = doc.createElement('img');
            if (entry.url) image.src = entry.url;
            entry.preview = image;
            image.alt = entry.file.name;
            const name = doc.createElement('span');
            name.className = 'small studio-photo-filename';
            name.title = entry.file.name;
            name.textContent = `${index + 1}. ${entry.file.name}`;
            const label = doc.createElement('label');
            label.htmlFor = `pending-description-${index}`;
            label.textContent = 'Photo Description';
            label.className = 'sr-only';
            const input = doc.createElement('input');
            input.id = label.htmlFor;
            input.name = `descriptions[${index}]`;
            input.maxLength = 255;
            input.placeholder = 'Describe this photo';
            input.value = entry.description;
            input.addEventListener('input', () => { entry.description = input.value; });
            const choice = doc.createElement('label');
            choice.className = 'check';
            const radio = doc.createElement('input');
            radio.type = 'radio';
            radio.name = 'cover_index';
            radio.value = index;
            radio.checked = cover === entry || (!hasCover && cover === null && index === 0);
            radio.addEventListener('change', () => { cover = entry; render(); previews.querySelector(`[value="${index}"][name="cover_index"]`).focus(); });
            choice.append(radio, doc.createTextNode(radio.checked ? 'Cover Photo' : 'Set as Cover'));
            const actions = doc.createElement('div');
            actions.className = 'studio-pending-actions';
            const action = (text, disabled, handler) => {
                const control = doc.createElement('button');
                control.type = 'button';
                control.className = 'button secondary';
                control.setAttribute('aria-label', text);
                control.title = text;
                if (text === 'Move Up' || text === 'Move Down' || text === 'Remove') {
                    const icon = doc.createElement('i');
                    icon.className = text === 'Remove' ? 'bi bi-trash3' : `bi bi-arrow-${text === 'Move Up' ? 'up' : 'down'}`;
                    icon.setAttribute('aria-hidden', 'true');
                    control.appendChild(icon);
                } else control.textContent = text;
                control.disabled = disabled;
                control.addEventListener('click', handler);
                actions.appendChild(control);
            };
            const move = (offset) => {
                [queue[index], queue[index + offset]] = [queue[index + offset], queue[index]];
                clearErrors(); render();
                previews.children[index + offset].querySelector('input').focus();
            };
            action('Move Up', index === 0, () => move(-1));
            action('Move Down', index === queue.length - 1, () => move(1));
            action('Remove', false, () => {
                queue.splice(index, 1);
                if (cover === entry) cover = null;
                clearErrors(); render();
                (previews.children[Math.min(index, queue.length - 1)]?.querySelector('input') || files).focus();
            });
            card.append(image, name, label, input, choice, actions);
            previews.appendChild(card);
        });
        files.files = transfer.files;
        count.textContent = `${queue.length} ${queue.length === 1 ? 'photo' : 'photos'} selected`;
    };
    const select = (selected) => {
        if (busy) return;
        clearErrors();
        if (queue.length + selected.length > 10) {
            errors.textContent = 'Select up to 10 photos per upload.';
            render();
            return;
        }
        for (const file of selected) {
            const entry = { file, description: '', url: null };
            queue.push(entry);
            if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024) {
                const reader = new win.FileReader();
                reader.addEventListener('load', () => {
                    entry.url = reader.result;
                    if (entry.preview) entry.preview.src = entry.url;
                });
                reader.readAsDataURL(file);
            }
        }
        render();
    };
    files.addEventListener('change', () => select(Array.from(files.files)));
    dropzone.querySelector('[data-drop-help]').hidden = false;
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        if (!busy) dropzone.classList.add('is-dragging');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        if (event.dataTransfer?.files.length) select(Array.from(event.dataTransfer.files));
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy) return;
        clearErrors();
        if (!queue.length) {
            errors.textContent = 'Please select at least one photo.';
            files.focus();
            return;
        }
        const body = new FormData(form);
        if (cover === null) body.delete('cover_index');
        busy = true;
        win.showLoader?.(button, 'Uploading?');
        controls = Array.from(form.elements).map((node) => [node, node.disabled]);
        controls.forEach(([node]) => { node.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        let completed = false;
        try {
            const response = await win.fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.redirect) {
                completed = true;
                win.location.assign(data.redirect);
                return;
            }
            if (response.status === 422 && data.errors) {
                for (const [field, messages] of Object.entries(data.errors)) {
                    const match = /^(?:images|descriptions)\.(\d+)$/.exec(field);
                    const card = match && previews.children[Number(match[1])];
                    const message = doc.createElement('span');
                    message.className = 'invalid-feedback';
                    message.textContent = messages.join(' ');
                    if (card) {
                        message.id = `pending-error-${field.replace('.', '-')}`;
                        card.appendChild(message);
                        if (field.startsWith('descriptions.')) {
                            const input = card.querySelector('input');
                            input.setAttribute('aria-invalid', 'true');
                            input.setAttribute('aria-describedby', message.id);
                        }
                    } else errors.appendChild(message);
                }
                if (!errors.textContent) errors.textContent = 'Please check the highlighted photos.';
            } else {
                errors.textContent = response.status === 419 ? 'Your session expired. Refresh the page before uploading.' : 'Photos could not be uploaded. Please try again.';
                win.showNotification?.({ type: 'error', title: errors.textContent });
            }
            errors.focus();
        } catch {
            errors.textContent = 'Connection interrupted. Check the gallery before retrying to avoid uploading twice.';
            win.showNotification?.({ type: 'error', title: 'Upload could not be confirmed.' });
            errors.focus();
        } finally {
            if (!completed) {
                controls.forEach(([node, disabled]) => { node.disabled = disabled; });
                win.hideLoader?.(button);
                busy = false;
                form.removeAttribute('aria-busy');
            }
        }
    });
    win.addEventListener('pageshow', () => {
        if (!busy) return;
        busy = false;
        form.removeAttribute('aria-busy');
        controls.forEach(([node, disabled]) => { node.disabled = disabled; });
        win.hideLoader?.(button);
        render();
    });
}

export function generatedStudioSlug(name, existing = []) {
    const base = name.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/_/g, '-').replace(/@/g, '-at-').replace(/[^a-z0-9\s-]/g, '')
        .replace(/[-\s]+/g, '-').replace(/^-|-$/g, '')
        .slice(0, 180).replace(/-$/, '') || 'studio';
    const used = new Set(existing);
    let slug = base;
    for (let suffix = 2; used.has(slug); suffix++) slug = `${base}-${suffix}`;
    return slug;
}

export function mountStudioSlug(form) {
    const field = form.querySelector('[data-slug-editor]');
    if (!field) return;
    const name = form.querySelector('[name="name"]');
    const input = field.querySelector('[name="slug"]');
    const mode = field.querySelector('[name="slug_mode"]');
    const preview = field.querySelector('[data-slug-preview]');
    const output = field.querySelector('[data-slug-output]');
    const edit = field.querySelector('[data-slug-edit]');
    const existing = JSON.parse(field.dataset.slugs);
    const update = () => {
        if (field.dataset.new === 'true' && mode.value === 'auto') input.value = generatedStudioSlug(name.value, existing);
        output.textContent = input.value;
    };
    if (mode.value !== 'manual') {
        input.hidden = true;
        preview.hidden = false;
    }
    edit.addEventListener('click', () => {
        mode.value = 'manual';
        preview.hidden = true;
        input.hidden = false;
        input.focus();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    name.addEventListener('input', update);
    update();
}

export function photoSnapshot(values) {
    const order = String(values.sort_order ?? '');
    return fieldSnapshot([
        ['alt_text', values.alt_text ?? ''],
        ['sort_order', order !== '' && Number.isFinite(Number(order)) ? String(Number(order)) : order],
        ['is_cover', values.is_cover ? '1' : '0'],
    ]);
}

export function mountPhotoSave(form, doc = document, win = window) {
    const button = doc.querySelector(`[data-photo-save="${form.id}"]`);
    const original = JSON.parse(form.dataset.photoOriginal);
    const baseline = photoSnapshot(original);
    let saving = false;
    const dirty = () => photoSnapshot({
        alt_text: form.elements.alt_text.value,
        sort_order: form.elements.sort_order.value,
        is_cover: form.elements.is_cover ? form.elements.is_cover.checked : original.is_cover,
    }) !== baseline;
    const update = () => { button.disabled = saving || !dirty(); };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', (event) => {
        if (saving || !dirty()) {
            event.preventDefault();
            return;
        }
        win.queueMicrotask(() => { saving = !event.defaultPrevented; });
    });
    win.addEventListener('pageshow', () => { saving = false; update(); });
    update();
}

export function mountStudioSlider(doc = document, win = window) {
    const stage = doc.querySelector('[data-studio-slider]');
    const photos = Array.from(doc.querySelectorAll('[data-studio-photo]'));
    if (!stage || photos.length < 2 || !win.Glide) return;
    const motion = win.matchMedia('(prefers-reduced-motion: reduce)');
    const media = stage.closest('.studio-preview-media');
    const toggle = media.querySelector('[data-photo-play]');
    let paused = false;
    let hovered = false;
    let suspended = false;
    const glide = new win.Glide(stage, {
        type: 'carousel', perView: 1, gap: 0, autoplay: false, hoverpause: false,
        keyboard: false, perTouch: 1, rewind: true,
        animationDuration: motion.matches ? 0 : 450,
        animationTimingFunc: 'cubic-bezier(.25,.1,.25,1)',
    });
    const playback = () => {
        if (paused || hovered || suspended || motion.matches || doc.hidden || media.contains(doc.activeElement)) glide.pause();
        else glide.play(4500);
        toggle.hidden = motion.matches;
        toggle.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
        toggle.title = paused ? 'Play slideshow' : 'Pause slideshow';
        toggle.querySelector('i').className = paused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
    };
    const update = () => {
        photos.forEach((photo, index) => photo.setAttribute('aria-pressed', String(index === glide.index)));
        stage.querySelectorAll('.glide__slide:not(.glide__slide--clone)').forEach((slide, index) => slide.setAttribute('aria-hidden', String(index !== glide.index)));
        stage.querySelectorAll('.glide__slide--clone').forEach((slide) => slide.setAttribute('aria-hidden', 'true'));
    };
    glide.on(['mount.after', 'run.after'], update);
    glide.mount();
    playback();
    photos.forEach((photo, index) => photo.addEventListener('click', () => glide.go(`=${index}`)));
    stage.querySelectorAll('[data-photo-step]').forEach((arrow) => {
        arrow.hidden = false;
        arrow.addEventListener('click', () => glide.go(Number(arrow.dataset.photoStep) > 0 ? '>' : '<'));
    });
    media.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        glide.go(event.key === 'ArrowRight' ? '>' : '<');
    });
    toggle.addEventListener('click', () => { paused = !paused; playback(); });
    media.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'touch') return;
        hovered = true;
        playback();
    });
    media.addEventListener('pointerleave', () => { hovered = false; playback(); });
    media.addEventListener('focusin', playback);
    media.addEventListener('focusout', () => win.queueMicrotask(playback));
    doc.addEventListener('visibilitychange', playback);
    win.addEventListener('pagehide', () => { suspended = true; playback(); });
    win.addEventListener('pageshow', () => { suspended = false; playback(); });
    motion.addEventListener('change', () => {
        glide.update({ animationDuration: motion.matches ? 0 : 450 });
        playback();
    });
}

export function mountStudios(doc = document, win = window) {
    const list = doc.querySelector('[data-phone-list]');
    const add = doc.querySelector('[data-phone-add]');
    if (list && add) {
        add.hidden = false;
        const update = () => {
            add.disabled = updatePhoneRows(list) >= 10;
            list.dispatchEvent(new Event('change', { bubbles: true }));
        };
        updatePhoneRows(list);
        add.addEventListener('click', () => {
            if (list.children.length >= 10) return;
            const row = list.firstElementChild.cloneNode(true);
            row.querySelector('input').value = '';
            row.querySelector('input').removeAttribute('aria-invalid');
            row.querySelector('input').removeAttribute('aria-describedby');
            row.querySelector('[data-phone-error]')?.remove();
            list.appendChild(row);
            update();
            row.querySelector('input').focus();
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            const row = button?.closest('[data-phone-row]');
            if (!row) return;
            if (button.hasAttribute('data-phone-remove') && list.children.length > 1) row.remove();
            else if (button.dataset.phoneMove === '-1' && row.previousElementSibling) {
                list.insertBefore(row, row.previousElementSibling);
            } else if (button.dataset.phoneMove === '1' && row.nextElementSibling) {
                list.insertBefore(row.nextElementSibling, row);
            }
            update();
        });
    }
    mountStudioSlider(doc, win);
    doc.querySelectorAll('[data-photo-form]').forEach((form) => mountPhotoSave(form, doc, win));
    const upload = doc.querySelector('[data-gallery-upload]');
    if (upload && win.DataTransfer && win.fetch) mountGalleryUpload(upload, doc, win);
    doc.querySelectorAll('[data-studio-remove]').forEach((form) => {
        let accepted = false;
        let confirming = false;
        form.addEventListener('submit', async (event) => {
            if (accepted) return;
            event.preventDefault();
            if (confirming) return;
            confirming = true;
            try {
                accepted = win.Swal
                    ? (await win.Swal.fire({
                        titleText: 'Remove this photo?',
                        text: 'This photo will be permanently deleted.',
                        icon: 'warning',
                        showCancelButton: true,
                        focusCancel: true,
                        confirmButtonText: 'Remove Photo',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'button', cancelButton: 'button secondary' },
                    })).isConfirmed
                    : win.confirm('Permanently delete this photo?');
                if (accepted) form.requestSubmit(event.submitter);
            } finally {
                confirming = false;
                accepted = false;
            }
        });
    });
    const form = doc.querySelector('[data-studio-form]');
    if (form) {
        mountStudioSlug(form);
        const panels = form.querySelectorAll('[data-studio-panel]');
        const compact = win.matchMedia('(max-width: 640px)');
        const arrange = () => panels.forEach((panel) => {
            panel.open = !compact.matches || !!panel.querySelector('[aria-invalid="true"]');
        });
        compact.addEventListener('change', arrange);
        arrange();
        form.addEventListener('invalid', (event) => {
            let panel = event.target.closest('details');
            while (panel) {
                panel.open = true;
                panel = panel.parentElement.closest('details');
            }
        }, true);
        const initial = fieldSnapshot(new FormData(form));
        const state = form.querySelector('[data-save-state]');
        const restoredInput = form.hasAttribute('data-unsaved-input');
        const isDirty = () => restoredInput || fieldSnapshot(new FormData(form)) !== initial;
        let saving = false;
        const update = () => {
            if (!state) return;
            const dirty = isDirty();
            state.hidden = false;
            state.textContent = dirty ? 'Unsaved changes' : 'No unsaved changes';
            state.classList.toggle('is-dirty', dirty);
        };
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        form.addEventListener('submit', (event) => {
            win.queueMicrotask(() => {
                if (!event.defaultPrevented) saving = true;
            });
        });
        win.addEventListener('pageshow', () => { saving = false; });
        win.addEventListener('beforeunload', (event) => {
            if (saving || !isDirty()) return;
            event.preventDefault();
            event.returnValue = '';
        });
        update();
    }
}

if (typeof document !== 'undefined') mountStudios();
