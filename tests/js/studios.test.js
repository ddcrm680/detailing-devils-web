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
  for (const id of ['studio-filters', 'studio-search', 'studio-state', 'studio-count', 'studio-empty', 'studio-pagination', 'studio-previous', 'studio-next', 'studio-page', 'studio-popular', 'studio-presence-states', 'studio-view-all', 'studio-directory']) element(id);
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

test('paginates all 63 approved records at 50 per page', () => {
  const { cards, get } = setup();
  assert.equal(cards.filter((card) => !card.hidden).length, 50);
  assert.equal(get('studio-count').textContent, '63 locations found');
  assert.equal(get('studio-page').textContent, 'Page 1 of 2');
  get('studio-next').listeners.click();
  assert.equal(cards.filter((card) => !card.hidden).length, 13);
  assert.equal(get('studio-page').textContent, 'Page 2 of 2');
  assert.equal(get('studio-next').disabled, true);
});

test('search and state filtering use the complete dataset before pagination', () => {
  const { cards, get } = setup();
  get('studio-next').listeners.click();
  get('studio-search').value = 'Malviya Nagar';
  get('studio-search').listeners.input();
  assert.equal(get('studio-count').textContent, '2 locations found');
  assert.equal(get('studio-pagination').hidden, true);
  assert.equal(cards.filter((card) => !card.hidden).length, 2);
  get('studio-state').value = 'Delhi';
  get('studio-state').listeners.change();
  assert.equal(get('studio-count').textContent, '1 location found');
  assert.equal(cards.filter((card) => !card.hidden).length, 1);
  get('studio-search').value = 'not a studio';
  get('studio-search').listeners.input();
  assert.equal(get('studio-empty').hidden, false);
  assert.equal(get('studio-results').hidden, true);
});

test('popular locations and presence states reuse the complete search and state filters', () => {
  const { cards, get } = setup();
  get('studio-popular').listeners.click({ target: { closest: () => ({ dataset: { studioSearch: 'Noida' } }) } });
  assert.equal(get('studio-search').value, 'Noida');
  assert.equal(get('studio-count').textContent, '7 locations found');
  assert.equal(cards.filter((card) => !card.hidden).length, 7);

  get('studio-presence-states').listeners.click({ target: { closest: () => ({ dataset: { studioState: 'Delhi' } }) } });
  assert.equal(get('studio-search').value, '');
  assert.equal(get('studio-state').value, 'Delhi');
  assert.equal(get('studio-count').textContent, '10 locations found');

  get('studio-view-all').listeners.click();
  assert.equal(get('studio-state').value, '');
  assert.equal(get('studio-count').textContent, '63 locations found');
  assert.equal(cards.filter((card) => !card.hidden).length, 50);
});
