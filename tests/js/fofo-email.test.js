import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const source = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public/assets/js/modules/fofo.js'), 'utf8');
const tick = () => new Promise((resolve) => setImmediate(resolve));

function element(name = '', type = 'text') {
  const classes = new Set();
  return {
    name, type, value: '', disabled: false, required: false,
    validity: { valid: true }, dataset: {}, listeners: {},
    classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value), contains: (value) => classes.has(value) },
    addEventListener(event, callback) { this.listeners[event] = callback; },
    setAttribute(name, value) { this[name] = value; },
    removeAttribute(name) { delete this[name]; },
    scrollIntoView() {}, focus() {}, matches() { return true; },
  };
}

function setup(post, { submissionEnabled = false } = {}) {
  const email = element('email', 'email');
  email.required = true;
  const feedback = { textContent: '', dataset: {} };
  const name = element('name');
  const lname = element('lname');
  const phone = element('phone', 'tel');
  const website = element('website', 'url');
  const reason = element('newbussiness', 'textarea');
  const image = element('locationimage', 'file');
  image.files = [];
  reason.maxLength = 255;
  const controls = { email, name, lname, phone, website, newbussiness: reason, locationimage: image };
  const errors = Object.fromEntries(Object.keys(controls).map((field) => [field, { textContent: '' }]));
  errors.email = feedback;
  const status = { textContent: '' };
  const review = element();
  const submitButton = element();
  submitButton.disabled = !submissionEnabled;
  const fields = new Map([
    ...Object.entries(controls).map(([field, input]) => [`#${field}`, input]),
    ...Object.entries(errors).map(([field, error]) => [`[data-error-for="${field}"]`, error]),
    ['#fofo-form-status', status], ['#fofo-review', review], ['#fofo-submit', submitButton],
  ]);
  for (const id of ['country', 'state', 'city', 'dob', 'bussinessowner', 'experienceautomotive', 'existingautomotive', 'spacearound']) {
    fields.set(`#${id}`, element(id));
  }
  for (const id of ['fofo-location-manual', 'fofo-location-selects', 'fofo-business-details', 'fofo-experience-details', 'fofo-facility-details', 'fofo-property-details']) {
    fields.set(`#${id}`, { hidden: true, querySelectorAll: () => [] });
  }
  const calls = [];
  const imageChecks = [];
  const notifications = [];
  const form = {
    dataset: { emailValidationUrl: '/email/validate', submitUrl: '/franchise/fofo' }, listeners: {},
    querySelector: (selector) => fields.get(selector),
    querySelectorAll: (selector) => selector === 'input, select, textarea' ? Object.values(controls) : [],
    addEventListener(event, callback) { this.listeners[event] = callback; },
  };
  const window = {
    $: () => form,
    initLocationDropdowns() {},
    validateImage: async (_file, options) => { imageChecks.push(options); return { valid: true }; },
    showValidationErrors(_form, messages) {
      const [field, values] = Object.entries(messages)[0];
      errors[field].textContent = values[0];
      controls[field].classList.add('is-invalid');
    },
    handleResponseSuccess(response) { notifications.push({ type: 'success', response }); },
    handleResponseError(error) { notifications.push({ type: 'error', error }); },
  };
  vm.runInNewContext(source, { window, axios: { post(url, payload, options) {
    calls.push({ url, payload, options });
    return post(url, payload);
  } }, Date, FormData: class { constructor(target) { this.form = target; } } });

  const blur = () => form.listeners.focusout({ target: email });
  const input = (value) => { email.value = value; email.listeners.input(); };
  const reviewFields = () => review.listeners.click();
  const focusout = (target) => form.listeners.focusout({ target });
  const change = (target) => form.listeners.change({ target });
  const submit = () => form.listeners.submit({ preventDefault() {} });
  return { email, feedback, status, review, submitButton, calls, notifications, blur, input, reviewFields, submit, focusout, change, controls, errors, imageChecks };
}

test('validates on blur once and displays a valid result', async () => {
  let resolve;
  const view = setup(() => new Promise((done) => { resolve = done; }));
  view.input('person@example.com');
  view.blur();
  await tick();
  view.blur();
  await tick();
  assert.equal(view.calls.length, 1);
  assert.equal(view.calls[0].url, '/email/validate');
  assert.equal(view.calls[0].payload.email, 'person@example.com');
  assert.equal(view.calls[0].options.skipGlobalErrorHandler, true);
  assert.equal(view.feedback.dataset.emailStatus, 'validating');

  resolve({ data: { success: true, result: 'valid' } });
  await tick();
  assert.equal(view.feedback.dataset.emailStatus, 'valid');
  assert.equal(view.email.classList.contains('is-invalid'), false);
  view.blur();
  await tick();
  assert.equal(view.calls.length, 1);
});

test('ignores a stale result after the email changes', async () => {
  let resolve;
  const view = setup(() => new Promise((done) => { resolve = done; }));
  view.input('old@example.com');
  view.blur();
  await tick();
  view.input('new@example.com');
  resolve({ data: { success: true, result: 'valid' } });
  await tick();
  assert.equal(view.feedback.textContent, '');
  assert.equal(view.feedback.dataset.emailStatus, undefined);
});

test('shows invalid and unavailable outcomes inline without a submission', async () => {
  const invalid = setup(async () => ({ data: { success: false, result: 'invalid' } }));
  invalid.input('person@example.com');
  invalid.blur();
  await tick();
  assert.equal(invalid.feedback.dataset.emailStatus, 'invalid');
  assert.equal(invalid.email.classList.contains('is-invalid'), true);
  invalid.blur();
  await tick();
  assert.equal(invalid.calls.length, 1);

  const unavailable = setup(async () => { throw new Error('network'); });
  unavailable.input('person@example.com');
  unavailable.blur();
  await tick();
  assert.equal(unavailable.feedback.dataset.emailStatus, 'unavailable');
  assert.match(unavailable.feedback.textContent, /Unable to verify/);
  assert.equal(unavailable.calls.length, 1);
});

test('invalid local format never calls the endpoint', async () => {
  const view = setup(async () => ({ data: { success: true, result: 'valid' } }));
  view.input('bad-email');
  view.email.validity.valid = false;
  view.blur();
  await tick();
  assert.equal(view.calls.length, 0);
  assert.equal(view.email.classList.contains('is-invalid'), true);
});

test('maps server validation and throttling failures to inline feedback', async () => {
  for (const [httpStatus, expected] of [[422, 'invalid'], [429, 'unavailable']]) {
    const view = setup(async () => { throw { response: { status: httpStatus } }; });
    view.input('person@example.com');
    view.blur();
    await tick();
    assert.equal(view.feedback.dataset.emailStatus, expected);
  }
});

test('review gate accepts only the currently validated email', async () => {
  let resolve;
  const view = setup(() => new Promise((done) => { resolve = done; }));
  view.input('first@example.com');
  view.blur();
  await tick();
  view.reviewFields();
  await tick();
  assert.match(view.status.textContent, /highlighted fields/);

  resolve({ data: { success: true, result: 'valid' } });
  await tick();
  view.reviewFields();
  await tick();
  assert.match(view.status.textContent, /fields look complete/);

  view.input('second@example.com');
  assert.equal(view.feedback.dataset.emailStatus, undefined);
  view.reviewFields();
  await tick();
  assert.match(view.status.textContent, /highlighted fields/);

  view.email.value = 'first@example.com';
  view.reviewFields();
  await tick();
  assert.match(view.status.textContent, /highlighted fields/);
});

test('review gate stays blocked after invalid and unavailable results', async () => {
  for (const outcome of [
    () => ({ data: { success: false, result: 'invalid' } }),
    () => { throw new Error('network'); },
  ]) {
    const view = setup(async () => outcome());
    view.input('person@example.com');
    view.blur();
    await tick();
    view.reviewFields();
    await tick();
    assert.match(view.status.textContent, /highlighted fields/);
  }
});

test('review shows inline CRM length, name, phone and website errors', async () => {
  const view = setup(async () => ({ data: { success: true, result: 'valid' } }));
  const { name, lname, phone, website, newbussiness: reason } = view.controls;

  name.value = 'A';
  view.focusout(name);
  await tick();
  assert.match(view.errors.name.textContent, /at least two characters/);

  name.value = 'A'.repeat(50);
  lname.value = 'B'.repeat(50);
  view.focusout(lname);
  await tick();
  assert.match(view.errors.lname.textContent, /100 characters/);

  phone.value = '919876543210';
  view.focusout(phone);
  await tick();
  assert.match(view.errors.phone.textContent, /starting with \+/);

  website.value = 'ftp://example.com';
  view.focusout(website);
  await tick();
  assert.match(view.errors.website.textContent, /https:\/\//);

  reason.value = 'x'.repeat(256);
  view.focusout(reason);
  await tick();
  assert.match(view.errors.newbussiness.textContent, /255 characters/);
});

test('location image checks match Laravel size and dimension limits', async () => {
  const view = setup(async () => ({ data: { success: true, result: 'valid' } }));
  const image = view.controls.locationimage;
  image.required = true;
  image.files = [{ size: 10000 * 1024 + 1, type: 'image/png' }];
  view.change(image);
  await tick();
  assert.match(view.errors.locationimage.textContent, /10,000 KB/);
  assert.equal(view.imageChecks.length, 0);

  image.files = [{ size: 10000 * 1024, type: 'image/png' }];
  view.change(image);
  await tick();
  assert.equal(view.errors.locationimage.textContent, '');
  assert.equal(view.imageChecks.length, 1);
  assert.equal(view.imageChecks[0].maxSize, 10000 * 1024);
  assert.equal(view.imageChecks[0].maxWidth, 10240);
  assert.equal(view.imageChecks[0].maxHeight, 7680);
});

test('submission requires the currently validated email and sends one multipart request', async () => {
  let resolveSubmission;
  const view = setup((url) => url === '/email/validate'
    ? Promise.resolve({ data: { success: true, result: 'valid' } })
    : new Promise((resolve) => { resolveSubmission = resolve; }), { submissionEnabled: true });

  view.input('person@example.com');
  view.submit();
  await tick();
  assert.equal(view.calls.length, 0);

  view.blur();
  await tick();
  view.submit();
  view.submit();
  await tick();
  assert.equal(view.calls.filter((call) => call.url === '/franchise/fofo').length, 1);
  assert.equal(view.calls[1].payload.form.dataset.submitUrl, '/franchise/fofo');
  assert.equal(view.calls[1].options.skipGlobalErrorHandler, true);
  assert.equal(view.submitButton.disabled, true);

  resolveSubmission({ data: { success: true, created: true, message: 'Application submitted.' } });
  await tick();
  assert.equal(view.submitButton.disabled, true);
  assert.equal(view.notifications.filter((item) => item.type === 'success').length, 1);
  view.submit();
  await tick();
  assert.equal(view.calls.length, 2);
});

test('failed submission shows server field errors and restores the submit button', async () => {
  const view = setup((url) => url === '/email/validate'
    ? Promise.resolve({ data: { success: true, result: 'valid' } })
    : Promise.reject({ response: { status: 422, data: { message: 'Validation failed.', errors: { email: ['Validate your email again.'] } } } }),
  { submissionEnabled: true });
  view.input('person@example.com');
  view.blur();
  await tick();
  view.submit();
  await tick();

  assert.equal(view.calls.length, 2);
  assert.equal(view.submitButton.disabled, false);
  assert.equal(view.errors.email.textContent, 'Validate your email again.');
  assert.equal(view.notifications.length, 0);
});
