import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = {};
const { updatePhoneRows } = await import('../../public/assets/js/admin/studios.js');
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
