@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', $studio->exists ? 'Edit studio' : 'Add studio')
@section('context', $studio->exists ? 'Edit studio' : 'Add studio')

@section('content')
    @php
        $protectedUrl = $studio->exists && ($studio->url_locked_at || $studio->status === 'active');
        $studioUrlPrefix = rtrim(config('seo.origin'), '/').\App\Services\StudioUrls::path('');
    @endphp
    <div class="studio-admin">
        <div class="page-heading">
            <div>
                <h1>{{ $studio->exists ? $studio->name : 'Add New Studio' }}</h1>
            </div>
            @if ($studio->exists)
                <a class="button secondary" href="{{ route('admin.studios.show', $studio) }}">View studio</a>
            @endif
        </div>
        <form
            class="studio-workspace studio-editor"
            data-studio-form
            @if ($errors->any() && old('name') !== null) data-unsaved-input @endif
            action="{{ $studio->exists ? route('admin.studios.update', $studio) : route('admin.studios.store') }}"
            method="post"
        >
            @csrf
            @if ($studio->exists)
                @method('put')
            @endif
            <div class="editor-actions studio-editor-actions">
                <span class="save-state" data-save-state role="status" aria-live="polite" hidden>No unsaved changes</span>
                <a class="button secondary" href="{{ route('admin.studios.index') }}">Cancel</a>
                <button class="button" type="submit">{{ $studio->exists ? 'Save Changes' : 'Create Studio' }}</button>
            </div>
            <section class="panel form-stack studio-form-section studio-details" aria-labelledby="details-title">
                <div class="section-heading">
                    <h2 id="details-title">Studio Details</h2>
                    <span class="muted small">* Required</span>
                </div>
                <div class="studio-field-grid">
                    @foreach (['name' => ['Studio name', 255], 'slug' => ['Studio Page URL', 191]] as $field => [$label, $limit])
                        <div class="field" @if ($field === 'slug' && ! $protectedUrl) data-slug-editor data-url-prefix="{{ $studioUrlPrefix }}" data-new="{{ $studio->exists ? 'false' : 'true' }}" data-slugs="{{ json_encode($existingSlugs ?? []) }}" @endif>
                            @if ($field === 'slug' && $protectedUrl)
                                <span class="studio-url-label">Studio Page URL</span>
                                <a class="studio-current-url" href="{{ rtrim(config('seo.origin'), '/').route('studios.show', ['slug' => $studio->slug], false) }}" target="_blank" rel="noopener">{{ rtrim(config('seo.origin'), '/').route('studios.show', ['slug' => $studio->slug], false) }}</a>
                                <input type="hidden" name="slug_mode" value="preserve">
                                <details class="studio-url-editor" @if ($errors->hasAny(['slug', 'expected_slug', 'confirm_url_change'])) open @endif>
                                    <summary>Change URL</summary>
                                    <div class="form-stack">
                                        <label for="new-studio-slug">New URL</label>
                                        <div class="studio-url-prefix">{{ $studioUrlPrefix }}</div>
                                        <input id="new-studio-slug" name="slug" form="studio-url-form" required maxlength="191" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('expected_slug') ? old('slug') : '' }}" placeholder="e.g. detailing-devils-jaipur" @if ($errors->has('slug')) aria-invalid="true" aria-describedby="studio-url-error" @endif>
                                        <output class="studio-current-url" for="new-studio-slug" data-studio-url-preview hidden></output>
                                        <p class="muted small">Your previous Studio Page URL will automatically redirect to the new URL.</p>
                                        @error('slug')<span class="invalid-feedback" id="studio-url-error">{{ $message }}</span>@enderror
                                        @error('expected_slug')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        <label class="check"><input type="checkbox" name="confirm_url_change" value="1" form="studio-url-form" required> I confirm this URL change.</label>
                                        @error('confirm_url_change')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                        <div class="actions">
                                            <button class="button secondary" type="submit" form="studio-url-form">Change URL</button>
                                            <button class="button secondary" type="reset" form="studio-url-form">Cancel</button>
                                        </div>
                                    </div>
                                </details>
                            @else
                            <label for="{{ $field }}">{{ $label }} @if ($field === 'name')<span aria-hidden="true">*</span>@endif</label>
                            <input
                                id="{{ $field }}"
                                name="{{ $field }}"
                                value="{{ old($field, $studio->$field) }}"
                                maxlength="{{ $limit }}"
                                @if ($field === 'name') placeholder="e.g. Detailing Devils Jaipur" @endif
                                @required($field === 'name')
                                @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                            >
                            @if ($field === 'slug')
                                <input type="hidden" name="slug_mode" value="{{ old('slug_mode', $studio->exists ? 'preserve' : 'auto') }}">
                                <div class="studio-slug-preview" data-slug-preview hidden>
                                    <output for="name" data-slug-output></output>
                                    <button class="button secondary" type="button" data-slug-edit>Customize URL</button>
                                </div>
                                <noscript><label class="check"><input type="checkbox" name="slug_mode" value="manual">Customize URL</label></noscript>
                            @endif
                            @error($field)
                                <span class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</span>
                            @enderror
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
            <details class="panel form-stack studio-form-section studio-location" aria-labelledby="location-title" data-studio-panel open>
                <summary><h2 id="location-title">Location</h2></summary>
                <div class="field">
                    <label for="address">Full address <span aria-hidden="true">*</span></label>
                    <textarea
                        id="address"
                        name="address"
                        placeholder="Enter complete studio address"
                        rows="2"
                        maxlength="4000"
                        required
                        @if ($errors->has('address')) aria-invalid="true" aria-describedby="address-error" @endif
                    >{{ old('address', $studio->address) }}</textarea>
                    @error('address')
                        <span class="invalid-feedback" id="address-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="studio-field-grid studio-location-grid">
                    @foreach ([
                        'city' => ['City (optional)', 100],
                        'state' => ['State *', 100],
                    ] as $field => [$label, $limit])
                        <div class="field">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <input
                                id="{{ $field }}"
                                name="{{ $field }}"
                                value="{{ old($field, $studio->$field) }}"
                                maxlength="{{ $limit }}"
                                @required($field === 'state')
                                @if ($field === 'state') placeholder="e.g. Rajasthan" @endif
                                @if ($field === 'city') placeholder="e.g. Jaipur" @endif
                                @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                            >
                            @error($field)
                                <span class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <div class="studio-field-grid">
                    <div class="field">
                        <label for="map_url">Google Maps URL (Directions)</label>
                        <textarea
                            id="map_url"
                            name="map_url"
                            rows="2"
                            maxlength="2048"
                            spellcheck="false"
                            placeholder="https://maps.app.goo.gl/…"
                            @if ($errors->has('map_url')) aria-invalid="true" aria-describedby="map-error" @endif
                        >{{ old('map_url', $studio->map_url) }}</textarea>
                        @error('map_url')
                            <span class="invalid-feedback" id="map-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="map_embed_url">Google Map Embed URL (Optional)</label>
                        <textarea
                            id="map_embed_url"
                            name="map_embed_url"
                            rows="2"
                            maxlength="12000"
                            placeholder="Paste embed URL or iframe HTML"
                            spellcheck="false"
                            aria-describedby="map-embed-help{{ $errors->has('map_embed_url') ? ' map-embed-error' : '' }}"
                            @if ($errors->has('map_embed_url')) aria-invalid="true" @endif
                        >{{ old('map_embed_url', $studio->map_embed_url) }}</textarea>
                        <p class="muted small" id="map-embed-help">Google Maps → Share → Embed a map → Copy HTML.</p>
                        @error('map_embed_url')
                            <span class="invalid-feedback" id="map-embed-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <details
                    class="studio-coordinates"
                    @if ($errors->hasAny(['country_code', 'latitude', 'longitude']) || $studio->latitude !== null || $studio->longitude !== null) open @endif
                >
                    <summary>Country &amp; coordinates (optional)</summary>
                    @php
                        $countries = collect(\Giggsey\Locale\Locale::getAllCountriesForLocale('en'))->sort();
                        $selectedCountry = old('country_code', $studio->exists ? $studio->country_code : 'IN');
                    @endphp
                    <div class="field">
                        <label for="country_code">Country</label>
                        <select
                            id="country_code"
                            name="country_code"
                            @if ($errors->has('country_code')) aria-invalid="true" aria-describedby="country_code-error" @endif
                        >
                            <option value="" @selected($selectedCountry === null || $selectedCountry === '')>Select country</option>
                            @if ($selectedCountry && ! $countries->has($selectedCountry))
                                <option value="{{ $selectedCountry }}" selected>Unknown country</option>
                            @endif
                            @foreach ($countries as $code => $country)
                                <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $country }}</option>
                            @endforeach
                        </select>
                        @error('country_code')
                            <span class="invalid-feedback" id="country_code-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="studio-field-grid">
                        @foreach (['latitude' => 'Latitude', 'longitude' => 'Longitude'] as $field => $label)
                            <div class="field">
                                <label for="{{ $field }}">{{ $label }}</label>
                                <input
                                    id="{{ $field }}"
                                    name="{{ $field }}"
                                    type="number"
                                    step="any"
                                    placeholder="{{ $field === 'latitude' ? 'e.g. 26.9124' : 'e.g. 75.7873' }}"
                                    value="{{ old($field, $studio->$field) }}"
                                    @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                                >
                                @error($field)
                                    <span class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </details>
            </details>
            <details class="panel form-stack studio-form-section studio-phones" aria-labelledby="contact-title" data-studio-panel open>
                <summary><h2 id="contact-title">Contact Information</h2></summary>
                @error('phones')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
                <ol data-phone-list>
                    @foreach (old('phones', $studio->phones) as $position => $phone)
                        <li data-phone-row>
                            <div class="field">
                                <label for="studio-phone-{{ $position }}" data-phone-label>Phone {{ $position + 1 }}{{ $position === 0 ? ' · Primary' : '' }}</label>
                                <input
                                    id="studio-phone-{{ $position }}"
                                    name="phones[{{ $position }}]"
                                    placeholder="e.g. +91 98765 43210"
                                    type="tel"
                                    value="{{ $phone }}"
                                    maxlength="40"
                                    required
                                    @if ($errors->has('phones.'.$position)) aria-invalid="true" aria-describedby="phone-error-{{ $position }}" @endif
                                >
                                @error('phones.'.$position)
                                    <span class="invalid-feedback" id="phone-error-{{ $position }}" data-phone-error>{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="actions" data-phone-actions hidden>
                                <button class="button secondary small-button" type="button" data-phone-move="-1" aria-label="Move phone up"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                <button class="button secondary small-button" type="button" data-phone-move="1" aria-label="Move phone down"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                                <button class="button secondary small-button" type="button" data-phone-remove aria-label="Remove phone"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <div><button class="button secondary small-button" type="button" data-phone-add hidden><i class="bi bi-plus-lg" aria-hidden="true"></i> Add phone number</button></div>
            </details>
            <details class="panel form-stack studio-form-section studio-visibility" aria-labelledby="visibility-title" data-studio-panel open>
                <summary><h2 id="visibility-title">Visibility &amp; Display Order</h2></summary>
                <div class="studio-field-grid">
                    <div class="field">
                        <label for="studio-status">Status <span aria-hidden="true">*</span></label>
                        <select
                            id="studio-status"
                            name="status"
                            required
                            @if ($errors->has('status')) aria-describedby="status-error" @endif
                            @if ($errors->has('status')) aria-invalid="true" @endif
                        >
                            @foreach (\App\Models\Studio::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', $studio->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <span class="invalid-feedback" id="status-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="sort_order">Display order <span aria-hidden="true">*</span></label>
                        <input
                            id="sort_order"
                            name="sort_order"
                            type="number"
                            min="0"
                            max="1000000"
                            value="{{ old('_image') ? $studio->sort_order : old('sort_order', $studio->sort_order) }}"
                            required
                            @if ($errors->has('sort_order')) aria-describedby="order-error" @endif
                            @if (! old('_image') && $errors->has('sort_order')) aria-invalid="true" @endif
                        >
                        @unless (old('_image'))
                            @error('sort_order')
                                <span class="invalid-feedback" id="order-error">{{ $message }}</span>
                            @enderror
                        @endunless
                    </div>
                </div>
            </details>
            @include('admin.studios.details-fields')
        </form>
        @if ($protectedUrl)
            <form id="studio-url-form" action="{{ route('admin.studios.url.update', $studio) }}" method="post" data-studio-url-form data-studio-name="{{ $studio->name }}" data-current-url="{{ rtrim(config('seo.origin'), '/').route('studios.show', ['slug' => $studio->slug], false) }}" data-url-prefix="{{ $studioUrlPrefix }}">
                @csrf
                <input type="hidden" name="expected_slug" value="{{ $studio->slug }}">
            </form>
        @endif
        @if ($studio->exists)
            <div class="studio-gallery-link"><a class="button secondary" href="{{ route('admin.studios.gallery.show', $studio) }}"><i class="bi bi-images" aria-hidden="true"></i> Manage Gallery</a></div>
        @endif
    </div>
@endsection
