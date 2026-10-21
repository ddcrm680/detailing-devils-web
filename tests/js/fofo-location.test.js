import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const locationSource = fs.readFileSync(new URL('../../public/assets/js/common/location.js', import.meta.url), 'utf8');
const fofoSource = fs.readFileSync(new URL('../../public/assets/js/modules/fofo.js', import.meta.url), 'utf8');
const tick = () => new Promise((resolve) => setImmediate(resolve));

function element(name = '') {
  return {
    name, value: '', disabled: false, dataset: {}, listeners: {},
    classList: { remove() {} },
    addEventListener(type, listener) { this.listeners[type] = listener; },
    dispatchEvent(event) { this.listeners[event.type]?.(event); },
    removeAttribute() {}, setAttribute() {}, querySelectorAll: () => [],
  };
}

function setup({ restored = {}, get } = {}) {
  const calls = [];
  const fields = new Map();
  for (const name of ['country', 'state', 'city', 'dob', 'email', 'phone', 'bussinessowner', 'experienceautomotive', 'existingautomotive', 'spacearound', 'fofo-submit', 'fofo-review', 'fofo-form-status', 'fofo-location-manual', 'fofo-location-selects', 'fofo-business-details', 'fofo-experience-details', 'fofo-facility-details', 'fofo-property-details']) {
    fields.set(`#${name}`, element(name));
  }
  for (const name of ['country', 'state', 'city', 'email']) {
    fields.set(`[data-error-for="${name}"]`, { textContent: '', dataset: {} });
  }
  fields.get('#fofo-submit').disabled = true;
  const selects = Object.fromEntries(['country', 'state', 'city'].map((name) => {
    const select = fields.get(`#${name}`);
    select.value = restored[name] || '';
    select.options = [];
    select.disabled = true;
    select.replaceChildren = (option) => { select.options = [option]; select.value = option.value; };
    select.add = (option) => select.options.push(option);
    select.change = (value) => { select.value = value; select.dispatchEvent({ type: 'change' }); };
    return [name, select];
  }));
  const form = {
    ...element(),
    dataset: { countriesUrl: '/countries', statesUrl: '/states', citiesUrl: '/cities' },
    querySelector: (selector) => fields.get(selector),
  };
  const window = {
    $: () => form,
    history: { state: null },
    showValidationErrors(_form, errors) {
      Object.entries(errors).forEach(([name, messages]) => {
        fields.get(`[data-error-for="${name}"]`).textContent = messages[0];
      });
    },
  };
  const context = vm.createContext({
    window, Date,
    Event: class { constructor(type) { this.type = type; } },
    Option: class { constructor(text, value) { this.text = text; this.value = value; } },
    axios: { async get(url, config) {
      calls.push({ url, params: config?.params });
      if (get) return get(url, config);
      const data = url === '/countries'
        ? [{ id: 44, name: 'United Kingdom' }, { id: 91, name: 'India' }]
        : url === '/states'
          ? config.params.country_id === '91'
            ? [{ id: 10, name: 'Delhi' }, { id: 20, name: 'Maharashtra' }]
            : [{ id: 30, name: 'England' }]
          : [{ id: config.params.state_id === '10' ? 100 : config.params.state_id === '20' ? 200 : 300, name: 'Fixture city' }];
      return { data: { data } };
    } },
  });
  vm.runInContext(locationSource, context);
  vm.runInContext(fofoSource, context);
  return { ...selects, calls, fields };
}

test('FOFO defaults to India by returned name and loads states without selecting state or city', async () => {
  const view = setup();
  await tick();
  assert.equal(view.country.value, '91');
  assert.equal(view.state.value, '');
  assert.equal(view.state.options[0].text, 'Select state');
  assert.equal(view.state.disabled, false);
  assert.equal(view.city.value, '');
  assert.equal(view.city.disabled, true);
  assert.deepEqual(view.calls.map((call) => call.url), ['/countries', '/states']);
  assert.equal(view.calls[1].params.country_id, '91');
});

test('changing states loads their cities and changing country retains the existing cascade', async () => {
  const view = setup();
  await tick();
  view.state.change('10');
  await tick();
  assert.equal(view.city.options[1].value, '100');
  view.city.change('100');
  view.state.change('20');
  assert.equal(view.city.value, '');
  assert.equal(view.city.disabled, true);
  await tick();
  assert.equal(view.city.options[1].value, '200');
  view.country.change('44');
  await tick();
  assert.equal(view.state.options[1].value, '30');
  assert.equal(view.state.value, '');
  assert.equal(view.city.disabled, true);
  view.state.change('30');
  await tick();
  assert.equal(view.city.options[1].value, '300');
});

test('existing country, state and city values take precedence over the India default', async () => {
  const view = setup({ restored: { country: '44', state: '30', city: '300' } });
  await tick();
  assert.equal(view.country.value, '44');
  assert.equal(view.state.value, '30');
  assert.equal(view.city.value, '300');
  assert.equal(view.city.disabled, false);
  assert.equal(view.calls[1].params.country_id, '44');
  view.country.change('91');
  await tick();
  view.country.change('44');
  await tick();
  assert.equal(view.state.value, '');
  assert.equal(view.city.value, '');
});

test('a restored manual country remains selected even if country loading fails', async () => {
  const view = setup({ restored: { country: 'other' }, get: async () => { throw new Error('offline'); } });
  await tick();
  assert.equal(view.country.value, 'other');
  assert.equal(view.fields.get('#fofo-location-manual').hidden, false);
  assert.equal(view.fields.get('#fofo-location-selects').hidden, true);
  assert.equal(view.city.disabled, true);
});

test('does not guess a country ID when India is absent from the returned data', async () => {
  const view = setup({ get: async () => ({ data: { data: [{ id: 44, name: 'United Kingdom' }] } }) });
  await tick();
  assert.equal(view.country.value, '');
  assert.equal(view.calls.length, 1);
  assert.equal(view.city.disabled, true);
});
