@extends('layouts.prime')

@section('title', 'Franchise Application | Detailing Devils Prime')

{{-- Temporary state list migrated from the legacy form; move to a controller/config when wiring the backend. --}}
@php
    $states = $states ?? [
        'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
        'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
        'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
        'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
        'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
        'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal'
    ];
@endphp

@section('content')
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1>Start your Detailing Devils Prime franchise journey</h1>
                <p>Complete this short application so our franchise team can understand your profile, preferred location and investment timeline.</p>
            </div>
            <div class="col-lg-5">
                <div class="hero-note">
                    <div class="hero-note-icon">✓</div>
                    <div>
                        <strong>Five guided steps</strong>
                        <span>Usually takes 4–6 minutes to complete.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="form-section">
    <div class="container">
        <div class="application-shell">
            <aside class="wizard-sidebar" aria-label="Application progress">
                <div class="sidebar-intro">
                    <span class="sidebar-label">Application progress</span>
                    <strong><span id="currentStepLabel">Step 1</span> of 5</strong>
                </div>
                <div class="progress" role="progressbar" aria-label="Application progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="20">
                    <div class="progress-bar" id="wizardProgress" style="width: 20%"></div>
                </div>

                <ol class="wizard-steps list-unstyled mb-0">
                    <li class="wizard-step active" data-step-indicator="1">
                        <span class="step-number">1</span>
                        <span><strong>Personal Information</strong><small>Your basic contact details</small></span>
                    </li>
                    <li class="wizard-step" data-step-indicator="2">
                        <span class="step-number">2</span>
                        <span><strong>Location</strong><small>Current address details</small></span>
                    </li>
                    <li class="wizard-step" data-step-indicator="3">
                        <span class="step-number">3</span>
                        <span><strong>Business Background</strong><small>Your management experience</small></span>
                    </li>
                    <li class="wizard-step" data-step-indicator="4">
                        <span class="step-number">4</span>
                        <span><strong>Property Details</strong><small>Commercial space status</small></span>
                    </li>
                    <li class="wizard-step" data-step-indicator="5">
                        <span class="step-number">5</span>
                        <span><strong>Franchise Plans</strong><small>Timeline and preferences</small></span>
                    </li>
                </ol>

                <div class="privacy-card d-none">
                    <span class="privacy-icon">🔒</span>
                    <div><strong>Private & confidential</strong><small>Your details are used only to evaluate and contact you about the franchise opportunity.</small></div>
                </div>
            </aside>

            <div class="wizard-content">
                <div id="formAlert" class="alert d-none" role="alert" aria-live="polite"></div>

                <form id="franchiseForm"
                        method="POST"
                        novalidate autocomplete="on"
                        data-submit-url="{{ route('prime.submit') }}"
                        data-send-otp-url="{{ route('otp.send') }}"
                        data-verify-otp-url="{{ route('otp.verify') }}"

                >
                    @csrf
                    <input type="hidden" name="mobile_e164" id="mobile_e164">
                    <input type="hidden" name="whatsapp_e164" id="whatsapp_e164">
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                    </div>

                    <section class="form-step active" data-step="1" aria-labelledby="step1Title">
                        <div class="step-heading">
                            <span class="step-kicker">Step 1 of 5</span>
                            <h2 id="step1Title">Personal Information</h2>
                            <p>Tell us who you are and how we can reach you.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="first_name">First Name <span>*</span></label>
                                <input class="form-control" type="text" id="first_name" name="first_name" maxlength="50" autocomplete="given-name" placeholder="Enter first name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="middle_name">Middle Name</label>
                                <input class="form-control" type="text" id="middle_name" name="middle_name" maxlength="50" autocomplete="additional-name" placeholder="Enter middle name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="last_name">Last Name <span>*</span></label>
                                <input class="form-control" type="text" id="last_name" name="last_name" maxlength="50" autocomplete="family-name" placeholder="Enter last name">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="mobile_number">Mobile Number <span>*</span></label>
                                <input class="form-control phone-input" type="tel" id="mobile_number" name="mobile_number" autocomplete="tel" inputmode="tel">
                                <div class="form-text">India is selected by default. You can change the country if required.</div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <label class="form-label mb-0" for="whatsapp_number">WhatsApp Number</label>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" id="same_as_mobile" name="same_as_mobile" value="1" checked>
                                        <label class="form-check-label" for="same_as_mobile">Same as mobile</label>
                                    </div>
                                </div>
                                <div id="whatsappField" class="conditional-field d-none">
                                    <input class="form-control phone-input" type="tel" id="whatsapp_number" name="whatsapp_number" autocomplete="tel" inputmode="tel">
                                </div>
                                <div id="sameMobileHint" class="same-number-note">We will use your mobile number for WhatsApp.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="email">Email Address <span>*</span></label>
                                <input class="form-control" type="email" id="email" name="email" maxlength="120" autocomplete="email" placeholder="name@example.com">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="age_group">Age <span>*</span></label>
                                <select class="form-select select2" id="age_group" name="age_group" data-placeholder="Select age">
                                    <option value=""></option>
                                    <option value="18-24">18–24 years</option>
                                    <option value="25+">25+ years</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="occupation">Current Occupation <span>*</span></label>
                                <select class="form-select select2" id="occupation" name="occupation" data-placeholder="Select occupation">
                                    <option value=""></option>
                                    <option value="Business Owner">Business Owner</option>
                                    <option value="Entrepreneur">Entrepreneur</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="form-step" data-step="2" aria-labelledby="step2Title">
                        <div class="step-heading">
                            <span class="step-kicker">Step 2 of 5</span>
                            <h2 id="step2Title">Location</h2>
                            <p>Share your current city and state details.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="city">City <span>*</span></label>
                                <input class="form-control" type="text" id="city" name="city" maxlength="80" autocomplete="address-level2" placeholder="Enter city">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="state">State <span>*</span></label>
                                <select class="form-select select2" id="state" name="state" data-placeholder="Select state">
                                    <option value=""></option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state }}">{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="pin_code">PIN Code</label>
                                <input class="form-control" type="text" id="pin_code" name="pin_code" maxlength="6" inputmode="numeric" autocomplete="postal-code" placeholder="6 digits">
                            </div>
                        </div>
                    </section>

                    <section class="form-step" data-step="3" aria-labelledby="step3Title">
                        <div class="step-heading">
                            <span class="step-kicker">Step 3 of 5</span>
                            <h2 id="step3Title">Business Background</h2>
                            <p>Help us understand your previous business experience.</p>
                        </div>

                        <fieldset class="choice-group">
                            <legend>Have you previously owned or managed a business? <span>*</span></legend>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <input class="btn-check" type="radio" name="business_experience" id="business_yes" value="yes">
                                    <label class="choice-card" for="business_yes"><span class="choice-icon">✓</span><span><strong>Yes</strong><small>I have business or management experience</small></span></label>
                                </div>
                                <div class="col-sm-6">
                                    <input class="btn-check" type="radio" name="business_experience" id="business_no" value="no">
                                    <label class="choice-card" for="business_no"><span class="choice-icon">—</span><span><strong>No</strong><small>This would be my first business venture</small></span></label>
                                </div>
                            </div>
                        </fieldset>

                        <div id="businessDetails" class="conditional-panel d-none">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="business_name">Business Name <span>*</span></label>
                                    <input class="form-control" type="text" id="business_name" name="business_name" maxlength="120" placeholder="Enter business name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="industry">Industry <span>*</span></label>
                                    <input class="form-control" type="text" id="industry" name="industry" maxlength="100" placeholder="e.g. Automotive, Retail, Hospitality">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="form-step" data-step="4" aria-labelledby="step4Title">
                        <div class="step-heading">
                            <span class="step-kicker">Step 4 of 5</span>
                            <h2 id="step4Title">Property Details</h2>
                            <p>Tell us about the commercial property planned for the franchise.</p>
                        </div>

                        <fieldset class="choice-group">
                            <legend>Do you already have a commercial property? <span>*</span></legend>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <input class="btn-check" type="radio" name="property_status" id="property_yes" value="yes">
                                    <label class="choice-card" for="property_yes"><span class="choice-icon">✓</span><span><strong>Yes</strong><small>I already have a property</small></span></label>
                                </div>
                                <div class="col-md-4">
                                    <input class="btn-check" type="radio" name="property_status" id="property_no" value="no">
                                    <label class="choice-card" for="property_no"><span class="choice-icon">—</span><span><strong>No</strong><small>I do not have a property yet</small></span></label>
                                </div>
                                <div class="col-md-4">
                                    <input class="btn-check" type="radio" name="property_status" id="property_rent" value="looking_to_rent">
                                    <label class="choice-card" for="property_rent"><span class="choice-icon">⌂</span><span><strong>Looking to rent</strong><small>I am actively exploring options</small></span></label>
                                </div>
                            </div>
                        </fieldset>

                        <div id="propertyDetails" class="conditional-panel d-none">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="property_location">Property Location <span>*</span></label>
                                    <input class="form-control" type="text" id="property_location" name="property_location" maxlength="180" placeholder="Area, city or full location">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="carpet_area">Carpet Area (Sq. Ft.) <span>*</span></label>
                                    <input class="form-control" type="number" id="carpet_area" name="carpet_area" min="100" max="100000" step="1" inputmode="numeric" placeholder="e.g. 1800">
                                </div>
                                <div class="col-md-3">
                                    <fieldset>
                                        <legend class="form-label">Road-facing? <span>*</span></legend>
                                        <div class="segmented-control">
                                            <input class="btn-check" type="radio" name="road_facing" id="road_yes" value="yes">
                                            <label class="btn" for="road_yes">Yes</label>
                                            <input class="btn-check" type="radio" name="road_facing" id="road_no" value="no">
                                            <label class="btn" for="road_no">No</label>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="form-step" data-step="5" aria-labelledby="step5Title">
                        <div class="step-heading">
                            <span class="step-kicker">Step 5 of 5</span>
                            <h2 id="step5Title">Franchise Plans</h2>
                            <p>Share your preferred market, timeline and communication choices.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="preferred_city">Preferred Franchise City</label>
                                <input class="form-control" type="text" id="preferred_city" name="preferred_city" maxlength="80" placeholder="Enter preferred city">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="preferred_area">Preferred Franchise Area / Locality</label>
                                <input class="form-control" type="text" id="preferred_area" name="preferred_area" maxlength="120" placeholder="Enter area or locality">
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="start_timeline">When do you wish to start your franchise? <span>*</span></label>
                                <select class="form-select select2" id="start_timeline" name="start_timeline" data-placeholder="Select your expected timeline">
                                    <option value=""></option>
                                    <option value="Immediately (within 30 days)">Immediately (within 30 days)</option>
                                    <option value="Within 1-3 months">Within 1–3 months</option>
                                    <option value="Within 3-6 months">Within 3–6 months</option>
                                    <option value="Within 6-12 months">Within 6–12 months</option>
                                    <option value="Just exploring the opportunity">Just exploring the opportunity</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <fieldset class="choice-group compact-choice-group">
                                    <legend>Are you the primary decision-maker for this investment? <span>*</span></legend>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <input class="btn-check" type="radio" name="decision_maker" id="decision_yes" value="yes">
                                            <label class="choice-card compact" for="decision_yes"><strong>Yes</strong></label>
                                        </div>
                                        <div class="col-md-4">
                                            <input class="btn-check" type="radio" name="decision_maker" id="decision_no" value="no">
                                            <label class="choice-card compact" for="decision_no"><strong>No</strong></label>
                                        </div>
                                        <div class="col-md-4">
                                            <input class="btn-check" type="radio" name="decision_maker" id="decision_joint" value="joint">
                                            <label class="choice-card compact" for="decision_joint"><strong>Joint decision with family / business partner</strong></label>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="col-12">
                                <fieldset class="contact-options">
                                    <legend>How would you like our team to contact you? <span>*</span></legend>
                                    <div class="d-flex flex-wrap gap-2">
                                        <input class="btn-check contact-method" type="checkbox" name="contact_methods[]" id="contact_phone" value="Phone Call">
                                        <label class="btn contact-btn" for="contact_phone">☎ Phone Call</label>

                                        <input class="btn-check contact-method" type="checkbox" name="contact_methods[]" id="contact_whatsapp" value="WhatsApp">
                                        <label class="btn contact-btn" for="contact_whatsapp">◉ WhatsApp</label>

                                        <input class="btn-check contact-method" type="checkbox" name="contact_methods[]" id="contact_email" value="Email">
                                        <label class="btn contact-btn" for="contact_email">✉ Email</label>
                                    </div>
                                    <label id="contact_methods_error" class="error d-none" for="contact_phone"></label>
                                </fieldset>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="partnership_reason">Why do you want to partner with Detailing Devils Prime?</label>
                                <textarea class="form-control" id="partnership_reason" name="partnership_reason" rows="5" maxlength="2000" placeholder="Tell us about your goals, interest in the automotive detailing industry, or what you expect from this partnership."></textarea>
                                <div class="d-flex justify-content-between form-text"><span>Optional, but helps our team understand your plans.</span><span id="reasonCounter">0 / 2000</span></div>
                            </div>

                            <div class="col-12">
                                <div class="consent-box">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1">
                                        <label class="form-check-label" for="consent">
                                            I agree to be contacted by Detailing Devils Prime through phone calls, WhatsApp, SMS and email regarding franchise opportunities. <span>*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="wizard-actions">
                        <button class="btn btn-outline-dark btn-prev d-none" type="button">Back</button>
                        <div class="ms-auto d-flex align-items-center gap-3">
                            <span class="required-note"><span>*</span> Required fields</span>
                            <button class="btn btn-dark btn-next" type="button">Continue <span aria-hidden="true">→</span></button>
                            <button class="btn btn-primary btn-submit d-none" type="submit">
                                <span class="submit-text">Submit Application</span>
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection

@push('js')
    <script src="{{ asset('assets/js/modules/prime.js') }}"></script>
@endpush
