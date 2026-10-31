@extends('layouts.app')

@section('title', $formModel['title'])
@section('description', $formModel['description'])

@push('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('assets/css/fofo.css') }}">
@endpush

@section('content')
    <section class="fofo-hero wrap" aria-labelledby="fofo-title">
        <div class="fofo-hero-inner">
            <span class="lbl">{{ $formModel['label'] }}</span>
            <h1 class="disp" id="fofo-title">
                <span class="fofo-title-line">Build your</span>
                <span class="fofo-title-line"><span class="acc">Devils</span> studio.</span>
            </h1>
            <p>
                Tell us about your background, preferred location and plans for a Detailing Devils franchise.
            </p>
        </div>
    </section>
    <section class="fofo-section wrap" aria-label="{{ $formModel['name'] }} franchise application">
        <div class="fofo-shell">
            @unless($submissionEnabled)
                <div class="fofo-notice" role="status">
                    <i class="bi bi-file-earmark" aria-hidden="true"></i>
                    <span class="fofo-notice-content">
                        <strong>Application preview</strong>
                        <span>Final submission is not active yet. Your details will not be sent or saved.</span>
                    </span>
                </div>
            @endunless
            <form
                id="fofo-form"
                data-franchise-model="fofo"
                method="post"
                enctype="multipart/form-data"
                novalidate
                data-submit-url="{{ route($formModel['submitRoute']) }}"
                data-countries-url="{{ route('locations.countries') }}"
                data-states-url="{{ route('locations.states') }}"
                data-cities-url="{{ route('locations.cities') }}"
                data-email-validation-url="{{ route('email.validate') }}"
            >
                @csrf
                @include('forms.partials.franchise-contact')
                @include('forms.partials.franchise-location')
                <fieldset class="fofo-group">
                    <legend>
                        <span>03</span>
                        <span>Your plans</span>
                    </legend>
                    <p class="fofo-section-copy">
                        Tell us how you found us and when you hope to begin.
                    </p>
                    <div class="fofo-grid">
                        <div class="fofo-field">
                            <label for="hearabout">
                                How did you hear about Detailing Devils? <em>*</em>
                            </label>
                            <select id="hearabout" name="hearabout" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['hearabout'] as $value)
                                    <option value="{{ $value }}">
                                        {{ $value === 'Google-Search' ? 'Google Search' : ($value === 'E-Mailing' ? 'E-Mail' : $value) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="hearabout"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="contacted">
                                How would you like to be contacted? <em>*</em>
                            </label>
                            <select id="contacted" name="contacted" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['contacted'] as $value)
                                    <option value="{{ $value }}">{{ $value }}</option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="contacted"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="franchiseapply">
                                Preferred franchise city <em>*</em>
                            </label>
                            <div class="fofo-control">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <input id="franchiseapply" name="franchiseapply" maxlength="100" required>
                            </div>
                            <small class="fofo-error" data-error-for="franchiseapply"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="wantstart">
                                When would you like to start? <em>*</em>
                            </label>
                            <select id="wantstart" name="wantstart" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['wantstart'] as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="wantstart"></small>
                        </div>
                        <div class="fofo-field fofo-wide">
                            <label for="newbussiness">
                                Why are you looking for a new business opportunity? <em>*</em>
                            </label>
                            <textarea id="newbussiness" name="newbussiness" rows="3" maxlength="255" required></textarea>
                            <small class="fofo-hint">Up to 255 characters.</small>
                            <small class="fofo-error" data-error-for="newbussiness"></small>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="fofo-group">
                    <legend>
                        <span>04</span>
                        <span>Business background</span>
                    </legend>
                    <p class="fofo-section-copy">
                        Tell us about your experience and current involvement in business or automotive services.
                    </p>
                    <div class="fofo-grid">
                        <div class="fofo-field fofo-wide">
                            <label for="bussinessowner">
                                Are you already a business owner? <em>*</em>
                            </label>
                            <div class="fofo-control">
                                <i class="bi bi-building" aria-hidden="true"></i>
                                <select id="bussinessowner" name="bussinessowner" required>
                                    <option value="">Select one</option>
                                    @foreach ($questionnaireOptions['yes_no'] as $value)
                                        <option value="{{ $value }}">{{ $value === 'YES' ? 'Yes' : 'No' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <small class="fofo-error" data-error-for="bussinessowner"></small>
                        </div>
                    </div>
                    <div class="fofo-grid fofo-conditional" id="fofo-business-details" hidden>
                        <div class="fofo-field">
                            <label for="type_of_bussiness">
                                Type of business <em>*</em>
                            </label>
                            <input
                                id="type_of_bussiness"
                                name="type_of_bussiness"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            >
                            <small class="fofo-error" data-error-for="type_of_bussiness"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="product_service">
                                Products or services you sell <em>*</em>
                            </label>
                            <input
                                id="product_service"
                                name="product_service"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            >
                            <small class="fofo-error" data-error-for="product_service"></small>
                        </div>
                        <div class="fofo-field fofo-wide">
                            <label for="bussiness_description">
                                Business description, employees and annual turnover <em>*</em>
                            </label>
                            <textarea
                                id="bussiness_description"
                                name="bussiness_description"
                                rows="3"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            ></textarea>
                            <small class="fofo-hint">Up to 255 characters.</small>
                            <small class="fofo-error" data-error-for="bussiness_description"></small>
                        </div>
                    </div>
                    <div class="fofo-grid">
                        <div class="fofo-field fofo-wide">
                            <label for="experienceautomotive">
                                Experience in automotive services or body painting? <em>*</em>
                            </label>
                            <div class="fofo-control">
                                <i class="bi bi-wrench" aria-hidden="true"></i>
                                <select id="experienceautomotive" name="experienceautomotive" required>
                                    <option value="">Select one</option>
                                    @foreach ($questionnaireOptions['yes_no'] as $value)
                                        <option value="{{ $value }}">{{ $value === 'YES' ? 'Yes' : 'No' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <small class="fofo-error" data-error-for="experienceautomotive"></small>
                        </div>
                    </div>
                    <div class="fofo-grid fofo-conditional" id="fofo-experience-details" hidden>
                        <div class="fofo-field fofo-wide">
                            <label for="experience">
                                Describe your automotive experience <em>*</em>
                            </label>
                            <textarea
                                id="experience"
                                name="experience"
                                rows="3"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            ></textarea>
                            <small class="fofo-hint">Up to 255 characters.</small>
                            <small class="fofo-error" data-error-for="experience"></small>
                        </div>
                    </div>
                    <div class="fofo-grid">
                        <div class="fofo-field fofo-wide">
                            <label for="existingautomotive">
                                Do you have an existing automotive detailing facility? <em>*</em>
                            </label>
                            <select id="existingautomotive" name="existingautomotive" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['yes_no'] as $value)
                                    <option value="{{ $value }}">{{ $value === 'YES' ? 'Yes' : 'No' }}</option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="existingautomotive"></small>
                        </div>
                    </div>
                    <div class="fofo-grid fofo-conditional" id="fofo-facility-details" hidden>
                        <div class="fofo-field fofo-wide">
                            <label for="existingfacility">
                                Describe its employees, services, location and area <em>*</em>
                            </label>
                            <textarea
                                id="existingfacility"
                                name="existingfacility"
                                rows="3"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            ></textarea>
                            <small class="fofo-hint">Up to 255 characters.</small>
                            <small class="fofo-error" data-error-for="existingfacility"></small>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="fofo-group">
                    <legend>
                        <span>05</span>
                        <span>Property and availability</span>
                    </legend>
                    <p class="fofo-section-copy">
                        Share details about your space and availability.
                    </p>
                    <div class="fofo-grid">
                        <div class="fofo-field fofo-wide">
                            <label for="spacearound">
                                Do you have around 1,000–2,000 sq. ft. for a studio? <em>*</em>
                            </label>
                            <select id="spacearound" name="spacearound" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['yes_no'] as $value)
                                    <option value="{{ $value }}">{{ $value === 'YES' ? 'Yes' : 'No' }}</option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="spacearound"></small>
                        </div>
                    </div>
                    <div class="fofo-grid fofo-conditional" id="fofo-property-details" hidden>
                        <div class="fofo-field fofo-wide">
                            <label for="elaboratelocation">
                                Tell us about the location <em>*</em>
                            </label>
                            <textarea
                                id="elaboratelocation"
                                name="elaboratelocation"
                                rows="3"
                                maxlength="255"
                                data-conditional-required
                                disabled
                            ></textarea>
                            <small class="fofo-hint">Up to 255 characters.</small>
                            <small class="fofo-error" data-error-for="elaboratelocation"></small>
                        </div>
                        <div class="fofo-field fofo-wide">
                            <label for="locationimage">
                                Location photo <em>*</em>
                            </label>
                            <input
                                id="locationimage"
                                name="locationimage"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                data-conditional-required
                                disabled
                            >
                            <small class="fofo-hint">JPEG, PNG or WebP, up to 10,000 KB.</small>
                            <small class="fofo-error" data-error-for="locationimage"></small>
                        </div>
                    </div>
                    <div class="fofo-grid">
                        <div class="fofo-field fofo-wide">
                            <span class="fofo-label" id="spendfranchise-label">
                                How much time will you spend at your franchise?&nbsp;<em>*</em>
                            </span>
                            <div class="fofo-radios" role="group" aria-labelledby="spendfranchise-label">
                                @foreach ($questionnaireOptions['spendfranchise'] as $value)
                                    <label>
                                        <input
                                            type="radio"
                                            name="spendfranchise"
                                            value="{{ $value }}"
                                            @required($loop->first)
                                        >
                                        {{ $value }}
                                    </label>
                                @endforeach
                            </div>
                            <small class="fofo-error" data-error-for="spendfranchise"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="civillitigation">
                                Civil litigation or criminal offence history? <em>*</em>
                            </label>
                            <select id="civillitigation" name="civillitigation" required>
                                <option value="">Select one</option>
                                @foreach ($questionnaireOptions['civillitigation'] as $value)
                                    <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                                @endforeach
                            </select>
                            <small class="fofo-error" data-error-for="civillitigation"></small>
                        </div>
                        <div class="fofo-field">
                            <label for="website">
                                Website, if any
                            </label>
                            <input
                                id="website"
                                name="website"
                                type="url"
                                inputmode="url"
                                autocomplete="url"
                                placeholder="https://example.com"
                                maxlength="255"
                            >
                            <small class="fofo-error" data-error-for="website"></small>
                        </div>
                    </div>
                </fieldset>
                <div class="fofo-actions">
                    <p id="fofo-form-status" role="status" aria-live="polite">
                        {{ $submissionEnabled ? 'Review your details, then submit your application.' : 'Review your details here. Final submission will be enabled in a later stage.' }}
                    </p>
                    <button class="cta" type="button" id="fofo-review">
                        Review fields
                        <i aria-hidden="true">
                            ↗
                        </i>
                    </button>
                    <button class="cta red" type="submit" id="fofo-submit" @disabled(! $submissionEnabled)>
                        {{ $submissionEnabled ? 'Submit Application' : 'Submit Application — Coming Soon' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('js')
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/js/intlTelInput.min.js"></script>
    <script
        src="{{ asset('assets/js/common/franchise-form.js') }}?v={{ substr(hash_file('sha256', public_path('assets/js/common/franchise-form.js')), 0, 12) }}"
    ></script>
    <script
        src="{{ asset('assets/js/modules/fofo.js') }}?v={{ substr(hash_file('sha256', public_path('assets/js/modules/fofo.js')), 0, 12) }}"
    ></script>
@endpush
