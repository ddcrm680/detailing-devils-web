import { fieldSnapshot } from './workspace.js';

export function updatePhoneRows(list) {
    const rows = Array.from(list.querySelectorAll('[data-phone-row]'));
    rows.forEach((row, index) => {
        row.querySelector('input').name = `phones[${index}]`;
        row.querySelector('[data-phone-label]').textContent = `Phone ${index + 1}${index === 0 ? ' · Primary' : ''}`;
        row.querySelector('[data-phone-actions]').hidden = false;
        row.querySelector('[data-phone-move="-1"]').disabled = index === 0;
        row.querySelector('[data-phone-move="1"]').disabled = index === rows.length - 1;
        row.querySelector('[data-phone-remove]').disabled = rows.length === 1;
    });
    return rows.length;
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
    const files = doc.querySelector('[data-gallery-files]');
    const previews = doc.querySelector('[data-gallery-previews]');
    files?.addEventListener('change', () => {
        previews.replaceChildren();
        for (const file of Array.from(files.files).slice(0, 10)) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) continue;
            const reader = new win.FileReader();
            const image = doc.createElement('img');
            image.alt = `Selected image: ${file.name}`;
            previews.appendChild(image);
            reader.addEventListener('load', () => { image.src = reader.result; });
            reader.readAsDataURL(file);
        }
    });
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
                        titleText: 'Remove this image?',
                        text: 'It will leave this gallery. Its file will be retained for safe recovery.',
                        icon: 'warning',
                        showCancelButton: true,
                        focusCancel: true,
                        confirmButtonText: 'Remove',
                        buttonsStyling: false,
                        customClass: { confirmButton: 'button', cancelButton: 'button secondary' },
                    })).isConfirmed
                    : win.confirm('Remove this image from the gallery? Its file will be retained.');
                if (accepted) form.requestSubmit(event.submitter);
            } finally {
                confirming = false;
                accepted = false;
            }
        });
    });
    const form = doc.querySelector('[data-studio-form]');
    if (form) {
        const initial = fieldSnapshot(new FormData(form));
        let saving = false;
        form.addEventListener('submit', () => { saving = true; });
        win.addEventListener('pageshow', () => { saving = false; });
        win.addEventListener('beforeunload', (event) => {
            if (saving || fieldSnapshot(new FormData(form)) === initial) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }
}

if (typeof document !== 'undefined') mountStudios();
