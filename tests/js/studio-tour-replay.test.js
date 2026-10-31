import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync('public/assets/js/modules/studio-tour.js', 'utf8');
const start = source.indexOf('    page.querySelectorAll(');
const binding = source.slice(start, source.lastIndexOf('\n  }'));

for (const model of ['prime', 'fofo', 'xclusive']) {
  test(`Replay ${model} retains smooth interception with its routed fallback`, () => {
    const tour = {};
    const calls = [];
    const links = [
      { id: 'tour-replay', href: `/studio-tour?model=${model}#tour` },
      { id: 'explore', href: '#models' },
    ];
    for (const link of links) {
      link.getAttribute = () => link.href;
      link.addEventListener = (event, handler) => { link.click = handler; };
    }
    const models = {};
    vm.runInNewContext(binding, {
      tour,
      page: { querySelectorAll(selector) {
        assert.equal(selector, 'a[href^="#"], #tour-replay');
        return links;
      } },
      document: { querySelector(selector) { assert.equal(selector, '#models'); return models; } },
      lenis: { scrollTo(target, options) { calls.push({ target, duration: options.duration }); } },
    });
    let prevented = 0;
    links[0].click({ preventDefault() { prevented++; } });
    links[1].click({ preventDefault() { prevented++; } });
    assert.equal(prevented, 2);
    assert.equal(calls[0].target, tour);
    assert.equal(calls[0].duration, 2);
    assert.equal(calls[1].target, models);
    assert.equal(calls[1].duration, 1.4);
    const fallback = new URL(links[0].href, 'https://example.test/');
    assert.equal(fallback.pathname, '/studio-tour');
    assert.equal(fallback.searchParams.get('model'), model);
    assert.equal(fallback.hash, '#tour');
  });
}
