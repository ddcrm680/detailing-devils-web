import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = {};
const { updatePhoneRows, generatedStudioSlug, photoSnapshot } = await import('../../public/assets/js/admin/studios.js');
delete globalThis.window;

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
