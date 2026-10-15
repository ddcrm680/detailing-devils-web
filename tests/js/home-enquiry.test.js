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

function setup(requestResponse) {
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
    querySelector(selector) {
      if (selector === '#form') return form;
      if (selector === '#note') return note;
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
  const context = vm.createContext({ document, axios, Swal, Blob, FormData: class { constructor(value) { this.form = value; } } });
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
    requests, notifications, button, note, fields, feedback, axios,
    submit: () => form.submit({ preventDefault() {}, target: form }),
    resetCount: () => resets,
  };
}

test('homepage loads one enquiry handler after the common scripts', () => {
  assert.equal(homeSource.includes("getElementById('form').addEventListener('submit'"), false);
  assert.equal(homepage.match(/assets\/js\/modules\/enquiry\.js/g)?.length, 1);
  for (const field of ['name', 'phone', 'city', 'car', 'interest']) {
    assert.ok(homepage.includes(`data-error-for="${field}"`));
  }
});

test('one pending enquiry uses shared Axios and the shared success notification', async () => {
  let resolve;
  const page = setup(() => new Promise((done) => { resolve = done; }));
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
  const page = setup(() => { throw new Error('should not request'); });
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
  const page = setup(async () => {
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
  const page = setup(async () => { throw responses.shift(); });
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
  const page = setup(async () => { throw new Error('offline'); });
  await page.submit();
  assert.equal(page.notifications.length, 1);
  assert.equal(page.notifications[0].titleText, 'Network Error');
  assert.equal(page.fields.name.value, 'Asha');
  assert.equal(page.resetCount(), 0);
  assert.equal(page.button.disabled, false);
});

test('an unsuccessful 200 response shows one error notification without resetting', async () => {
  const page = setup(async () => ({ data: { success: false, message: 'Please try again.' } }));
  await page.submit();
  assert.equal(page.notifications.length, 1);
  assert.equal(page.notifications[0].text, 'Please try again.');
  assert.equal(page.resetCount(), 0);
});
