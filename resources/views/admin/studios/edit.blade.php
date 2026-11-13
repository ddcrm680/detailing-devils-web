@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', $studio->exists ? 'Edit studio' : 'Add studio')
@section('context', $studio->exists ? 'Edit studio' : 'Add studio')

@section('content')
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
                    @foreach (['name' => ['Studio name', 255], 'slug' => ['Slug', 191]] as $field => [$label, $limit])
                        <div class="field" @if ($field === 'slug') data-slug-editor data-new="{{ $studio->exists ? 'false' : 'true' }}" data-slugs="{{ json_encode($existingSlugs ?? []) }}" @endif>
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
                                    <button class="button secondary" type="button" data-slug-edit>Edit slug</button>
                                </div>
                                <noscript><label class="check"><input type="checkbox" name="slug_mode" value="manual">Edit slug</label></noscript>
                            @endif
                            @error($field)
                                <span class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</span>
                            @enderror
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
                        'country_code' => ['Country code (optional)', 2],
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
                                @if ($errors->has($field)) aria-invalid="true" @endif
                                @if ($field === 'country_code')
                                    placeholder="e.g. IN"
                                    @if ($errors->has($field)) aria-describedby="country_code-error" @endif
                                @elseif ($errors->has($field))
                                    aria-describedby="{{ $field }}-error"
                                @endif
                            >
                            @error($field)
                                <span class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <div class="field">
                    <label for="map_url">Google Maps URL (optional)</label>
                    <input
                        id="map_url"
                        name="map_url"
                        type="url"
                        value="{{ old('map_url', $studio->map_url) }}"
                        maxlength="2048"
                        placeholder="https://maps.app.goo.gl/…"
                        @if ($errors->has('map_url')) aria-invalid="true" aria-describedby="map-error" @endif
                    >
                    @error('map_url')
                        <span class="invalid-feedback" id="map-error">{{ $message }}</span>
                    @enderror
                </div>
                <details
                    class="studio-coordinates"
                    @if ($errors->hasAny(['latitude', 'longitude']) || $studio->latitude !== null || $studio->longitude !== null) open @endif
                >
                    <summary>Coordinates (optional)</summary>
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
        </form>
        @if ($studio->exists)
            <div class="studio-gallery-link"><a class="button secondary" href="{{ route('admin.studios.gallery.show', $studio) }}"><i class="bi bi-images" aria-hidden="true"></i> Manage Gallery</a></div>
        @endif
    </div>
@endsection
