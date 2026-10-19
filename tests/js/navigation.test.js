import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/modules/navigation.js', import.meta.url), 'utf8');

function setup() {
  const document = { activeElement: null };
  const element = (tagName = 'DIV') => ({
    tagName, inert: false, visible: true, attributes: {}, listeners: {},
    classList: { values: new Set(), toggle(name, enabled) { enabled ? this.values.add(name) : this.values.delete(name); }, contains(name) { return this.values.has(name); } },
    addEventListener(name, callback) { this.listeners[name] = callback; },
    setAttribute(name, value) { this.attributes[name] = value; },
    getClientRects() { return this.visible ? [{}] : []; },
    focus() { document.activeElement = this; },
  });
  const hdr = element('HEADER');
  const burger = element('BUTTON');
  const menu = element();
  menu.inert = true;
  const links = Array.from({ length: 7 }, () => element('A'));
  const headerLinks = Array.from({ length: 3 }, () => element('A'));
  headerLinks[2].visible = false;
  const nav = { scrollTop: 100 };
  hdr.querySelectorAll = (selector) => selector === 'a' ? headerLinks : [...headerLinks, burger];
  menu.querySelectorAll = () => links;
  menu.querySelector = (selector) => selector === 'nav' ? nav : links[0];
  const main = element('MAIN');
  const footer = element('FOOTER');
  footer.inert = true;
  const script = element('SCRIPT');
  document.documentElement = element('HTML');
  document.body = element('BODY');
  document.body.children = [hdr, menu, main, footer, script];
  document.getElementById = (id) => ({ hdr, burger, menu })[id];
  const events = [];
  const window = {
    listeners: {},
    addEventListener(name, callback) { this.listeners[name] = callback; },
    dispatchEvent(event) { events.push(event); },
  };
  vm.runInNewContext(source, {
    document, window, scrollY: 0, innerHeight: 900,
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
  });
  return { document, window, burger, menu, nav, main, footer, script, links, headerLinks, events };
}

test('opening contains the page and closing restores focus, inert state and scroll locks', () => {
  const ui = setup();
  ui.burger.listeners.click();
  assert.equal(ui.document.documentElement.classList.contains('menu-open'), true);
  assert.equal(ui.document.body.classList.contains('menu-open'), true);
  assert.equal(ui.menu.inert, false);
  assert.equal(ui.menu.attributes['aria-hidden'], 'false');
  assert.equal(ui.burger.attributes['aria-expanded'], 'true');
  assert.equal(ui.document.activeElement, ui.links[0]);
  assert.equal(ui.nav.scrollTop, 0);
  assert.equal(ui.main.inert, true);
  assert.equal(ui.script.inert, false);

  ui.burger.listeners.click();
  assert.equal(ui.document.documentElement.classList.contains('menu-open'), false);
  assert.equal(ui.document.body.classList.contains('menu-open'), false);
  assert.equal(ui.menu.inert, true);
  assert.equal(ui.menu.attributes['aria-hidden'], 'true');
  assert.equal(ui.burger.attributes['aria-expanded'], 'false');
  assert.equal(ui.document.activeElement, ui.burger);
  assert.equal(ui.main.inert, false);
  assert.equal(ui.footer.inert, true);
  assert.deepEqual(ui.events.map((event) => event.detail.open), [true, false]);
});

test('Tab and Shift+Tab wrap between the visible header and menu without reaching the page', () => {
  const ui = setup();
  ui.burger.listeners.click();
  ui.links[6].focus();
  let prevented = 0;
  ui.window.listeners.keydown({ key: 'Tab', shiftKey: false, preventDefault() { prevented++; } });
  assert.equal(ui.document.activeElement, ui.headerLinks[0]);
  ui.window.listeners.keydown({ key: 'Tab', shiftKey: true, preventDefault() { prevented++; } });
  assert.equal(ui.document.activeElement, ui.links[6]);
  assert.equal(prevented, 2);
});

test('menu and header links close the overlay, and Escape only acts on an open menu', () => {
  const ui = setup();
  for (const link of [...ui.links, ...ui.headerLinks]) {
    ui.burger.listeners.click();
    link.listeners.click();
    assert.equal(ui.menu.inert, true);
    assert.equal(ui.main.inert, false);
  }
  ui.burger.listeners.click();
  ui.window.listeners.keydown({ key: 'Escape' });
  assert.equal(ui.menu.inert, true);
  assert.equal(ui.document.activeElement, ui.burger);
  ui.main.focus();
  const signals = ui.events.length;
  ui.window.listeners.keydown({ key: 'Escape' });
  assert.equal(ui.document.activeElement, ui.main);
  assert.equal(ui.events.length, signals);
});

test('pages without the shared menu do not register navigation handlers', () => {
  assert.doesNotThrow(() => vm.runInNewContext(source, { document: { getElementById() { return null; } } }));
});
