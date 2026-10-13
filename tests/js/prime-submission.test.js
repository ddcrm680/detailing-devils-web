import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const source = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public/assets/js/modules/prime.js'), 'utf8');

function loadPrime(submissionEnabled) {
  const calls = [];
  const handlers = new Map();
  const values = new Map([['#first_name', 'Asha'], ['#otpCode', '123456']]);
  let submitHandler;
  const browserWindow = {
    intlTelInput: () => ({ isValidNumber: () => true, getNumber: () => '+919876543210' }),
  };

  function $(selector) {
    const key = selector === browserWindow ? 'window' : selector;
    const element = {
      length: 1,
      attr(name) {
        if (key === '#franchiseForm') {
          return {
            'data-submit-url': '/franchise/prime',
            'data-send-otp-url': '/otp/send',
            'data-verify-otp-url': '/otp/verify',
            'data-submission-enabled': String(submissionEnabled),
          }[name];
        }
        return this;
      },
      validate(options) {
        submitHandler = options.submitHandler;
        return { element: () => true, resetElements() {}, showErrors() {} };
      },
      on(event, callback) { handlers.set(`${key}:${event}`, callback); return this; },
      each() { return this; },
      val(value) {
        if (arguments.length) { values.set(key, value); return this; }
        return values.get(key) ?? '';
      },
      offset() { return { top: 0 }; },
      scrollTop() { return 0; },
      hasClass() { return false; },
      is() { return true; },
    };
    for (const method of ['removeClass', 'addClass', 'toggleClass', 'css', 'text', 'find', 'prop', 'hide', 'animate']) {
      element[method] = () => element;
    }
    return element;
  }

  $.validator = { addMethod() {} };
  vm.runInNewContext(source, {
    jQuery: $, window: browserWindow, document: { querySelector: () => ({}) },
    axios: { post(url, payload) { calls.push({ url, payload }); return new Promise(() => {}); } },
    Promise,
  });

  return { calls, submit: () => submitHandler(), inputOtp: () => handlers.get('#otpCode:input').call({ value: '123456' }) };
}

test('disabled Prime preview cannot start OTP by form submit or OTP input', () => {
  const prime = loadPrime(false);
  assert.equal(prime.submit(), false);
  prime.inputOtp();
  assert.equal(prime.calls.length, 0);
});

test('enabled Prime form keeps the existing single in-flight OTP send', () => {
  const prime = loadPrime(true);
  assert.equal(prime.submit(), false);
  assert.equal(prime.submit(), false);
  assert.equal(prime.calls.length, 1);
  assert.equal(prime.calls[0].url, '/otp/send');
  assert.equal(prime.calls[0].payload.purpose, 'prime');
});
