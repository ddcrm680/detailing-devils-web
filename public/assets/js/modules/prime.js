(function ($) {
    'use strict';

    const form = $('#franchiseForm');
    if (!form.length) return;

    const tyest = '';

    const API_URL = form.attr('data-submit-url');
    const SEND_OTP_API_URL = form.attr('data-send-otp-url');
    const VERIFY_OTP_API_URL = form.attr('data-verify-otp-url');
    const submissionEnabled = form.attr('data-submission-enabled') === 'true';


    let currentStep = 1;
    const totalSteps = 5;
    let otpSendRequest = null;
    let otpVerificationInFlight = false;
    let submissionInFlight = false;
    let applicationSubmitted = false;
    const mobileInput = document.querySelector('#mobile_number');
    const whatsappInput = document.querySelector('#whatsapp_number');

    const phoneOptions = {
        initialCountry: 'in',
        separateDialCode: true,
        strictMode: true,
        numberDisplayFormat: 'NATIONAL',
        loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/js/utils.js')
    };

    const mobileIti = window.intlTelInput(mobileInput, phoneOptions);
    const whatsappIti = window.intlTelInput(whatsappInput, phoneOptions);

    $('.select2').each(function () {
        const select = $(this);
        select.select2({
            width: '100%',
            placeholder: select.data('placeholder') || 'Select an option',
            allowClear: true
        });
    });

    $.validator.addMethod('personName', function (value, element) {
        return this.optional(element) || /^[\p{L}][\p{L}\s'.-]{1,49}$/u.test(value.trim());
    }, 'Enter a valid name.');

    $.validator.addMethod('crmFullName', function () {
        const name = [$('#first_name').val(), $('#middle_name').val(), $('#last_name').val()]
            .map(value => value.trim()).join(' ').trim();
        return Array.from(name).length <= 100;
    }, 'Your full name must be 100 characters or fewer.');

    $.validator.addMethod('cityName', function (value, element) {
        return this.optional(element) || /^[\p{L}][\p{L}\s'.()-]{1,79}$/u.test(value.trim());
    }, 'Enter a valid city.');

    $.validator.addMethod('indianPin', function (value, element) {
        return this.optional(element) || /^[1-9][0-9]{5}$/.test(value);
    }, 'Enter a valid 6-digit Indian PIN code.');

    $.validator.addMethod('intlPhone', function (value, element, itiInstance) {
        if (this.optional(element)) return true;
        return Boolean(itiInstance && itiInstance.isValidNumber());
    }, 'Enter a valid phone number.');

    $.validator.addMethod('contactMethodRequired', function () {
        return $('.contact-method:checked').length > 0;
    }, 'Choose at least one contact method.');

    const validator = form.validate({
        ignore: [],
        errorElement: 'label',
        errorClass: 'error',
        validClass: 'valid',
        focusInvalid: false,
        rules: {
            first_name: { required: true, personName: true },
            middle_name: { personName: true },
            last_name: { required: true, personName: true, crmFullName: true },
            mobile_number: { required: true, intlPhone: mobileIti },
            whatsapp_number: {
                required: { depends: function () { return !$('#same_as_mobile').is(':checked'); } },
                intlPhone: whatsappIti
            },
            email: { required: true, email: true, maxlength: 120 },
            age_group: { required: true },
            occupation: { required: true },
            city: { required: true, cityName: true },
            state: { required: true },
            pin_code: { indianPin: true },
            business_experience: { required: true },
            business_name: {
                required: { depends: function () { return $('#business_yes').is(':checked'); } },
                minlength: 2,
                maxlength: 120
            },
            industry: {
                required: { depends: function () { return $('#business_yes').is(':checked'); } },
                minlength: 2,
                maxlength: 100
            },
            property_status: { required: true },
            property_location: {
                required: { depends: function () { return $('#property_yes').is(':checked'); } },
                minlength: 3,
                maxlength: 180
            },
            carpet_area: {
                required: { depends: function () { return $('#property_yes').is(':checked'); } },
                number: true,
                min: 100,
                max: 100000
            },
            road_facing: {
                required: { depends: function () { return $('#property_yes').is(':checked'); } }
            },
            preferred_city: { maxlength: 80 },
            preferred_area: { maxlength: 120 },
            start_timeline: { required: true },
            decision_maker: { required: true },
            'contact_methods[]': { contactMethodRequired: true },
            partnership_reason: { maxlength: 255 },
            consent: { required: true }
        },
        messages: {
            first_name: { required: 'First name is required.' },
            last_name: { required: 'Last name is required.' },
            mobile_number: { required: 'Mobile number is required.' },
            whatsapp_number: { required: 'WhatsApp number is required.' },
            email: { required: 'Email address is required.', email: 'Enter a valid email address.' },
            age_group: { required: 'Select your age group.' },
            occupation: { required: 'Select your occupation.' },
            city: { required: 'City is required.' },
            state: { required: 'Select your state.' },
            business_experience: { required: 'Select an option.' },
            business_name: { required: 'Business name is required.' },
            industry: { required: 'Industry is required.' },
            property_status: { required: 'Select your property status.' },
            property_location: { required: 'Property location is required.' },
            carpet_area: { required: 'Carpet area is required.', min: 'Minimum carpet area is 100 sq. ft.', max: 'Enter a realistic carpet area.' },
            road_facing: { required: 'Select an option.' },
            start_timeline: { required: 'Select your expected start timeline.' },
            decision_maker: { required: 'Select an option.' },
            consent: { required: 'Please provide consent to continue.' }
        },
        errorPlacement: function (error, element) {
            const name = element.attr('name');

            if (element.hasClass('select2-hidden-accessible')) {
                error.insertAfter(element.next('.select2'));
                return;
            }

            if (name === 'business_experience') {
                error.insertAfter(element.closest('.choice-group').find('.row'));
                return;
            }

            if (name === 'property_status') {
                error.insertAfter(element.closest('.choice-group').find('.row'));
                return;
            }

            if (name === 'decision_maker') {
                error.insertAfter(element.closest('.choice-group').find('.row'));
                return;
            }

            if (name === 'road_facing') {
                error.insertAfter(element.closest('.segmented-control'));
                return;
            }

            if (name === 'contact_methods[]') {
                $('#contact_methods_error').removeClass('d-none').text(error.text());
                return;
            }

            if (element.attr('type') === 'tel') {
                error.insertAfter(element.closest('.iti'));
                return;
            }

            if (name === 'consent') {
                error.insertAfter(element.closest('.form-check'));
                return;
            }

            error.insertAfter(element);
        },
        showErrors: function (errors) {
            this.defaultShowErrors();

            if (errors['contact_methods[]']) {
                $('#contact_methods_error').removeClass('d-none').text(errors['contact_methods[]']).show();
            }
        },
        highlight: function (element) {
            const $element = $(element);
            $element.addClass('error');
            if ($element.hasClass('select2-hidden-accessible')) {
                $element.next('.select2').addClass('is-invalid');
            }
        },
        unhighlight: function (element) {
            const $element = $(element);
            $element.removeClass('error');
            if ($element.hasClass('select2-hidden-accessible')) {
                $element.next('.select2').removeClass('is-invalid');
            }
            if ($element.hasClass('contact-method') && $('.contact-method:checked').length > 0) {
                $('#contact_methods_error').addClass('d-none').text('');
            }
        },
        invalidHandler: function (_event, validation) {
            const firstError = validation.errorList[0];
            if (!firstError) return;

            const field = $(firstError.element);
            const step = Number(field.closest('.form-step').data('step')) || currentStep;
            showStep(step);

            $('#formAlert').removeClass('d-none alert-success').addClass('alert-danger')
                .text('Please review this step: ' + firstError.message);

            const target = field.hasClass('contact-method') ? field.closest('.contact-options')
                : field.hasClass('select2-hidden-accessible') ? field.next('.select2')
                : field.is(':hidden') ? field.closest('fieldset') : field;
            $('html, body').animate({ scrollTop: Math.max(0, target.offset().top - 140) }, 250);
        },
        submitHandler: function () {
            if (!submissionEnabled) return false;
            if (otpVerificationInFlight || submissionInFlight || applicationSubmitted) return false;
            syncPhoneValues();
            sendOtp();
            return false;
        }
    });

    function fieldsInStep(step) {
        return $('.form-step[data-step="' + step + '"]')
            .find(':input')
            .not(':button, [type="hidden"], :disabled')
            .filter(function () {
                return $(this).is(':visible') || $(this).hasClass('select2-hidden-accessible') || this.type === 'radio' || this.type === 'checkbox';
            });
    }

    function validateCurrentStep() {
        let valid = true;
        const checkedNames = new Set();

        fieldsInStep(currentStep).each(function () {
            const field = $(this);
            const name = field.attr('name') || '';

            if ((this.type === 'radio' || this.type === 'checkbox') && checkedNames.has(name)) return;
            if (this.type === 'radio' || this.type === 'checkbox') checkedNames.add(name);

            if (!validator.element(this)) valid = false;
        });

        if (!valid) {
            const firstError = $('.form-step[data-step="' + currentStep + '"] .error:visible').first();
            if (firstError.length) {
                $('html, body').animate({ scrollTop: Math.max(0, firstError.offset().top - 140) }, 250);
            }
        }

        return valid;
    }

    function showStep(step) {
        currentStep = Math.min(Math.max(step, 1), totalSteps);
        $('.form-step').removeClass('active').attr('aria-hidden', 'true');
        $('.form-step[data-step="' + currentStep + '"]').addClass('active').attr('aria-hidden', 'false');

        $('.wizard-step').each(function () {
            const indicator = Number($(this).data('step-indicator'));
            $(this).toggleClass('active', indicator === currentStep);
            $(this).toggleClass('completed', indicator < currentStep);
            $(this).find('.step-number').text(indicator < currentStep ? '✓' : indicator);
        });

        const progress = (currentStep / totalSteps) * 100;
        $('#wizardProgress').css('width', progress + '%').attr('aria-valuenow', progress);
        $('#currentStepLabel').text('Step ' + currentStep);
        $('.btn-prev').toggleClass('d-none', currentStep === 1);
        $('.btn-next').toggleClass('d-none', currentStep === totalSteps);
        $('.btn-submit').toggleClass('d-none', currentStep !== totalSteps);

        const shellTop = $('.application-shell').offset().top - 90;
        if ($(window).scrollTop() > shellTop + 180) {
            $('html, body').animate({ scrollTop: Math.max(0, shellTop) }, 250);
        }
    }

    function syncPhoneValues() {
        const mobileNumber = mobileIti.isValidNumber() ? mobileIti.getNumber() : '';
        $('#mobile_e164').val(mobileNumber);

        if ($('#same_as_mobile').is(':checked')) {
            $('#whatsapp_e164').val(mobileNumber);
        } else {
            $('#whatsapp_e164').val(whatsappIti.isValidNumber() ? whatsappIti.getNumber() : '');
        }
    }

    function sendOtp() {

        if (!submissionEnabled) return Promise.resolve({ success: false });

        if (otpSendRequest) return otpSendRequest;

        const button = $('.btn-submit');
        const alert = $('#formAlert');
        const modalOpen = $('#otpModal').hasClass('show');

        // Initial Send only
        if (!modalOpen) {
            button.prop('disabled', true);
            button.find('.submit-text').text('Sending OTP...');
            button.find('.spinner-border').removeClass('d-none');

            alert
                .addClass('d-none')
                .removeClass('alert-danger')
                .text('');
        }

        // Clear modal error every time
        $('#otpError').addClass('d-none').text('');

        otpSendRequest = axios.post(SEND_OTP_API_URL, {
            purpose: 'prime',
            name: $('#first_name').val().trim(),
            mobile: $('#mobile_e164').val().trim()
        }, { skipGlobalErrorHandler: true })

        .then(function (response) {

            if (!isSuccessfulResponse(response.data)) {
                showOtpError(responseErrorMessage(response.data, null, 'Unable to send OTP.'), modalOpen);
                return { success: false };
            }

            // Resend
            if (modalOpen) {
                $('#otpCode').val('').trigger('focus');
                return { success: true };
            }

            // First Send
            $('#otpCode').val('');
            $('#otpMobileNumber').text($('#mobile_e164').val());

            $('#otpModal')
                .one('shown.bs.modal', function () {
                    $('#otpCode').trigger('focus');
                })
                .modal('show');
            return { success: true };
        })

        .catch(function (error) {
            showOtpError(requestErrorMessage(error, 'Unable to send OTP. Please try again.'), modalOpen);
            return { success: false, status: error.response?.status, retryAfter: error.response?.headers?.get?.('Retry-After') ?? error.response?.headers?.['retry-after'] };
        })

        .finally(function () {

            otpSendRequest = null;

            if (!modalOpen) {
                button.prop('disabled', false);
                button.find('.submit-text').text('Submit Application');
                button.find('.spinner-border').addClass('d-none');
            }

        });

        return otpSendRequest;
    }


    //auto verify otp
    $('#otpCode').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);

        if (this.value.length === 6) {
            verifyOtp();
        }
    });

    function verifyOtp() {

        if (!submissionEnabled) return;

        const otp = $('#otpCode').val().trim();

        if (otp.length !== 6 || otpVerificationInFlight) {
            return;
        }

        otpVerificationInFlight = true;
        $('#otpCode').prop('disabled', true);
        $('#otpVerifying').removeClass('d-none');
        $('#otpError').addClass('d-none').text('');

        axios.post(VERIFY_OTP_API_URL, { purpose: 'prime', otp }, { skipGlobalErrorHandler: true })

        .then(function (response) {

            if (!isSuccessfulResponse(response.data)) {
                showOtpError(responseErrorMessage(response.data, null, 'Unable to verify the code.'), true);

                $('#otpCode').prop('disabled', false).val('').trigger('focus');

                return;
            }

            $('#otpModal').modal('hide');
            submitForm();

        })

        .catch(function (error) {

            showOtpError(requestErrorMessage(error, 'Unable to verify the code. Please try again.'), true);

            $('#otpCode')
                .prop('disabled', false)
                .val('')
                .trigger('focus');

        })

        .finally(function () {

            otpVerificationInFlight = false;
            $('#otpCode').prop('disabled', false);
            $('#otpVerifying').addClass('d-none');

        });

    }

    // Reset modal when closed
    $('#otpModal').on('hidden.bs.modal', function () {

        $('#otpCode').val('').prop('disabled', false);
        $('#otpError').addClass('d-none').text('');
        $('#otpVerifying').addClass('d-none');

    });


    $('#btnResendOtp').on('click', function (e) {

        e.preventDefault();

        const button = $(this);

        if (button.prop('disabled')) {
            return;
        }

        button.prop('disabled', true);

        sendOtp().then(function (result) {
            if (result.success) {
                startResendCooldown(button, 30);
            } else if (result.status === 429) {
                const retryAfter = Number(result.retryAfter);
                startResendCooldown(button, Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 30);
            } else {
                button.prop('disabled', false).text('Resend Code');
            }
        });

    });

    function startResendCooldown(button, duration) {
        let seconds = Math.ceil(duration);
        button.prop('disabled', true).text('Resend Code (' + seconds + ')');

        const timer = setInterval(function () {
            seconds--;

            if (seconds === 0) {
                clearInterval(timer);
                button.prop('disabled', false).text('Resend Code');
            } else {
                button.text('Resend Code (' + seconds + ')');
            }
        }, 1000);
    }

    function showOtpError(message, modalOpen) {

        if (modalOpen) {

            $('#otpError')
                .removeClass('d-none')
                .text(message);

            return;
        }

        const alert = $('#formAlert');

        alert
            .removeClass('d-none')
            .addClass('alert-danger')
            .text(message);

        $('html, body').animate({
            scrollTop: Math.max(0, alert.offset().top - 120)
        }, 300);
    }


function trackPrimeLead() {

    if (typeof fbq !== 'function') {
        console.warn('Meta Pixel is not available.');
        return;
    }

    const eventId = 'prime_franchise_' + Date.now();

    // =========================
    // CUSTOMER DATA
    // =========================

    const firstName = $('#first_name').val().trim();
    const middleName = $('#middle_name').val().trim();
    const lastName = $('#last_name').val().trim();

    const email = $('#email').val().trim();

    const phone = ($('#mobile_e164').val() || '')
        .replace(/\D/g, '');

    const city = $('#city').val().trim();
    const state = $('#state').val().trim();
    const pinCode = $('#pin_code').val().trim();

    // =========================
    // ADVANCED MATCHING
    // =========================

    fbq('init', '1565782775050658', {
        em: email,
        ph: phone,
        fn: firstName,
        ln: lastName,
        ct: city.replace(/\s+/g, ''),
        st: state.replace(/\s+/g, ''),
        zp: pinCode,
        country: 'in'
    });

    // =========================
    // LEAD EVENT
    // =========================

    const leadName = [
        firstName,
        middleName,
        lastName
    ]
        .filter(Boolean)
        .join(' ')
        .trim();

    const eventData = {

        lead_name: leadName,
        lead_type: 'franchise_prime_application',

        content_name: 'Franchise Prime Application',
        content_category: 'Franchise',

        // Profile
        age_group: $('#age_group').val() || '',
        occupation: $('#occupation').val() || '',

        // Location
        city: city,
        state: state,

        // Business
        business_experience:
            $('input[name="business_experience"]:checked').val() || '',
        industry: $('#industry').val() || '',

        // Property
        property_status:
            $('input[name="property_status"]:checked').val() || '',
        road_facing:
            $('input[name="road_facing"]:checked').val() || '',

        // Preferred location
        preferred_city: $('#preferred_city').val() || '',
        preferred_area: $('#preferred_area').val() || '',

        // Intent
        start_timeline: $('#start_timeline').val() || '',
        decision_maker:
            $('input[name="decision_maker"]:checked').val() || '',

        // Contact preference
        contact_methods:
            $('input[name="contact_methods[]"]:checked')
                .map(function () {
                    return this.value;
                })
                .get()
                .join(','),

        // Attribution
        page_location: window.location.href,
        source: 'franchise-prime'
    };

    // =========================
    // META LEAD
    // =========================

    fbq('track', 'Lead', eventData, {
        eventID: eventId
    });

    console.log('Meta Prime Lead:', {
        eventId: eventId,
        advancedMatching: {
            email: !!email,
            phone: !!phone,
            firstName: !!firstName,
            lastName: !!lastName,
            city: !!city,
            state: !!state,
            pinCode: !!pinCode,
            country: 'in'
        },
        eventData: eventData
    });
}

    function submitForm() {
        if (!submissionEnabled) return;
        if (submissionInFlight || applicationSubmitted) return;

        submissionInFlight = true;
        const button = $('.btn-submit');
        const alert = $('#formAlert');

        button.prop('disabled', true);
        button.find('.submit-text').text('Submitting...');
        button.find('.spinner-border').removeClass('d-none');
        alert.addClass('d-none').removeClass('alert-success alert-danger');

        axios.post(API_URL, form.serialize(), {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            skipGlobalErrorHandler: true
        }).then(function (response) {
            if (!isSuccessfulResponse(response.data)) {
                handleServerErrors(response.data, null);
                return;
            }
            applicationSubmitted = true;
            if (response.data.created === true) {
                trackPrimeLead();
            }

            alert.removeClass('d-none')
                .addClass('alert-success')
                .html('<strong>' + (response.data.created === true ? 'Application submitted.' : 'Application already received.') + '</strong> ' + escapeHtml(response.data.message));

            $('.form-step[data-step="5"]').children().not('#formAlert').hide();
            $('.wizard-actions').hide();


            $('html, body').animate({
                scrollTop: Math.max(0, alert.offset().top - 120)
            }, 300);

        }).catch(function (error) {
            handleServerErrors(error.response?.data || {}, error.response?.status ?? 0);
        }).finally(function () {
            submissionInFlight = false;
            button.prop('disabled', false);
            button.find('.submit-text').text('Submit Application');
            button.find('.spinner-border').addClass('d-none');
        });
    }

    function isSuccessfulResponse(response) {
        return Boolean(response && response.success === true);
    }

    function responseErrors(response) {
        const errors = response && (response.errors || (response.data && response.data.errors));
        return errors && typeof errors === 'object' && !Array.isArray(errors) ? errors : {};
    }

    function firstMessage(value) {
        if (typeof value === 'string') return value.trim();
        if (Array.isArray(value)) {
            for (const item of value) {
                const message = firstMessage(item);
                if (message) return message;
            }
        }
        return '';
    }

    function statusFallback(status, fallback) {
        if (status === 419) return 'Your session expired. Refresh the page and try again.';
        if (status === 429) return 'Too many attempts. Please wait before trying again.';
        if (status === 503) return 'This service is temporarily unavailable. Please try again later.';
        if (status === 0) return 'Connection failed. Please check your network and try again.';
        return fallback;
    }

    function responseErrorMessage(response, status, fallback) {
        const fieldMessage = Object.values(responseErrors(response)).map(firstMessage).find(Boolean) || '';
        const message = firstMessage(response && response.message);
        return (status === 422 ? fieldMessage || message : message || fieldMessage) || fallback;
    }

    function requestErrorMessage(error, fallback) {
        const status = error.response?.status ?? 0;
        return responseErrorMessage(error.response?.data, status, statusFallback(status, fallback));
    }

    function fieldNameForError(key) {
        const name = key.split(/[.[]/)[0];
        if (name === 'contact_methods') return 'contact_methods[]';
        if (name === 'mobile_e164') return 'mobile_number';
        if (name === 'whatsapp_e164' || (name === 'whatsapp_number' && $('#same_as_mobile').is(':checked'))) {
            return $('#same_as_mobile').is(':checked') ? 'mobile_number' : 'whatsapp_number';
        }
        return name;
    }

    function revealConditionalField(name) {
        if (name === 'business_name' || name === 'industry') $('#businessDetails').removeClass('d-none');
        if (name === 'property_location' || name === 'carpet_area' || name === 'road_facing') $('#propertyDetails').removeClass('d-none');
        if (name === 'whatsapp_number') $('#whatsappField').removeClass('d-none');
    }

    function handleServerErrors(response, status) {
        const alert = $('#formAlert');
        const errors = responseErrors(response);
        const message = responseErrorMessage(response, status, statusFallback(status, 'Please review the form and try again.'));
        alert.removeClass('d-none').addClass('alert-danger').html('<strong>Unable to submit.</strong> ' + escapeHtml(message));

        const fieldErrors = {};
        let firstField = null;

        Object.entries(errors).forEach(function ([key, messages]) {
            const name = fieldNameForError(key);
            const field = form.find(':input').filter(function () { return this.name === name; }).first();
            const fieldMessage = firstMessage(messages);
            if (!field.length || !field.closest('.form-step').length || field.attr('type') === 'hidden' || !fieldMessage) return;

            if (!fieldErrors[name]) fieldErrors[name] = fieldMessage;
            if (!firstField) firstField = field;
            revealConditionalField(name);
        });

        if (firstField) {
            const step = Number(firstField.closest('.form-step').data('step')) || currentStep;
            showStep(step);
            validator.showErrors(fieldErrors);
        }

        const scrollTarget = firstField || alert;
        $('html, body').animate({ scrollTop: Math.max(0, scrollTarget.offset().top - 120) }, 300);
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value || '')).html();
    }

    $('.btn-next').on('click', function () {
        syncPhoneValues();
        if (validateCurrentStep()) showStep(currentStep + 1);
    });

    $('.btn-prev').on('click', function () {
        showStep(currentStep - 1);
    });

    $('#same_as_mobile').on('change', function () {
        const isSame = this.checked;
        $('#whatsappField').toggleClass('d-none', isSame);
        $('#sameMobileHint').toggleClass('d-none', !isSame);
        if (isSame) {
            whatsappIti.setNumber('');
            validator.resetElements($('#whatsapp_number'));
        }
    });

    $('input[name="business_experience"]').on('change', function () {
        const show = this.value === 'yes';
        $('#businessDetails').toggleClass('d-none', !show);
        if (!show) {
            $('#business_name, #industry').val('');
            validator.resetElements($('#business_name, #industry'));
        }
    });

    $('input[name="property_status"]').on('change', function () {
        const show = this.value === 'yes';
        $('#propertyDetails').toggleClass('d-none', !show);
        if (!show) {
            $('#property_location, #carpet_area').val('');
            $('input[name="road_facing"]').prop('checked', false);
            validator.resetElements($('#property_location, #carpet_area, input[name="road_facing"]'));
        }
    });

    $('.select2').on('change', function () {
        $(this).valid();
    });

    $('.contact-method').on('change', function () {
        if ($('.contact-method:checked').length > 0) {
            $('#contact_methods_error').addClass('d-none').text('');
        }
    });

    $('#partnership_reason').on('input', function () {
        $('#reasonCounter').text(this.value.length + ' / 255');
    });

    $('#pin_code').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    showStep(1);
})(jQuery);
