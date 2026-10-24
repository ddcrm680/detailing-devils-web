import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../public/assets/js/modules/frame-loader.js', import.meta.url), 'utf8');
const settle = async () => { for (let i = 0; i < 30; i++) await new Promise(setImmediate); };

function setup({ manualFetch = false, manualDecode = false, manualWarm = false, legacyDecode = false, bitmapError = false, fail = false, compact = true, fetchConcurrency = 4, nativeImages = null, blobSize = 65536, menuOpen = false } = {}) {
  const requests = [], decodes = [], images = [], progress = [], revoked = [], listeners = new Map(), timers = new Map();
  const media = { matches: compact }, document = { hidden: false, documentElement: { classList: { contains: () => menuOpen } } };
  const warms = [];
  let now = 0, timerId = 0, activeDecodes = 0, peakDecodes = 0;
  const context = vm.createContext({
    performance: { now: () => now }, document, navigator: {}, matchMedia: () => media,
    AbortController, DOMException, setTimeout: (fn) => { timers.set(++timerId, fn); return timerId; },
    clearTimeout: (id) => timers.delete(id),
    window: {
      addEventListener: (name, fn) => listeners.set(name, fn),
      removeEventListener: (name) => listeners.delete(name),
    },
    URL: { createObjectURL: (blob) => `blob:${blob.index}`, revokeObjectURL: (url) => revoked.push(url) },
    Image: class {
      constructor() {
        assert.ok(images.length < 1000, 'Image scheduling must settle within a bounded number of resources');
        this.naturalWidth = 1920; this.naturalHeight = 1080; this.closed = false;
        images.push(this);
        if (legacyDecode) this.decode = undefined;
      }
      set src(value) {
        this.value = value;
        if (!value) { assert.equal(this.closed, false); this.closed = true; }
        else {
          this.index = Number(value.includes(':') ? value.split(':')[1] : value);
          activeDecodes++; peakDecodes = Math.max(peakDecodes, activeDecodes);
          const decode = { index: this.index, resolve: () => { activeDecodes--; this.onload?.(); } };
          decodes.push(decode);
          if (!manualDecode) queueMicrotask(decode.resolve);
        }
      }
      get src() { return this.value; }
      decode() {
        return new Promise((resolve) => {
          warms.push({ index: this.index, resolve });
          if (!manualWarm) resolve();
        });
      }
    },
    fetch: (url, options) => new Promise((resolve, reject) => {
      const request = { index: Number(url), options, resolve: () => resolve({ ok: !fail, blob: async () => ({ size: blobSize, index: Number(url) }) }) };
      options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
      requests.push(request);
      if (!manualFetch) request.resolve();
    }),
    createImageBitmap: (blob) => {
      assert.ok(images.length < 1000, 'Bitmap scheduling must settle within a bounded number of resources');
      if (bitmapError) return Promise.reject(new Error('Unsupported bitmap format'));
      return new Promise((resolve) => {
        activeDecodes++; peakDecodes = Math.max(peakDecodes, activeDecodes);
        const image = { width: 1920, height: 1080, index: blob.index, closed: false,
          close() { assert.equal(this.closed, false); this.closed = true; } };
        images.push(image);
        const decode = { index: blob.index, resolve: () => { activeDecodes--; resolve(image); } };
        decodes.push(decode);
        if (!manualDecode) queueMicrotask(decode.resolve);
      });
    },
  });
  vm.runInContext(source.replace('export class FrameLoader', 'globalThis.FrameLoader = class FrameLoader'), context);
  const loader = new context.FrameLoader({ count: 330, source: (index) => String(index), frameBytes: 1920 * 1080 * 4, fetchConcurrency, nativeImages: nativeImages || (() => media.matches), onProgress: (...args) => progress.push(args) });
  return {
    loader, requests, decodes, warms, images, progress, revoked, media, document, listeners, timers, navigator: context.navigator,
    advance: (ms = 100) => { now += ms; },
    peakDecodes: () => peakDecodes,
    fire: (name, event = {}) => listeners.get(name)?.(event),
    retry: () => { now += 1501; const pending = [...timers.values()]; timers.clear(); pending.forEach((fn) => fn()); },
  };
}

test('stationary loading is bounded, starts with frame zero once and warms only the local buffer', async () => {
  const ui = setup();
  ui.loader.update(0);
  await settle();
  assert.deepEqual(ui.requests.map((r) => r.index), [0, 1, 2, 3, 4, 5, 6]);
  assert.equal(ui.requests.filter((r) => r.index === 0).length, 1);
  assert.equal(ui.loader.get(0).index, 0);
  assert.equal(ui.progress.at(-1)[1], true);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  assert.equal(ui.peakDecodes() <= 6, true);
  ui.loader.destroy();
});

test('a completed request refills its slot without waiting for its batch', async () => {
  const ui = setup({ manualFetch: true });
  ui.loader.update(0);
  assert.equal(ui.requests.length, 4);
  ui.requests[1].resolve();
  await settle();
  assert.equal(ui.requests.length, 5);
  assert.equal(ui.loader.requests.has(0), true);
  ui.loader.destroy();
});

test('the AVIF pipeline can cap normal downloads independently of connection estimates', async () => {
  const ui = setup({ manualFetch: true, fetchConcurrency: 2 });
  ui.navigator.connection = { downlink: 10, rtt: 0, effectiveType: '4g' };
  ui.loader.update(0);
  assert.equal(ui.requests.length, 2);
  ui.requests[1].resolve(); await settle();
  assert.equal(ui.requests.length, 3);
  assert.equal(ui.loader.requests.size, 2);
  ui.loader.destroy();
});

test('responsive fetch limits change on resize and still respect a slow-network cap', async () => {
  const ui = setup({ manualFetch: true, compact: false, fetchConcurrency: (compact) => compact ? 4 : 2 });
  ui.loader.update(0);
  assert.equal(ui.requests.length, 2);
  ui.media.matches = true; ui.loader.update(0);
  assert.equal(ui.requests.length, 4);
  ui.navigator.connection = { downlink: 1.6, rtt: 150, effectiveType: '4g' };
  ui.advance(); ui.loader.update(1);
  assert.equal(ui.loader.concurrency, 2);
  assert.equal(ui.requests.length, 4, 'Already running requests should not be starved by a changed limit');
  ui.loader.destroy();
});

test('a jump aborts obsolete downloads and prioritizes the new target', async () => {
  const ui = setup({ manualFetch: true });
  ui.loader.update(0);
  ui.advance(); ui.loader.update(200);
  await settle();
  assert.equal(ui.requests.find((r) => r.index === 1).options.signal.aborted, true);
  assert.equal(ui.requests[4].index, 200);
  assert.equal(ui.requests[4].options.priority, 'high');
  assert.equal(ui.loader.requests.size <= 4, true);
  ui.loader.destroy();
});

test('late images are retained only while useful, with at most six resource loads in flight', async () => {
  const ui = setup({ manualDecode: true });
  ui.loader.update(0);
  await settle();
  assert.equal(ui.decodes.length, 6);
  ui.advance(); ui.loader.update(200);
  ui.decodes[1].resolve();
  await settle();
  assert.equal(ui.loader.get(200).index, 1);
  assert.equal(ui.decodes[6].index, 200);
  assert.equal(ui.peakDecodes(), 6);
  ui.decodes[6].resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 200);
  ui.loader.destroy();
  ui.decodes.filter((decode) => decode !== ui.decodes[1] && decode !== ui.decodes[6]).forEach((decode) => decode.resolve());
  await settle();
  assert.equal(ui.images.every((image) => image.closed), true);
});

async function initialFrames(options = {}) {
  const ui = setup({ compact: false, manualFetch: true, ...options });
  const fetched = new Set(), decoded = new Set();
  ui.loader.update(0);
  for (let pass = 0; pass < 12; pass++) {
    for (const request of ui.requests) if (!fetched.has(request)) { fetched.add(request); request.resolve(); }
    await settle();
    if (options.manualDecode) {
      for (const decode of ui.decodes) if (!decoded.has(decode)) { decoded.add(decode); decode.resolve(); }
      await settle();
    }
    if (ui.loader.cache.size === 7) return ui;
  }
  assert.fail('Initial frames did not settle');
}

function advancePast(ui, start, end) {
  ui.advance(); ui.loader.update(start);
  const direction = end > start ? 1 : -1;
  for (let index = start + direction; index !== end + direction; index += direction) {
    ui.advance(16); ui.loader.update(index);
  }
}

test('a downloaded frame outside the forward window becomes one fallback without another fetch', async () => {
  const ui = await initialFrames();
  advancePast(ui, 109, 200);
  const request = ui.requests.find((r) => r.index === 109);
  assert.equal(request.options.signal.aborted, false);
  request.resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 109);
  assert.equal(ui.loader.fallback, 109);
  assert.equal(ui.loader.window.has(109), true);
  assert.equal(ui.requests.filter((r) => r.index === 109).length, 1);
  for (let pass = 0; pass < 10; pass++) ui.loader.pump();
  await settle();
  assert.equal(ui.decodes.filter((d) => d.index === 109).length, 1);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
});

test('a pending fallback leaves decode capacity and pixels for the exact frame', async () => {
  const ui = await initialFrames({ manualDecode: true });
  advancePast(ui, 109, 200);
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  const fallback = ui.decodes.find((d) => d.index === 109);
  assert.equal(ui.loader.fallbackJob, 109);
  assert.equal(ui.loader.bytes + ui.loader.frameBytes * (ui.loader.decoding.size + 1) <= ui.loader.maxBytes, true);
  for (const index of [201, 202, 203]) {
    ui.loader.blobs.set(index, { index, size: 65536 }); ui.loader.blobBytes += 65536;
  }
  ui.loader.pump(); await settle();
  assert.equal(ui.loader.decoding.size, 3);
  assert.equal(ui.decodes.some((d) => d.index === 203), false, 'Speculation leaves the fourth slot free');
  ui.requests.find((r) => r.index === 200).resolve(); await settle();
  const exact = ui.decodes.find((d) => d.index === 200);
  assert.ok(exact, 'Exact decoding starts while fallback decoding is pending');
  exact.resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 200);
  fallback.resolve(); await settle();
  assert.equal(ui.images.find((im) => im.index === 109).closed, true);
  assert.equal(ui.loader.fallbackJob, null);
  assert.equal(ui.loader.fallback, null);
  assert.equal(ui.peakDecodes() <= 4, true);
  ui.loader.destroy();
  for (const decode of ui.decodes) if ([201, 202, 203].includes(decode.index)) decode.resolve();
  await settle();
});

test('fallback work is skipped when the exact frame needs the remaining pixel slot', async () => {
  const ui = await initialFrames();
  ui.loader.get(6);
  advancePast(ui, 109, 200);
  for (const key of [...ui.loader.cache.keys()]) if (key !== 0 && key !== 6) ui.loader.evict(key);
  ui.loader.maxBytes = ui.loader.frameBytes * 3;
  ui.loader.selectWindow();
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  assert.equal(ui.decodes.some((d) => d.index === 109), false);
  ui.requests.find((r) => r.index === 200).resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 200);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
});

test('a late normal decode is reused as fallback instead of being decoded twice', async () => {
  const ui = await initialFrames({ manualDecode: true });
  ui.advance(); ui.loader.update(109);
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  const late = ui.decodes.find((d) => d.index === 109);
  advancePast(ui, 110, 200);
  late.resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 109);
  assert.equal(ui.decodes.filter((d) => d.index === 109).length, 1);
  assert.equal(ui.loader.fallback, 109);
  ui.loader.destroy();
});

test('exact decoding can evict a retained fallback after the pixel budget shrinks', async () => {
  const ui = await initialFrames();
  ui.loader.get(6);
  advancePast(ui, 109, 200);
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  assert.equal(ui.loader.fallback, 109);
  ui.loader.maxBytes = ui.loader.frameBytes * 3;
  assert.equal(ui.loader.makeRoom(0), true);
  ui.requests.find((r) => r.index === 200).resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 200);
  assert.equal(ui.loader.fallback, null);
  assert.equal(ui.images.find((im) => im.index === 109).closed, true);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
});

test('out-of-order late completions never replace a closer visible fallback', async () => {
  const ui = await initialFrames({ manualDecode: true });
  ui.advance(); ui.loader.update(109);
  for (const index of [109, 111]) ui.requests.find((r) => r.index === index).resolve();
  await settle();
  advancePast(ui, 110, 200);
  ui.decodes.find((d) => d.index === 111).resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 111);
  ui.decodes.find((d) => d.index === 109).resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 111);
  assert.equal(ui.images.find((im) => im.index === 109).closed, true);
  ui.loader.destroy();
});

test('rapid reversal picks a closer downloaded fallback and holds it through tied arrivals', async () => {
  const ui = await initialFrames();
  advancePast(ui, 109, 200);
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  assert.equal(ui.loader.get(200).index, 109);
  ui.advance(); ui.loader.update(98); await settle();
  advancePast(ui, 97, 60);
  ui.requests.find((r) => r.index === 98).resolve(); await settle();
  assert.equal(ui.loader.get(60).index, 98);
  ui.requests.find((r) => r.index === 96).resolve(); await settle();
  assert.equal(ui.loader.get(60).index, 96);
  const created = ui.images.length;
  ui.loader.blobs.set(24, { index: 24, size: 1 });
  ui.loader.blobBytes += 1;
  for (let pass = 0; pass < 10; pass++) ui.loader.pump();
  await settle();
  assert.equal(ui.loader.get(60).index, 96);
  assert.equal(ui.images.length, created);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  assert.equal(ui.decodes.filter((d) => d.index === 98).length, 1);
  ui.loader.destroy();
});

test('pending fallback completion after suspension releases its resource without restarting work', async () => {
  const ui = await initialFrames({ manualDecode: true });
  advancePast(ui, 109, 200);
  ui.requests.find((r) => r.index === 109).resolve(); await settle();
  const fallback = ui.decodes.find((d) => d.index === 109);
  ui.loader.update(200, false);
  const count = ui.requests.length;
  fallback.resolve(); await settle();
  assert.equal(ui.loader.fallbackJob, null);
  assert.equal(ui.images.find((im) => im.index === 109).closed, true);
  assert.equal(ui.requests.length, count);
  ui.loader.destroy();
});

test('continuous scrolling lets slow downloads finish instead of starving them with repeated aborts', async () => {
  const ui = setup({ manualFetch: true });
  ui.loader.update(0);
  for (let index = 1; index <= 50; index++) { ui.advance(16); ui.loader.update(index); }
  assert.equal(ui.requests.length, 4);
  assert.equal(ui.requests.every((request) => !request.options.signal.aborted), true);
  ui.requests[1].resolve(); await settle();
  assert.equal(ui.requests[4].index, 50);
  ui.loader.destroy();
});

test('loaded target frames remain displayable while two background decodes are still pending', async () => {
  const ui = setup({ manualWarm: true });
  ui.loader.update(0); await settle();
  assert.equal(ui.warms.length, 2);
  assert.equal(ui.loader.get(1).index, 1);
  ui.advance(); ui.loader.update(200); await settle();
  assert.equal(ui.loader.get(200).index, 200);
  assert.equal(ui.warms.length, 2);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
  ui.warms.forEach((warm) => warm.resolve()); await settle();
  assert.equal(ui.loader.warming.size, 0);
});

test('visited compressed frames are reused on reverse traversal without retaining an unbounded cache', async () => {
  const ui = setup({ blobSize: 1024 * 1024 });
  for (const index of [0, 30, 60, 90, 120, 150]) {
    ui.advance(500); ui.loader.update(index); await settle();
    assert.equal(ui.loader.blobBytes <= 40 * 1024 * 1024, true);
  }
  const requests = ui.requests.length;
  const loaded = ui.images.length;
  ui.advance(500); ui.loader.update(149); await settle();
  assert.equal(ui.requests.slice(requests).some((request) => request.index === 149), false);
  assert.ok(ui.images.length - loaded <= 20, 'Reversal must not repeatedly reload its own eviction victims');
  ui.loader.destroy();
});

test('forward, reverse and replay retain exact target access within the decoded byte budget', async () => {
  const ui = setup();
  for (const index of [0, 5, 40, 100, 200, 329, 200, 40, 1, 0]) {
    ui.advance(500); ui.loader.update(index);
    await settle();
    assert.equal(ui.loader.get(index)?.index, index);
    assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
    assert.equal(ui.loader.blobBytes <= ui.loader.maxBlobBytes, true);
    assert.equal(ui.loader.cache.has(0), true);
    assert.equal(ui.images.filter((image) => !image.closed).length, ui.loader.cache.size);
  }
  ui.loader.destroy();
  assert.equal(ui.images.every((image) => image.closed), true);
});

test('nearest-frame fallback chooses the lower frame on ties and never returns a released image', async () => {
  const ui = setup();
  ui.loader.update(0); await settle();
  const lower = ui.loader.cache.get(1), upper = ui.loader.cache.get(3);
  for (const key of [...ui.loader.cache.keys()]) if (key !== 1 && key !== 3) ui.loader.evict(key);
  assert.equal(ui.loader.get(2).image, lower);
  ui.loader.evict(1);
  assert.equal(ui.loader.get(2).image, upper);
  ui.loader.destroy();
});

test('leaving the sequence pauses downloads, trims decoded frames and resumes on return', async () => {
  const ui = setup();
  ui.loader.update(20); await settle();
  ui.loader.get(20);
  ui.loader.update(20, false);
  assert.deepEqual([...ui.loader.cache.keys()].sort((a, b) => a - b), [0, 20]);
  const count = ui.requests.length;
  await settle(); assert.equal(ui.requests.length, count);
  ui.loader.update(20, true); await settle();
  assert.equal(ui.loader.get(20).index, 20);
  assert.equal(ui.loader.cache.size > 2, true);
  ui.loader.destroy();
});

test('visibility, menu pause and persisted page navigation resume without destroying the loader', async () => {
  const ui = setup();
  ui.loader.update(0); await settle();
  ui.document.hidden = true; ui.fire('visibilitychange');
  assert.equal(ui.loader.active, false);
  ui.document.hidden = false; ui.fire('visibilitychange'); await settle();
  assert.equal(ui.loader.active, true);
  ui.fire('dd:menu-toggle', { detail: { open: true } });
  ui.loader.update(0); assert.equal(ui.loader.active, false);
  ui.fire('dd:menu-toggle', { detail: { open: false } }); await settle();
  ui.fire('pagehide', { persisted: true });
  assert.equal(ui.loader.destroyed, false);
  ui.fire('pageshow'); await settle();
  assert.equal(ui.loader.active, true);
  ui.fire('pagehide', { persisted: false });
  assert.equal(ui.loader.destroyed, true);
  assert.equal(ui.listeners.size, 0);
});

test('resizing to compact mode enforces the smaller memory budget', async () => {
  const ui = setup({ compact: false });
  ui.loader.update(100); await settle();
  ui.media.matches = true;
  ui.loader.update(100); await settle();
  assert.equal(ui.loader.maxBytes, 96 * 1024 * 1024);
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
});

test('resize replaces the displayed resource with the right renderer and a new draw version', async () => {
  const ui = setup({ compact: false });
  ui.loader.update(1); await settle();
  const desktop = ui.loader.get(1);
  assert.equal(typeof desktop.image.close, 'function');
  ui.media.matches = true; ui.loader.update(1); await settle();
  const mobile = ui.loader.get(1);
  assert.equal(mobile.image.close, undefined);
  assert.equal(desktop.image.closed, true);
  assert.notEqual(mobile.version, desktop.version);
  ui.media.matches = false; ui.loader.update(1); await settle();
  assert.equal(typeof ui.loader.get(1).image.close, 'function');
  assert.equal(ui.loader.bytes <= ui.loader.maxBytes, true);
  ui.loader.destroy();
  assert.equal(ui.images.every((image) => image.closed), true);
});

test('a slow high-latency 4g connection limits fetching without relying on its broad type label', async () => {
  const ui = setup({ manualFetch: true });
  ui.navigator.connection = { downlink: 1.6, rtt: 150, effectiveType: '4g' };
  ui.loader.update(0);
  assert.equal(ui.requests.length, 2);
  ui.loader.destroy();
});

test('desktop opening uses the original native renderer while later frames use bitmaps', async () => {
  const ui = setup({ compact: false });
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(0).image.close, undefined);
  assert.equal(typeof ui.loader.get(1).image.close, 'function');
  assert.equal(ui.peakDecodes() <= 4, true);
  ui.loader.destroy();
});

test('a fine-pointer canvas scaled below half-size can retain native resampling across resize', async () => {
  let downscaled = true;
  const ui = setup({ compact: false, nativeImages: () => downscaled });
  ui.loader.update(1); await settle();
  const native = ui.loader.get(1);
  assert.equal(native.image.close, undefined);
  assert.equal(ui.loader.maxBytes, 160 * 1024 * 1024);
  downscaled = false; ui.loader.update(1); await settle();
  assert.equal(typeof ui.loader.get(1).image.close, 'function');
  assert.equal(native.image.closed, true);
  assert.notEqual(ui.loader.get(1).version, native.version);
  ui.loader.destroy();
});

test('compact Tour frames can switch renderer by source scale without changing the byte budget', async () => {
  const ui = setup({ nativeImages: (index) => index < 3 });
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(1).image.close, undefined);
  assert.equal(typeof ui.loader.get(3).image.close, 'function');
  ui.advance(); ui.loader.update(100); await settle();
  assert.equal(typeof ui.loader.get(100).image.close, 'function');
  assert.equal(ui.loader.bytes <= 96 * 1024 * 1024, true);
  ui.loader.destroy();
  assert.equal(ui.images.every((image) => image.closed), true);
});

test('desktop JPEG loads publish immediately without speculative native decode bookkeeping', async () => {
  const ui = setup({ compact: false, nativeImages: () => true });
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(6).index, 6);
  assert.equal(ui.warms.length, 0);
  assert.equal(ui.requests.length, 7);
  ui.loader.destroy();
});

test('unsupported desktop bitmap decoding falls back once to native images', async () => {
  const ui = setup({ compact: false, bitmapError: true });
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(1).image.close, undefined);
  assert.equal(ui.loader.nativeFallback.has(1), true);
  assert.equal(ui.loader.failures.size, 0);
  ui.loader.destroy();
});

test('a menu opened before the shared module arrives keeps the loader paused until it closes', async () => {
  const ui = setup({ menuOpen: true });
  ui.loader.update(0); await settle();
  assert.equal(ui.requests.length, 0);
  ui.fire('dd:menu-toggle', { detail: { open: false } }); await settle();
  assert.equal(ui.loader.get(0).index, 0);
  ui.loader.destroy();
  ui.loader.failed(0);
  assert.equal(ui.timers.size, 0);
});

test('native images retain their source URL until eviction and release it on destruction', async () => {
  const ui = setup();
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(0).index, 0);
  assert.equal(ui.revoked.length, 0);
  const image = ui.loader.get(0).image;
  ui.loader.destroy();
  assert.equal(image.src, '');
  assert.equal(ui.revoked.length, ui.images.length);
});

test('images without decode support use the existing load event and still release their URLs', async () => {
  const ui = setup({ legacyDecode: true });
  ui.loader.update(0); await settle();
  assert.equal(ui.loader.get(0).index, 0);
  ui.loader.destroy();
  assert.equal(ui.revoked.length, ui.images.length);
});

test('an exact cache hit and a budget check with room do not scan or sort cached entries', async () => {
  const ui = setup();
  ui.loader.update(0); await settle();
  ui.loader.priority = () => assert.fail('No eviction ordering is needed');
  ui.loader.cache[Symbol.iterator] = () => assert.fail('No nearest-frame scan is needed');
  const selected = ui.loader.get(2);
  assert.equal(ui.loader.get(2), selected);
  assert.equal(ui.loader.makeRoom(0), true);
  ui.loader.destroy();
});

test('HTTP errors retry once, settle the loader and do not produce an endless request loop', async () => {
  const ui = setup({ fail: true });
  ui.loader.update(0); await settle();
  ui.retry(); await settle();
  ui.retry(); await settle();
  assert.equal(ui.requests.filter((r) => r.index === 0).length, 2);
  assert.equal(ui.loader.requests.size, 0);
  assert.equal(ui.loader.cache.size, 0);
  assert.equal(ui.progress.at(-1)[1], true);
  ui.loader.destroy();
});
