(() => {
  const form = window.$('#fofo-form');
  if (!form) return;

  const country = form.querySelector('#country');
  const state = form.querySelector('#state');
  const city = form.querySelector('#city');
  const initialLocation = { country: country.value, state: state.value, city: city.value };
  const pendingLocation = { ...initialLocation };
  const manualLocation = form.querySelector('#fofo-location-manual');
  const selectedLocation = form.querySelector('#fofo-location-selects');
  const status = form.querySelector('#fofo-form-status');
  const emailInput = form.querySelector('#email');
  const emailFeedback = form.querySelector('[data-error-for="email"]');
  const phoneInput = form.querySelector('#phone');
  const phoneIti = window.intlTelInput?.(phoneInput, {
    initialCountry: window.history.state?.fofoPhoneCountry || 'in',
    separateDialCode: true,
    strictMode: true,
    numberDisplayFormat: 'NATIONAL',
    allowedNumberTypes: ['MOBILE', 'FIXED_LINE_OR_MOBILE'],
    containerClass: 'fofo-phone',
    loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/js/utils.js'),
  });
  let phoneReady = false;
  let phoneUnavailable = !phoneIti;
  phoneIti?.promise.then(() => {
    phoneReady = true;
    rememberPhoneCountry();
  }).catch(() => {
    phoneUnavailable = true;
  });
  let emailRequestVersion = 0;
  let emailCheckStatus = '';
  let emailCheckedValue = '';
  let submitting = false;
  let submitted = false;
  const submitButton = form.querySelector('#fofo-submit');
  const submissionEnabled = !submitButton.disabled;

  if (phoneIti) {
    // The library caches the inline dropdown width at initialization.
    window.addEventListener('resize', () => {
      const dropdown = phoneInput.closest('.iti--inline-country-selector')?.querySelector('.iti__country-selector');
      if (dropdown) dropdown.style.width = `${phoneInput.offsetWidth}px`;
    });
  }

  function rememberPhoneCountry() {
    // Keep the dial code paired with browser-restored national input on Back.
    window.history.replaceState({
      ...window.history.state,
      fofoPhoneCountry: phoneIti.getSelectedCountry().iso2,
    }, '');
  }

  function setError(input, message = '') {
    const inputs = input.type === 'radio'
      ? form.querySelectorAll(`input[name="${input.name}"]`)
      : [input];

    if (message) {
      window.showValidationErrors(form, { [input.name]: [message] });
      inputs.forEach((control) => control.setAttribute('aria-invalid', 'true'));
      return;
    }

    const error = form.querySelector(`[data-error-for="${input.name}"]`);
    if (error) error.textContent = '';
    inputs.forEach((control) => {
      control.classList.remove('is-invalid');
      control.removeAttribute('aria-invalid');
    });
  }

  function toggleFields(container, visible) {
    container.hidden = !visible;
    container.querySelectorAll('input, select, textarea').forEach((input) => {
      input.disabled = !visible;
      input.required = visible && input.hasAttribute('data-conditional-required');
      if (!visible) setError(input);
    });
  }

  window.initLocationDropdowns({
    country,
    state,
    city,
    urls: {
      countries: form.dataset.countriesUrl,
      states: form.dataset.statesUrl,
      cities: form.dataset.citiesUrl,
    },
    onCountryChange(value) {
      if (value !== initialLocation.country) {
        pendingLocation.state = '';
        pendingLocation.city = '';
      }
      const manual = value === 'other';
      selectedLocation.hidden = manual;
      toggleFields(manualLocation, manual);
    },
    onStatus(select, locationStatus) {
      if (select === country && ['ready', 'empty', 'error'].includes(locationStatus)) {
        country.add(new Option('Other country (enter manually)', 'other'));
        country.disabled = false;
      }

      if (select === state && locationStatus === 'selected' && state.value !== initialLocation.state) {
        pendingLocation.city = '';
      }

      if (locationStatus === 'ready' || (select === country && ['empty', 'error'].includes(locationStatus))) {
        // Restore existing values before applying FOFO's first-load country default.
        const value = select.value || pendingLocation[select.name] || (select === country
          ? [...country.options].find((option) => option.text.trim().toLowerCase() === 'india')?.value
          : '');
        delete pendingLocation[select.name];
        if (value && [...select.options].some((option) => option.value === value)) {
          select.value = value;
          select.dispatchEvent(new Event('change'));
        }
      }

      if (locationStatus === 'error') {
        const message = select === country
          ? 'Countries could not be loaded. Choose Other country or reload this page.'
          : select === state
            ? 'States could not be loaded. Please try selecting the country again.'
            : 'Cities could not be loaded. Please try selecting the state again.';
        setError(select, message);
      } else if (locationStatus === 'empty' && select !== country) {
        setError(select, select === state
          ? 'No states found. Choose Other country to enter your location manually.'
          : 'No cities found. Choose Other country to enter your location manually.');
      } else if (locationStatus !== 'loading') {
        setError(select);
      }
    },
  });

  [
    ['bussinessowner', 'fofo-business-details'],
    ['experienceautomotive', 'fofo-experience-details'],
    ['existingautomotive', 'fofo-facility-details'],
    ['spacearound', 'fofo-property-details'],
  ].forEach(([selectId, detailsId]) => {
    const select = form.querySelector(`#${selectId}`);
    const details = form.querySelector(`#${detailsId}`);
    select.addEventListener('change', () => toggleFields(details, select.value === 'YES'));
  });

  const dob = form.querySelector('#dob');
  const today = new Date();
  today.setDate(today.getDate() - 1);
  dob.max = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  async function validateField(input) {
    if (input.disabled || !input.name || input.name === '_token') return true;

    let message = '';
    const value = input.value.trim();

    if (input.type === 'radio') {
      if (!form.querySelector('input[name="spendfranchise"]:checked')) message = 'Choose how much time you can spend.';
    } else if (input.type === 'file') {
      if (input.required && !input.files.length) message = 'Select a location photo.';
      else if (input.files.length) {
        const maxSize = 10000 * 1024;
        if (input.files[0].size > maxSize) {
          message = 'Image size must not exceed 10,000 KB.';
        } else {
          const result = await window.validateImage(input.files[0], {
            maxSize, maxWidth: 10240, maxHeight: 7680,
          });
          if (!result.valid) message = result.message;
        }
      }
    } else if (input.required && !value) {
      message = 'This field is required.';
    } else if (value && typeof input.maxLength === 'number' && input.maxLength >= 0 && value.length > input.maxLength) {
      message = `Use ${input.maxLength} characters or fewer.`;
    } else if (value && (input.name === 'name' || input.name === 'lname')
      && (value.length < 2 || !/^\p{L}[\p{L}\s'.-]*$/u.test(value))) {
      message = 'Enter a valid name of at least two characters.';
    } else if (value && input.name === 'lname'
      && `${form.querySelector('#name').value.trim()} ${value}`.trim().length > 100) {
      message = 'Your full name must be 100 characters or fewer.';
    } else if (value && input.type === 'email' && !input.validity.valid) {
      message = 'Enter a valid email address.';
    } else if (value && input.type === 'url' && (!input.validity.valid || !/^https?:\/\//i.test(value))) {
      message = 'Enter a complete website URL, including https://.';
    } else if (value && input.type === 'date' && (!input.validity.valid || value > dob.max)) {
      message = 'Enter a valid date of birth in the past.';
    } else if (value && input.name === 'phone') {
      if (phoneUnavailable) message = 'Phone verification could not load. Please reload this page.';
      else if (!phoneReady) message = 'Phone verification is loading. Please try again in a moment.';
      else if (!phoneIti.isValidNumber()) message = 'Enter a valid mobile number for the selected country.';
    }

    setError(input, message);
    return !message;
  }

  function setEmailFeedback(feedbackStatus, message) {
    if (feedbackStatus === 'valid' || feedbackStatus === 'validating') {
      setError(emailInput);
      emailFeedback.textContent = message;
    } else {
      setError(emailInput, message);
    }
    emailFeedback.dataset.emailStatus = feedbackStatus;
  }

  function hasValidatedCurrentEmail() {
    return emailCheckStatus === 'valid' && emailCheckedValue === emailInput.value.trim();
  }

  async function checkEmail() {
    const email = emailInput.value.trim();
    const observedVersion = emailRequestVersion;

    if (!await validateField(emailInput)) {
      emailCheckStatus = '';
      emailCheckedValue = '';
      return;
    }
    if (observedVersion !== emailRequestVersion || emailInput.value.trim() !== email) return;

    if (hasValidatedCurrentEmail()) {
      setEmailFeedback('valid', 'Valid email.');
      return;
    }
    if (emailCheckStatus === 'invalid' && emailCheckedValue === email) {
      setEmailFeedback('invalid', 'Invalid email or not verified as deliverable.');
      return;
    }
    if (emailCheckStatus === 'validating' && emailCheckedValue === email) {
      setEmailFeedback('validating', 'Validating email…');
      return;
    }

    emailCheckStatus = 'validating';
    emailCheckedValue = email;
    const requestVersion = ++emailRequestVersion;
    setEmailFeedback('validating', 'Validating email…');

    try {
      const response = await axios.post(form.dataset.emailValidationUrl, { email }, {
        skipGlobalErrorHandler: true,
      });
      if (requestVersion !== emailRequestVersion || emailInput.value.trim() !== email) return;

      if (response.data?.success === true && response.data?.result === 'valid') {
        emailCheckStatus = 'valid';
        setEmailFeedback('valid', 'Valid email.');
      } else if (response.data?.result === 'invalid') {
        emailCheckStatus = 'invalid';
        setEmailFeedback('invalid', 'Invalid email or not verified as deliverable.');
      } else {
        emailCheckStatus = 'unavailable';
        setEmailFeedback('unavailable', 'Unable to verify email right now. Please try again.');
      }
    } catch (error) {
      if (requestVersion !== emailRequestVersion || emailInput.value.trim() !== email) return;
      emailCheckStatus = error.response?.status === 422 ? 'invalid' : 'unavailable';
      setEmailFeedback(emailCheckStatus, emailCheckStatus === 'invalid'
        ? 'Invalid email address.'
        : 'Unable to verify email right now. Please try again.');
    }
  }

  async function reviewFields() {
    let firstInvalid = null;
    const checkedRadios = new Set();

    for (const input of form.querySelectorAll('input, select, textarea')) {
      if (input.name === '_token') continue;
      if (input.disabled) {
        if (input === country) {
          setError(country, 'Wait for countries to load.');
          if (!firstInvalid) firstInvalid = form.querySelector('#fofo-review');
        } else if (input === state && country.value && country.value !== 'other') {
          setError(state, 'Choose a country with available states, or select Other country.');
          if (!firstInvalid) firstInvalid = country;
        } else if (input === city && state.value) {
          setError(city, 'Choose a state with available cities, or select Other country.');
          if (!firstInvalid) firstInvalid = state;
        }
        continue;
      }
      if (input.type === 'radio') {
        if (checkedRadios.has(input.name)) continue;
        checkedRadios.add(input.name);
      }
      const locallyValid = await validateField(input);
      if (!locallyValid && !firstInvalid) firstInvalid = input;
      if (input === emailInput && locallyValid) {
        if (hasValidatedCurrentEmail()) {
          setEmailFeedback('valid', 'Valid email.');
        } else {
          const invalid = emailCheckStatus === 'invalid' && emailCheckedValue === input.value.trim();
          setEmailFeedback(invalid ? 'invalid' : 'unavailable', invalid
            ? 'Invalid email or not verified as deliverable.'
            : emailCheckStatus === 'validating'
              ? 'Email validation is still in progress.'
              : 'Unable to verify email. Leave this field to check it again.');
          if (!firstInvalid) firstInvalid = input;
        }
      }
    }

    if (firstInvalid) {
      status.textContent = 'Please check the highlighted fields. Nothing has been submitted.';
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus({ preventScroll: true });
        return false;
    } else {
      status.textContent = submissionEnabled
        ? 'The visible fields look complete. Your application is ready to submit.'
        : 'The visible fields look complete. Final submission is not active; nothing has been sent or saved.';
      return true;
    }
  }

  async function submitApplication() {
    if (submitting || submitted || submitButton.disabled) return;
    submitting = true;
    try {
      if (!await reviewFields()) return;

      submitButton.disabled = true;
      status.textContent = 'Submitting your application…';
      const application = new FormData(form);
      // Keep the visible national number; send the same E.164 format used by Prime.
      application.set('phone', phoneIti.getNumber());
      const response = await axios.post(form.dataset.submitUrl, application, {
        skipGlobalErrorHandler: true,
      });

      if (response.data?.success !== true) {
        status.textContent = response.data?.message || 'Unable to submit your application. Please try again.';
        return;
      }

      status.textContent = response.data.message || 'Your application has been received.';
      submitted = true;
      submitButton.disabled = true;
      if (response.data.created === true) {
        // Leave the confirmation visible before a clean GET, never a POST retry.
        window.setTimeout(() => {
          form.reset();
          const historyState = { ...window.history.state };
          delete historyState.fofoPhoneCountry;
          window.history.replaceState(historyState, '');
          window.location.reload();
        }, 5000);
      }
      window.handleResponseSuccess({ title: 'Application received', message: status.textContent });
    } catch (error) {
      if (submitted) return;
      const errors = error.response?.data?.errors;
      if (error.response?.status === 422 && errors) {
        window.showValidationErrors(form, errors);
        if (errors.email) {
          emailCheckStatus = '';
          emailCheckedValue = '';
          emailFeedback.dataset.emailStatus = 'unavailable';
        }
        status.textContent = error.response.data.message || 'Please check the highlighted fields.';
        const firstField = form.querySelector(`[name="${Object.keys(errors)[0]}"]`);
        if (firstField) {
          firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstField.focus({ preventScroll: true });
        }
      } else {
        status.textContent = 'Your application could not be submitted. Please try again later.';
        void window.handleResponseError(error);
      }
    } finally {
      submitting = false;
      if (!submitted) {
        submitButton.disabled = false;
      }
    }
  }

  form.addEventListener('focusout', (event) => {
    if (event.target === emailInput) {
      void checkEmail();
      return;
    }
    if (event.target.matches('input:not([type="radio"]):not([type="file"]), select, textarea')) {
      void validateField(event.target);
    }
  });
  emailInput.addEventListener('input', () => {
    emailRequestVersion++;
    emailCheckStatus = '';
    emailCheckedValue = '';
    setError(emailInput);
    delete emailFeedback.dataset.emailStatus;
  });
  phoneInput.addEventListener('countrychange', () => {
    rememberPhoneCountry();
    if (phoneReady && phoneInput.value.trim()) void validateField(phoneInput);
  });
  form.addEventListener('change', (event) => {
    if (event.target.matches('input[type="radio"], input[type="file"]')) void validateField(event.target);
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    void submitApplication();
  });
  form.querySelector('#fofo-review').addEventListener('click', () => void reviewFields());

})();
