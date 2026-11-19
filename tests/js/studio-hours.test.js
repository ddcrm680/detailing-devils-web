import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.window = {};
const { mountOpeningHours, mountStudioServices } = await import('../../public/assets/js/admin/studios.js');
delete globalThis.window;

function fixture({ empty = false, uniform = false } = {}) {
    const events = {};
    const controls = {};
    for (const name of ['hours-compact', 'hours-customize', 'common-opens', 'common-closes', 'open-all-days', 'hours-status', 'hours-preset', 'hours-clear']) {
        controls[name] = { hidden: true, open: true, value: '', checked: false, addEventListener(type, handler) { this[type] = handler; }, closest() { return null; } };
    }
    const rows = ['monday', 'tuesday', 'sunday'].map((day) => {
        const closed = { checked: !empty && !uniform && day === 'sunday' };
        const opens = { value: empty ? '' : uniform || day === 'monday' ? '10:00' : '09:00' };
        const closes = { value: empty ? '' : uniform || day === 'monday' ? '19:00' : '17:00' };
        const row = { querySelector(s) { return s.includes('closed') ? closed : s.includes('opens') ? opens : closes; }, closed, opens, closes };
        [closed, opens, closes].forEach(input => { input.closest = () => row; });
        return row;
    });
    const section = {
        querySelectorAll() { return rows; },
        querySelector(s) { return s.includes('aria-invalid') ? null : controls[s.slice(6, -1)]; },
        addEventListener(type, handler) { events[type] = handler; },
    };
    let changes = 0;
    mountOpeningHours({ querySelector() { return section; }, dispatchEvent() { changes++; } });
    return { rows, controls, changes: () => changes, change(target) { events.change({ target }); } };
}

test('initial mount reflects saved custom and empty hours without populating them', () => {
    const f = fixture();
    assert.equal(f.controls['hours-customize'].open, false);
    assert.equal(f.rows[1].opens.value, '09:00');
    assert.equal(f.rows[2].closed.checked, true);
    assert.equal(f.controls['open-all-days'].checked, false);
    const empty = fixture({ empty: true });
    assert.ok(empty.rows.every(row => row.opens.value === ''));
    assert.equal(empty.controls['hours-status'].textContent, 'Hours not set');
    assert.equal(empty.changes(), 0);
});

test('shared time edits preserve closed days; Open All 7 Days explicitly opens them', () => {
    const f = fixture();
    f.controls['common-opens'].value = '11:00';
    f.controls['common-closes'].value = '18:00';
    f.change(f.controls['common-opens']);
    assert.equal(f.rows[1].opens.value, '11:00');
    assert.equal(f.rows[2].opens.value, '09:00');
    f.controls['open-all-days'].checked = true;
    f.change(f.controls['open-all-days']);
    assert.equal(f.rows[2].closed.checked, false);
    assert.equal(f.rows[2].opens.value, '11:00');
});

test('uniform defaults stay compact; custom day changes preserve the other days', () => {
    const f = fixture({ uniform: true });
    assert.equal(f.controls['common-opens'].value, '10:00');
    assert.equal(f.controls['open-all-days'].checked, true);
    f.controls['open-all-days'].checked = false;
    f.change(f.controls['open-all-days']);
    assert.equal(f.controls['hours-customize'].open, true);
    f.rows[2].closed.checked = true;
    f.change(f.rows[2].closed);
    assert.equal(f.rows[0].opens.value, '10:00');
    assert.equal(f.rows[2].opens.disabled, true);
});

test('preset and Clear Hours are explicit, reversible form changes', () => {
    const f = fixture({ empty: true });
    f.controls['hours-preset'].click();
    assert.ok(f.rows.every(row => row.opens.value === '10:00' && row.closes.value === '19:00' && !row.closed.checked));
    f.controls['hours-clear'].click();
    assert.ok(f.rows.every(row => row.opens.value === '' && row.closes.value === '' && !row.closed.checked));
    assert.equal(f.changes(), 2);
});

test('Select All and Clear change only the selected service checkboxes', () => {
    const checks = [{ checked: true }, { checked: false }];
    const actions = {};
    const tools = { hidden: true, querySelector(s) { return { addEventListener(type, handler) { actions[s] = handler; } }; } };
    let changes = 0;
    mountStudioServices({ querySelector() { return tools; }, querySelectorAll() { return checks; }, dispatchEvent() { changes++; } });
    actions['[data-services-all]']();
    assert.ok(checks.every(c => c.checked));
    actions['[data-services-clear]']();
    assert.ok(checks.every(c => !c.checked));
    assert.equal(changes, 2);
});
