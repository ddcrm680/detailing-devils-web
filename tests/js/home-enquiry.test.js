import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const source = read('public/assets/js/modules/enquiry.js');
const homeSource = read('public/assets/js/modules/home.js');
const homepage = read('resources/views/home.blade.php');

async function setup(requestResponse, { phoneValue = '+919876543210', phoneInit = Promise.resolve(), phoneLibrary = true, historyState = null, fullscreenPhone = false } = {}) {
  const requests = [];
  const notifications = [];
  let resets = 0;
  let rejectResponse;
  const button = { disabled: false };
  const note = { textContent: '' };
  const fields = Object.fromEntries(['name', 'phone', 'city', 'car', 'interest'].map((name) => {
    const classes = new Set();
    return [name, {
      value: name === 'name' ? 'Asha' : name === 'phone' ? '+919876543210' : '',
      classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value), contains: (value) => classes.has(value) },
    }];
  }));
  const feedback = Object.fromEntries(Object.keys(fields).map((name) => [name, { textContent: '' }]));
  fields.phone.value = phoneValue;
  fields.phone.listeners = {};
  fields.phone.addEventListener = (event, handler) => { fields.phone.listeners[event] = handler; };
  const dropdown = {
    style: { width: '522px' },
    setAttribute(name, value) { this[name] = value; },
    closest: () => fullscreenPhone ? null : {},
  };
  const phoneIti = { promise: phoneInit, isValidNumber: () => true, getNumber: () => '+919876543210', getSelectedCountry: () => ({ iso2: historyState?.enquiryPhoneCountry || 'in' }) };
  let phoneOptions;
  const form = {
    action: '/enquiry',
    addEventListener(event, handler) { if (event === 'submit') this.submit = handler; },
    querySelector(selector) {
      if (selector === 'button[type=submit]') return button;
      if (selector === '#n') return fields.name;
      if (selector === '#p') return fields.phone;
      const fieldName = selector.match(/^\[name="([^"]+)"\]/)?.[1];
      if (fieldName) return fields[fieldName];
      const errorName = selector.match(/^\[data-error-for="([^"]+)"\]/)?.[1];
      return errorName ? feedback[errorName] : null;
    },
    querySelectorAll(selector) {
      if (selector === '.invalid-feedback') return Object.values(feedback);
      if (selector === '.is-invalid') return Object.values(fields).filter((field) => field.classList.contains('is-invalid'));
      return [];
    },
    reset() { resets++; },
  };
  const document = {
    body: {},
    querySelector(selector) {
      if (selector === '#form') return form;
      if (selector === '#note') return note;
      if (selector === '.enquiry-phone .iti__country-selector') return dropdown;
      if (selector === 'meta[name="csrf-token"]') return { getAttribute: () => 'csrf-token' };
      return null;
    },
  };
  const axios = {
    defaults: { headers: { common: {} } },
    interceptors: { response: { use(_resolve, reject) { rejectResponse = reject; } } },
    post(url, body, config) {
      requests.push({ url, body, config });
      return Promise.resolve().then(requestResponse).catch((error) => rejectResponse({ ...error, config }));
    },
  };
  const Swal = { fire: (options) => notifications.push(options) };
  const listeners = {};
  const history = { state: historyState, replaceState(value) { this.state = value; } };
  const context = vm.createContext({
    document, axios, Swal, Blob, history,
    addEventListener(event, handler) { listeners[event] = handler; },
    intlTelInput: phoneLibrary ? (input, options) => { assert.equal(input, fields.phone); phoneOptions = options; return phoneIti; } : undefined,
    FormData: class {
      constructor(value) { this.form = value; this.values = new Map(Object.entries(fields).map(([name, field]) => [name, field.value])); }
      set(name, value) { this.values.set(name, value); }
      get(name) { return this.values.get(name); }
    },
  });
  context.window = context;
  for (const file of [
    'public/assets/js/common/dom.js',
    'public/assets/js/common/axios.js',
    'public/assets/js/common/notification.js',
    'public/assets/js/common/validation.js',
    'public/assets/js/common/response.js',
  ]) vm.runInContext(read(file), context);
  vm.runInContext(source, context);

  return {
    requests, notifications, button, note, fields, feedback, axios, phoneIti, phoneOptions, history, dropdown, listeners,
    submit: () => form.submit({ preventDefault() {}, target: form }),
    resetCount: () => resets,
  };
}

test('homepage loads one enquiry handler after the common scripts', () => {
  assert.equal(homeSource.includes("getElementById('form').addEventListener('submit'"), false);
  assert.equal(homepage.match(/assets\/js\/modules\/enquiry\.js/g)?.length, 1);
  assert.equal(homepage.match(/intl-tel-input@29\.1\.2\/dist\/js\/intlTelInput\.min\.js/g)?.length, 1);
  assert.equal(homepage.match(/intl-tel-input@29\.1\.2\/dist\/css\/intlTelInput\.css/g)?.length, 1);
  for (const field of ['name', 'phone', 'city', 'car', 'interest']) {
    assert.ok(homepage.includes(`data-error-for="${field}"`));
  }
});

test('one pending enquiry uses shared Axios and the shared success notification', async () => {
  let resolve;
  const page = await setup(() => new Promise((done) => { resolve = done; }));
  const first = page.submit();
  await page.submit();
  assert.equal(page.requests.length, 1);
  assert.equal(page.button.disabled, true);
  assert.equal(page.requests[0].url, '/enquiry');
  assert.equal(page.requests[0].body.form.action, '/enquiry');
  assert.equal(page.requests[0].config.skipGlobalErrorHandler, true);
  assert.equal(page.axios.defaults.headers.common['X-CSRF-TOKEN'], 'csrf-token');
  assert.equal(page.axios.defaults.headers.common.Accept, 'application/json');
  assert.match(page.note.textContent, /Sending/);

  resolve({ data: { success: true, message: 'Saved' } });
  await first;
  assert.equal(page.note.textContent, '');
  assert.equal(page.resetCount(), 1);
  assert.equal(page.button.disabled, false);
  assert.equal(page.notifications.length, 1);
  assert.equal(page.notifications[0].text, 'Saved');
});

test('local required fields use shared validation without a request', async () => {
  const page = await setup(() => { throw new Error('should not request'); });
  page.fields.name.value = '';
  await page.submit();
  assert.equal(page.requests.length, 0);
  assert.match(page.feedback.name.textContent, /Add your name/);
  assert.equal(page.fields.name.classList.contains('is-invalid'), true);
  assert.equal(page.notifications.length, 0);
  assert.equal(page.note.textContent, '');
});

test('a retry clears old field errors before a successful submission', async () => {
  let attempts = 0;
  const page = await setup(async () => {
    if (++attempts === 1) throw { response: { status: 422, data: { errors: { city: ['City is too long.'] } } } };
    return { data: { success: true, message: 'Saved' } };
  });
  await page.submit();
  assert.equal(page.feedback.city.textContent, 'City is too long.');
  await page.submit();
  assert.equal(page.feedback.city.textContent, '');
  assert.equal(page.fields.city.classList.contains('is-invalid'), false);
  assert.equal(page.notifications.length, 1);
  assert.equal(page.resetCount(), 1);
});

test('503 shows the server message once and 422 shows field feedback without a toast', async () => {
  const responses = [
    { response: { status: 503, data: { success: false, message: 'Enquiry submissions are temporarily unavailable.' } } },
    { response: { status: 422, data: { errors: { name: ['The name may not be greater than 50 characters.'] } } } },
  ];
  const page = await setup(async () => { throw responses.shift(); });
  await page.submit();
  assert.equal(page.notifications.length, 1);
  assert.match(page.notifications[0].text, /temporarily unavailable/);
  assert.equal(page.fields.name.value, 'Asha');
  assert.equal(page.button.disabled, false);

  await page.submit();
  assert.match(page.feedback.name.textContent, /greater than 50/);
  assert.equal(page.fields.name.classList.contains('is-invalid'), true);
  assert.equal(page.notifications.length, 1);
  assert.equal(page.resetCount(), 0);
});

test('network failure uses the shared error notification and preserves fields', async () => {
  const page = await setup(async () => { throw new Error('offline'); });
  await page.submit();
  assert.equal(page.notifications.length, 1);
  assert.equal(page.notifications[0].titleText, 'Network Error');
  assert.equal(page.fields.name.value, 'Asha');
  assert.equal(page.resetCount(), 0);
  assert.equal(page.button.disabled, false);
});

test('an unsuccessful 200 response shows one error notification without resetting', async () => {
  const page = await setup(async () => ({ data: { success: false, message: 'Please try again.' } }));
  await page.submit();
  assert.equal(page.notifications.length, 1);
  assert.equal(page.notifications[0].text, 'Please try again.');
  assert.equal(page.resetCount(), 0);
});

test('enquiry defaults to Prime-style India dialing and sends E.164 without changing other fields', async () => {
  const page = await setup(async () => ({ data: { success: true } }), { phoneValue: '98765 43210' });
  assert.equal(page.phoneOptions.initialCountry, 'in');
  assert.equal(page.phoneOptions.separateDialCode, true);
  assert.equal(page.phoneOptions.strictMode, true);
  assert.equal(page.phoneOptions.numberDisplayFormat, 'NATIONAL');
  page.fields.city.value = 'Fixture city';
  page.fields.car.value = 'Fixture car';
  page.fields.interest.value = 'Ceramic Coating';
  await page.submit();
  assert.equal(page.requests[0].body.get('phone'), '+919876543210');
  assert.equal(page.requests[0].body.get('name'), 'Asha');
  for (const name of ['city', 'car', 'interest']) assert.equal(page.requests[0].body.get(name), page.fields[name].value);
});

test('international enquiry keeps its national input and selected country after a server validation error', async () => {
  const page = await setup(async () => { throw { response: { status: 422, data: { errors: { phone: ['Check this phone number.'] } } } }; },
    { phoneValue: '07123456789', historyState: { enquiryPhoneCountry: 'gb', otherState: 'preserved' } });
  page.phoneIti.getNumber = () => '+447123456789';
  page.fields.phone.listeners.countrychange();
  assert.equal(page.phoneOptions.initialCountry, 'gb');
  assert.deepEqual({ ...page.history.state }, { enquiryPhoneCountry: 'gb', otherState: 'preserved' });
  await page.submit();
  assert.equal(page.requests[0].body.get('phone'), '+447123456789');
  assert.equal(page.fields.phone.value, '07123456789');
  assert.equal(page.feedback.phone.textContent, 'Check this phone number.');
  assert.equal(page.resetCount(), 0);
});

test('enquiry passes an existing international number to the library without replacing it', async () => {
  const page = await setup(async () => ({}), { phoneValue: '+447123456789' });
  assert.equal(page.fields.phone.value, '+447123456789');
});

test('enquiry handles phone loading, unavailable assets, invalid input and missing phone without submitting', async () => {
  let finishLoading;
  const loading = await setup(async () => ({ data: { success: true } }), { phoneInit: new Promise(resolve => { finishLoading = resolve; }) });
  await loading.submit();
  assert.match(loading.feedback.phone.textContent, /loading/);
  assert.equal(loading.requests.length, 0);
  assert.equal(loading.button.disabled, false);
  finishLoading();
  await Promise.resolve();
  await loading.submit();
  assert.equal(loading.requests.length, 1);

  for (const options of [() => ({ phoneLibrary: false }), () => ({ phoneInit: Promise.reject(new Error('offline')) })]) {
    const page = await setup(async () => ({}), options());
    await Promise.resolve();
    await page.submit();
    assert.match(page.feedback.phone.textContent, /could not load/);
    assert.equal(page.requests.length, 0);
    assert.equal(page.fields.phone.value, '+919876543210');
  }
  const invalid = await setup(async () => ({}));
  invalid.phoneIti.isValidNumber = () => false;
  await invalid.submit();
  assert.match(invalid.feedback.phone.textContent, /valid phone number for the selected country/);
  assert.equal(invalid.requests.length, 0);
  invalid.fields.phone.value = '';
  await invalid.submit();
  assert.equal(invalid.feedback.phone.textContent, 'Add your phone number so the studio can call you.');
});

test('enquiry refreshes detached dropdown width on open and resize and lets Lenis leave its scrolling alone', async () => {
  const page = await setup(async () => ({}));
  for (const width of [342, 273, 522]) {
    page.fields.phone.offsetWidth = width;
    page.fields.phone.listeners['open:countryselector']();
    page.listeners.resize();
    assert.equal(page.dropdown.style.width, `${width}px`);
    assert.equal(page.dropdown['data-lenis-prevent'], '');
  }
  const mobile = await setup(async () => ({}), { fullscreenPhone: true });
  mobile.fields.phone.offsetWidth = 273;
  mobile.fields.phone.listeners['open:countryselector']();
  mobile.listeners.resize();
  assert.equal(mobile.dropdown.style.width, '522px');
});
