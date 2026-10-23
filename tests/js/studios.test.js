import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/modules/studios.js'), 'utf8');
const studios = JSON.parse(fs.readFileSync(path.join(root, 'resources/data/studios.json'), 'utf8'));

function setup() {
  const elements = new Map();
  const element = (id) => {
    const value = { value: '', hidden: false, disabled: false, textContent: '', listeners: {}, addEventListener(name, handler) { this.listeners[name] = handler; } };
    elements.set(id, value);
    return value;
  };
  for (const id of ['studio-filters', 'studio-search', 'studio-state', 'studio-count', 'studio-empty', 'studio-popular', 'studio-directory']) element(id);
  const cards = studios.map((studio) => ({
    hidden: false,
    dataset: { search: `${studio.name} ${studio.address} ${studio.state} ${studio.phones.join(' ')}`, state: studio.state },
    index: { textContent: '' },
    querySelector() { return this.index; },
  }));
  elements.set('studio-results', { hidden: false, querySelectorAll() { return cards; } });
  vm.runInNewContext(source, { document: { getElementById(id) { return elements.get(id); } } });
  return { cards, get: (id) => elements.get(id) };
}

test('shows all supplied records by default', () => {
  const { cards, get } = setup();
  assert.equal(cards.filter((card) => !card.hidden).length, studios.length);
  assert.equal(get('studio-count').textContent, `${studios.length} locations found`);
});

test('search and state filtering show every matching record', () => {
  const { cards, get } = setup();
  get('studio-search').value = 'Detailing Devils';
  get('studio-search').listeners.input();
  assert.equal(cards.filter((card) => !card.hidden).length, studios.length);
  assert.equal(get('studio-count').textContent, `${studios.length} locations found`);
  get('studio-search').value = 'Malviya Nagar';
  get('studio-search').listeners.input();
  assert.equal(get('studio-count').textContent, '2 locations found');
  assert.equal(cards.filter((card) => !card.hidden).length, 2);
  get('studio-state').value = 'Delhi';
  get('studio-state').listeners.change();
  assert.equal(get('studio-count').textContent, '1 location found');
  assert.equal(cards.filter((card) => !card.hidden).length, 1);
  get('studio-search').value = 'not a studio';
  get('studio-search').listeners.input();
  assert.equal(get('studio-count').textContent, '0 locations found');
  assert.equal(get('studio-empty').hidden, false);
  assert.equal(get('studio-results').hidden, true);
});

test('popular locations and the state dropdown show all matching studios', () => {
  const { cards, get } = setup();
  get('studio-state').value = 'Delhi';
  get('studio-state').listeners.change();
  assert.equal(cards.filter((card) => !card.hidden).length, 10);

  get('studio-popular').listeners.click({ target: { closest: () => ({ dataset: { studioSearch: 'Noida' } }) } });
  assert.equal(get('studio-search').value, 'Noida');
  assert.equal(get('studio-state').value, '');
  assert.equal(get('studio-count').textContent, '7 locations found');
  assert.equal(cards.filter((card) => !card.hidden).length, 7);

  get('studio-search').value = '';
  get('studio-state').value = 'Delhi';
  get('studio-state').listeners.change();
  assert.equal(get('studio-count').textContent, '10 locations found');
  assert.equal(cards.filter((card) => !card.hidden).length, 10);

  get('studio-state').value = '';
  get('studio-state').listeners.change();
  assert.equal(get('studio-state').value, '');
  assert.equal(get('studio-count').textContent, `${studios.length} locations found`);
  assert.equal(cards.filter((card) => !card.hidden).length, studios.length);
});
