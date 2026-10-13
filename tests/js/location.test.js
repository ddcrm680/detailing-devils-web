import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const source = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public/assets/js/common/location.js'), 'utf8');
const tick = () => new Promise((resolve) => setImmediate(resolve));

class Select {
  constructor() {
    this.options = [];
    this.value = '';
    this.disabled = true;
    this.listeners = {};
  }

  replaceChildren(option) {
    this.options = [option];
    this.value = option.value;
  }

  add(option) {
    this.options.push(option);
  }

  addEventListener(event, callback) {
    this.listeners[event] = callback;
  }

  change(value) {
    this.value = value;
    this.listeners.change();
  }
}

function setup(get) {
  const window = {};
  const calls = [];
  const context = {
    window,
    axios: { get(url, config) {
      calls.push({ url, config });
      return get(url, config);
    } },
    Option: class {
      constructor(label, value) {
        this.label = label;
        this.value = value;
      }
    },
  };
  vm.runInNewContext(source, context);

  const country = new Select();
  const state = new Select();
  const city = new Select();
  const statuses = [];
  const selections = [];
  window.initLocationDropdowns({
    country, state, city,
    urls: { countries: '/locations/countries', states: '/locations/states', cities: '/locations/cities' },
    onStatus: (select, status) => statuses.push({ select, status }),
    onCountryChange: (value) => selections.push(value),
  });

  return { country, state, city, calls, statuses, selections };
}

test('loads dependent lists with shared Axios and resets descendants', async () => {
  const view = setup(async (url) => ({ data: { data: url.endsWith('countries')
    ? [{ id: 1, name: 'India' }]
    : url.endsWith('states')
      ? [{ id: 10, name: 'Maharashtra' }]
      : [{ id: 100, name: 'Mumbai' }] } }));
  await tick();

  assert.equal(view.country.disabled, false);
  view.country.change('1');
  assert.equal(view.state.disabled, true);
  assert.equal(view.city.disabled, true);
  await tick();
  assert.equal(view.calls[1].url, '/locations/states');
  assert.equal(view.calls[1].config.params.country_id, '1');
  assert.equal(view.calls[1].config.skipGlobalErrorHandler, true);
  assert.equal(view.state.disabled, false);

  view.state.change('10');
  await tick();
  assert.equal(view.calls[2].url, '/locations/cities');
  assert.equal(view.calls[2].config.params.state_id, '10');
  assert.equal(view.city.options[1].label, 'Mumbai');

  view.country.change('other');
  assert.equal(view.state.value, '');
  assert.equal(view.city.value, '');
  assert.equal(view.calls.length, 3);
  assert.deepEqual(view.selections, ['1', 'other']);
});

test('ignores an old state response after country changes', async () => {
  let resolveStates;
  const view = setup((url) => {
    if (url.endsWith('countries')) return Promise.resolve({ data: { data: [{ id: 1, name: 'India' }] } });
    return new Promise((resolve) => { resolveStates = resolve; });
  });
  await tick();
  view.country.change('1');
  view.country.change('other');
  resolveStates({ data: { data: [{ id: 10, name: 'Maharashtra' }] } });
  await tick();

  assert.equal(view.state.disabled, true);
  assert.equal(view.state.options.length, 1);
  assert.equal(view.state.value, '');
});

test('reports request errors without enabling an empty state list', async () => {
  const view = setup((url) => url.endsWith('countries')
    ? Promise.resolve({ data: { data: [{ id: 1, name: 'India' }] } })
    : Promise.reject(new Error('unavailable')));
  await tick();
  view.country.change('1');
  await tick();

  assert.equal(view.state.disabled, true);
  assert.equal(view.statuses.at(-1).status, 'error');
});
