(function ($) {
    'use strict';

    const form = $('#franchiseForm');
    if (!form.length) return;

    const tyest = '';

    const API_URL = form.attr('data-submit-url');
    const SEND_OTP_API_URL = form.attr('data-send-otp-url');
    const VERIFY_OTP_API_URL = form.attr('data-verify-otp-url');


    let currentStep = 1;
    const totalSteps = 5;
    const mobileInput = document.querySelector('#mobile_number');
    const whatsappInput = document.querySelector('#whatsapp_number');

    const phoneOptions = {
        initialCountry: 'in',
        preferredCountries: ['in'],
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
        ignore: ':hidden:not(.select2-hidden-accessible):not(#whatsapp_number)',
        errorElement: 'label',
        errorClass: 'error',
        validClass: 'valid',
        focusInvalid: false,
        rules: {
            first_name: { required: true, personName: true },
            middle_name: { personName: true },
            last_name: { required: true, personName: true },
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
            partnership_reason: { maxlength: 2000 },
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
        submitHandler: function () {
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

        $.ajax({
            url: SEND_OTP_API_URL,
            type: 'POST',
            data: {
                name: $('#first_name').val().trim(),
                mobile: $('#mobile_e164').val().trim()
            },
            dataType: 'json'
        })

        .done(function (response) {

            if (!response.status) {
                return showOtpError(response.message || 'Unable to send OTP.', modalOpen);
            }

            // Resend
            if (modalOpen) {
                $('#otpCode').val('').trigger('focus');
                return;
            }

            // First Send
            $('#otpCode').val('');
            $('#otpMobileNumber').text($('#mobile_e164').val());

            $('#otpModal')
                .one('shown.bs.modal', function () {
                    $('#otpCode').trigger('focus');
                })
                .modal('show');
        })

        .fail(function () {
            showOtpError('Unable to send OTP. Please try again.', modalOpen);
        })

        .always(function () {

            if (!modalOpen) {
                button.prop('disabled', false);
                button.find('.submit-text').text('Submit Application');
                button.find('.spinner-border').addClass('d-none');
            }

        });
    }


    //auto verify otp
    $('#otpCode').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);

        if (this.value.length === 6) {
            verifyOtp();
        }
    });

    function verifyOtp() {

        const otp = $('#otpCode').val().trim();

        if (otp.length !== 6) {
            return;
        }

        $('#otpCode').prop('disabled', true);
        $('#otpVerifying').removeClass('d-none');
        $('#otpError').addClass('d-none').text('');

        $.ajax({
            url: VERIFY_OTP_API_URL,
            type: 'POST',
            dataType: 'json',
            data: { otp }
        })

        .done(function (response) {

            if (!response.status) {
                $('#otpError')
                    .removeClass('d-none')
                    .text(response.message);

               $('#otpCode').prop('disabled', false).val('').trigger('focus');

                return;
            }

            $('#otpModal').modal('hide');
            submitForm();

        })

        .fail(function (xhr) {

            const response = xhr.responseJSON || {};

            $('#otpError')
                .removeClass('d-none')
                .text(response.message || 'Something went wrong. Please try again.');

            $('#otpCode')
                .prop('disabled', false)
                .val('')
                .trigger('focus');

        })

        .always(function () {

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

        sendOtp();

        let seconds = 30;

        button.prop('disabled', true).text('Resend Code (' + seconds + ')');

        const timer = setInterval(function () {

            seconds--;

            if (seconds === 0) {
                clearInterval(timer);
                button.prop('disabled', false).text('Resend Code');
                return;
            }

            button.text('Resend Code (' + seconds + ')');

        }, 1000);

    });

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
        const button = $('.btn-submit');
        const alert = $('#formAlert');

        button.prop('disabled', true);
        button.find('.submit-text').text('Submitting...');
        button.find('.spinner-border').removeClass('d-none');
        alert.addClass('d-none').removeClass('alert-success alert-danger');

        $.ajax({
            url: API_URL,
            method: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (!response.status) {
                handleServerErrors(response);
                return;
            }
            //fb track pixel code
            trackPrimeLead();

            alert.removeClass('d-none')
                .addClass('alert-success')
                .html('<strong>Application submitted.</strong> ' + escapeHtml(response.message));

            $('.form-step[data-step="5"]').children().not('#formAlert').hide();
            $('.wizard-actions').hide();


            $('html, body').animate({
                scrollTop: Math.max(0, alert.offset().top - 120)
            }, 300);

        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            handleServerErrors(response);
        }).always(function () {
            button.prop('disabled', false);
            button.find('.submit-text').text('Submit Application');
            button.find('.spinner-border').addClass('d-none');
        });
    }

    function handleServerErrors(response) {
        const alert = $('#formAlert');
        const errors = (response.data && response.data.errors) || {};
        alert.removeClass('d-none').addClass('alert-danger').html('<strong>Unable to submit.</strong> ' + escapeHtml(response.message || 'Please review the form and try again.'));

        Object.keys(errors).forEach(function (name) {
            const target = name === 'contact_methods' ? $('[name="contact_methods[]"]').first() : $('[name="' + name + '"]').first();
            if (target.length) validator.showErrors({ [target.attr('name')]: errors[name] });
        });

        const firstFieldName = Object.keys(errors)[0];
        if (firstFieldName) {
            const selectorName = firstFieldName === 'contact_methods' ? 'contact_methods[]' : firstFieldName;
            const firstField = $('[name="' + selectorName + '"]').first();
            const step = Number(firstField.closest('.form-step').data('step')) || currentStep;
            showStep(step);
        }

        $('html, body').animate({ scrollTop: Math.max(0, alert.offset().top - 120) }, 300);
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
        $('#reasonCounter').text(this.value.length + ' / 2000');
    });

    $('#pin_code').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    showStep(1);
})(jQuery);
