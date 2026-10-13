@extends('layouts.app')

@section('title', 'FOFO Franchise Application | Detailing Devils')
@section('description', 'Explore the Detailing Devils Franchise Owned Franchise Operated model.')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/fofo.css') }}">
@endpush

@section('content')
<section class="fofo-hero wrap" aria-labelledby="fofo-title">
    <div class="fofo-hero-inner">
        <span class="lbl">Franchise Owned Franchise Operated</span>
        <h1 class="disp" id="fofo-title"><span class="fofo-title-line">Build your</span>
            <span class="fofo-title-line"><span class="acc">Devils</span> studio.</span></h1>
        <p>Tell us about your background, preferred location and plans for a Detailing Devils franchise.</p>
    </div>
</section>

<section class="fofo-section wrap" aria-label="FOFO franchise application">
    <div class="fofo-shell">
        @unless($submissionEnabled)
        <div class="fofo-notice" role="status">
            <i class="bi bi-file-earmark" aria-hidden="true"></i>
            <span class="fofo-notice-content"><strong>Application preview</strong>
                <span>Final submission is not active yet. Your details will not be sent or saved.</span></span>
        </div>
        @endunless

        <form id="fofo-form" method="post" enctype="multipart/form-data" novalidate
            data-submit-url="{{ route('fofo.submit') }}"
            data-countries-url="{{ route('locations.countries') }}"
            data-states-url="{{ route('locations.states') }}"
            data-cities-url="{{ route('locations.cities') }}"
            data-email-validation-url="{{ route('email.validate') }}">
            @csrf

            <fieldset class="fofo-group">
                <legend><span>01</span><span>Personal information</span></legend>
                <p class="fofo-section-copy">Let's start with your basic details.</p>
                <div class="fofo-grid">
                    <div class="fofo-field"><label for="name">First name <em>*</em></label><div class="fofo-control"><i class="bi bi-person" aria-hidden="true"></i><input id="name" name="name" autocomplete="given-name" minlength="2" maxlength="50" required></div><small class="fofo-error" data-error-for="name"></small></div>
                    <div class="fofo-field"><label for="lname">Last name <em>*</em></label><div class="fofo-control"><i class="bi bi-person" aria-hidden="true"></i><input id="lname" name="lname" autocomplete="family-name" minlength="2" maxlength="50" required></div><small class="fofo-error" data-error-for="lname"></small></div>
                    <div class="fofo-field fofo-email-field"><label for="email">Email <em>*</em></label><div class="fofo-control fofo-email-control"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" name="email" type="email" autocomplete="email" maxlength="254" aria-describedby="fofo-email-feedback" required><span class="fofo-email-indicator" aria-hidden="true"><i class="bi bi-check-circle-fill fofo-email-valid-icon"></i><i class="bi bi-exclamation-circle-fill fofo-email-invalid-icon"></i><i class="bi bi-exclamation-circle fofo-email-unavailable-icon"></i></span></div><small class="fofo-error" id="fofo-email-feedback" data-error-for="email" aria-live="polite"></small></div>
                    <div class="fofo-field"><label for="phone">Phone <em>*</em></label><div class="fofo-control"><i class="bi bi-telephone" aria-hidden="true"></i><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+91 98765 43210" maxlength="32" required></div><small class="fofo-hint">Include your country calling code.</small><small class="fofo-error" data-error-for="phone"></small></div>
                    <div class="fofo-field"><label for="dob">Date of birth <em>*</em></label><div class="fofo-control"><i class="bi bi-calendar-event" aria-hidden="true"></i><input id="dob" name="dob" type="date" autocomplete="bday" required></div><small class="fofo-error" data-error-for="dob"></small></div>
                    <div class="fofo-field"><label for="profession">Current profession</label><div class="fofo-control"><i class="bi bi-briefcase" aria-hidden="true"></i><input id="profession" name="profession" autocomplete="organization-title" maxlength="150"></div><small class="fofo-error" data-error-for="profession"></small></div>
                </div>
            </fieldset>

            <fieldset class="fofo-group">
                <legend><span>02</span><span>Your location</span></legend>
                <p class="fofo-section-copy">Help us understand where you plan to set up your franchise.</p>
                <div class="fofo-location-row">
                    <div class="fofo-grid">
                        <div class="fofo-field"><label for="country">Country <em>*</em></label><div class="fofo-control"><i class="bi bi-globe" aria-hidden="true"></i><select id="country" name="country" required disabled><option value="">Loading countries…</option></select></div><small class="fofo-error" data-error-for="country"></small></div>
                    </div>
                    <div class="fofo-grid" id="fofo-location-selects">
                        <div class="fofo-field"><label for="state">State or region <em>*</em></label><div class="fofo-control"><i class="bi bi-geo-alt" aria-hidden="true"></i><select id="state" name="state" required disabled><option value="">Select a country first</option></select></div><small class="fofo-error" data-error-for="state"></small></div>
                        <div class="fofo-field"><label for="city">City <em>*</em></label><div class="fofo-control"><i class="bi bi-geo-alt" aria-hidden="true"></i><select id="city" name="city" required disabled><option value="">Select a state first</option></select></div><small class="fofo-error" data-error-for="city"></small></div>
                    </div>
                </div>
                <div class="fofo-grid" id="fofo-location-manual" hidden>
                    <div class="fofo-field"><label for="another_country_name">Country name <em>*</em></label><input id="another_country_name" name="another_country_name" maxlength="150" data-conditional-required disabled><small class="fofo-error" data-error-for="another_country_name"></small></div>
                    <div class="fofo-field"><label for="another_state_name">State or region name <em>*</em></label><input id="another_state_name" name="another_state_name" maxlength="100" data-conditional-required disabled><small class="fofo-error" data-error-for="another_state_name"></small></div>
                    <div class="fofo-field"><label for="another_city_name">City name <em>*</em></label><input id="another_city_name" name="another_city_name" maxlength="100" data-conditional-required disabled><small class="fofo-error" data-error-for="another_city_name"></small></div>
                </div>
            </fieldset>

            <fieldset class="fofo-group">
                <legend><span>03</span><span>Your plans</span></legend>
                <p class="fofo-section-copy">Tell us how you found us and when you hope to begin.</p>
                <div class="fofo-grid">
                    <div class="fofo-field"><label for="hearabout">How did you hear about Detailing Devils? <em>*</em></label><select id="hearabout" name="hearabout" required><option value="">Select one</option><option value="Google-Search">Google Search</option><option value="Youtube">Youtube</option><option value="Facebook">Facebook</option><option value="Instagram">Instagram</option><option value="E-Mailing">E-Mail</option><option value="Referral">Referral</option><option value="Other">Other</option></select><small class="fofo-error" data-error-for="hearabout"></small></div>
                    <div class="fofo-field"><label for="contacted">How would you like to be contacted? <em>*</em></label><select id="contacted" name="contacted" required><option value="">Select one</option><option value="Telephone">Telephone</option><option value="Mail">Mail</option><option value="Whatsapp">Whatsapp</option></select><small class="fofo-error" data-error-for="contacted"></small></div>
                    <div class="fofo-field"><label for="franchiseapply">Preferred franchise city <em>*</em></label><div class="fofo-control"><i class="bi bi-geo-alt" aria-hidden="true"></i><input id="franchiseapply" name="franchiseapply" maxlength="100" required></div><small class="fofo-error" data-error-for="franchiseapply"></small></div>
                    <div class="fofo-field"><label for="wantstart">When would you like to start? <em>*</em></label><select id="wantstart" name="wantstart" required><option value="">Select one</option>
                        @foreach (config('form_options.fofo.wantstart') as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select><small class="fofo-error" data-error-for="wantstart"></small></div>
                    <div class="fofo-field fofo-wide"><label for="newbussiness">Why are you looking for a new business opportunity? <em>*</em></label><textarea id="newbussiness" name="newbussiness" rows="3" maxlength="255" required></textarea><small class="fofo-hint">Up to 255 characters.</small><small class="fofo-error" data-error-for="newbussiness"></small></div>
                </div>
            </fieldset>

            <fieldset class="fofo-group">
                <legend><span>04</span><span>Business background</span></legend>
                <p class="fofo-section-copy">Tell us about your experience and current involvement in business or automotive services.</p>
                <div class="fofo-grid">
                    <div class="fofo-field fofo-wide"><label for="bussinessowner">Are you already a business owner? <em>*</em></label><div class="fofo-control"><i class="bi bi-building" aria-hidden="true"></i><select id="bussinessowner" name="bussinessowner" required><option value="">Select one</option><option value="NO">No</option><option value="YES">Yes</option></select></div><small class="fofo-error" data-error-for="bussinessowner"></small></div>
                </div>
                <div class="fofo-grid fofo-conditional" id="fofo-business-details" hidden>
                    <div class="fofo-field"><label for="type_of_bussiness">Type of business <em>*</em></label><input id="type_of_bussiness" name="type_of_bussiness" maxlength="255" data-conditional-required disabled><small class="fofo-error" data-error-for="type_of_bussiness"></small></div>
                    <div class="fofo-field"><label for="product_service">Products or services you sell <em>*</em></label><input id="product_service" name="product_service" maxlength="255" data-conditional-required disabled><small class="fofo-error" data-error-for="product_service"></small></div>
                    <div class="fofo-field fofo-wide"><label for="bussiness_description">Business description, employees and annual turnover <em>*</em></label><textarea id="bussiness_description" name="bussiness_description" rows="3" maxlength="255" data-conditional-required disabled></textarea><small class="fofo-hint">Up to 255 characters.</small><small class="fofo-error" data-error-for="bussiness_description"></small></div>
                </div>
                <div class="fofo-grid">
                    <div class="fofo-field fofo-wide"><label for="experienceautomotive">Experience in automotive services or body painting? <em>*</em></label><div class="fofo-control"><i class="bi bi-wrench" aria-hidden="true"></i><select id="experienceautomotive" name="experienceautomotive" required><option value="">Select one</option><option value="NO">No</option><option value="YES">Yes</option></select></div><small class="fofo-error" data-error-for="experienceautomotive"></small></div>
                </div>
                <div class="fofo-grid fofo-conditional" id="fofo-experience-details" hidden>
                    <div class="fofo-field fofo-wide"><label for="experience">Describe your automotive experience <em>*</em></label><textarea id="experience" name="experience" rows="3" maxlength="255" data-conditional-required disabled></textarea><small class="fofo-hint">Up to 255 characters.</small><small class="fofo-error" data-error-for="experience"></small></div>
                </div>
                <div class="fofo-grid">
                    <div class="fofo-field fofo-wide"><label for="existingautomotive">Do you have an existing automotive detailing facility? <em>*</em></label><select id="existingautomotive" name="existingautomotive" required><option value="">Select one</option><option value="NO">No</option><option value="YES">Yes</option></select><small class="fofo-error" data-error-for="existingautomotive"></small></div>
                </div>
                <div class="fofo-grid fofo-conditional" id="fofo-facility-details" hidden>
                    <div class="fofo-field fofo-wide"><label for="existingfacility">Describe its employees, services, location and area <em>*</em></label><textarea id="existingfacility" name="existingfacility" rows="3" maxlength="255" data-conditional-required disabled></textarea><small class="fofo-hint">Up to 255 characters.</small><small class="fofo-error" data-error-for="existingfacility"></small></div>
                </div>
            </fieldset>

            <fieldset class="fofo-group">
                <legend><span>05</span><span>Property and availability</span></legend>
                <p class="fofo-section-copy">Share details about your space and availability.</p>
                <div class="fofo-grid">
                    <div class="fofo-field fofo-wide"><label for="spacearound">Do you have around 1,000–2,000 sq. ft. for a studio? <em>*</em></label><select id="spacearound" name="spacearound" required><option value="">Select one</option><option value="NO">No</option><option value="YES">Yes</option></select><small class="fofo-error" data-error-for="spacearound"></small></div>
                </div>
                <div class="fofo-grid fofo-conditional" id="fofo-property-details" hidden>
                    <div class="fofo-field fofo-wide"><label for="elaboratelocation">Tell us about the location <em>*</em></label><textarea id="elaboratelocation" name="elaboratelocation" rows="3" maxlength="255" data-conditional-required disabled></textarea><small class="fofo-hint">Up to 255 characters.</small><small class="fofo-error" data-error-for="elaboratelocation"></small></div>
                    <div class="fofo-field fofo-wide"><label for="locationimage">Location photo <em>*</em></label><input id="locationimage" name="locationimage" type="file" accept="image/jpeg,image/png,image/webp" data-conditional-required disabled><small class="fofo-hint">JPEG, PNG or WebP, up to 10,000 KB.</small><small class="fofo-error" data-error-for="locationimage"></small></div>
                </div>
                <div class="fofo-grid">
                    <div class="fofo-field fofo-wide"><span class="fofo-label" id="spendfranchise-label">How much time will you spend at your franchise?&nbsp;<em>*</em></span><div class="fofo-radios" role="group" aria-labelledby="spendfranchise-label"><label><input type="radio" name="spendfranchise" value="Full Time" required> Full Time</label><label><input type="radio" name="spendfranchise" value="Part Time"> Part Time</label><label><input type="radio" name="spendfranchise" value="Other"> Other</label></div><small class="fofo-error" data-error-for="spendfranchise"></small></div>
                    <div class="fofo-field"><label for="civillitigation">Civil litigation or criminal offence history? <em>*</em></label><select id="civillitigation" name="civillitigation" required><option value="">Select one</option><option value="yes">Yes</option><option value="no">No</option></select><small class="fofo-error" data-error-for="civillitigation"></small></div>
                    <div class="fofo-field"><label for="website">Website, if any</label><input id="website" name="website" type="url" inputmode="url" autocomplete="url" placeholder="https://example.com" maxlength="255"><small class="fofo-error" data-error-for="website"></small></div>
                </div>
            </fieldset>

            <div class="fofo-actions">
                <p id="fofo-form-status" role="status" aria-live="polite">{{ $submissionEnabled ? 'Review your details, then submit your application.' : 'Review your details here. Final submission will be enabled in a later stage.' }}</p>
                <button class="cta" type="button" id="fofo-review">Review fields <i aria-hidden="true">↗</i></button>
                <button class="cta red" type="submit" id="fofo-submit" @disabled(! $submissionEnabled)>{{ $submissionEnabled ? 'Submit Application' : 'Submit Application — Coming Soon' }}</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('js')
    <script src="{{ asset('assets/js/modules/fofo.js') }}"></script>
@endpush
