@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', $studio->exists ? 'Edit studio' : 'Add studio')
@section('context', $studio->exists ? 'Edit studio' : 'Add studio')

@section('content')
    <div class="page-heading">
        <div>
            <h1>{{ $studio->exists ? $studio->name : 'Add New Studio' }}</h1>
            <p class="muted">Changes stay in the CMS during Phase 1. The public locator is unchanged.</p>
        </div>
        <a class="button secondary" href="{{ route('admin.studios.index') }}">Back to studios</a>
    </div>
    <form
        class="panel form-stack studio-workspace"
        data-studio-form
        action="{{ $studio->exists ? route('admin.studios.update', $studio) : route('admin.studios.store') }}"
        method="post"
    >
        @csrf
        @if ($studio->exists)
            @method('put')
        @endif
        <h2>Studio details</h2>
        <div class="studio-field-grid">
            @foreach ([
                'name' => ['Studio name', 255],
                'slug' => ['Unique slug', 191],
                'city' => ['City (optional)', 100],
                'state' => ['State', 100],
                'country_code' => ['Verified country code (optional)', 2],
            ] as $field => [$label, $limit])
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $studio->$field) }}" maxlength="{{ $limit }}" @required(in_array($field, ['name', 'slug', 'state'])) @if ($errors->has($field)) aria-invalid="true" @endif>
                    @error($field)<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
            @endforeach
        </div>
        <p class="small muted">Slugs use lowercase words and hyphens. Leave unknown city and country values blank; use a verified code such as IN only when confirmed.</p>
        <div class="field">
            <label for="address">Full address</label>
            <textarea id="address" name="address" rows="3" maxlength="4000" required>{{ old('address', $studio->address) }}</textarea>
            @error('address')<span class="invalid-feedback">{{ $message }}</span>@enderror
        </div>
        <fieldset class="studio-phones">
            <legend>Phone numbers</legend>
            <p class="muted small">Include the country code. The first number is the primary contact; formatting is preserved.</p>
            <ol data-phone-list>
                @foreach (old('phones', $studio->phones) as $position => $phone)
                    <li data-phone-row>
                        <label class="field">
                            <span data-phone-label>Phone {{ $position + 1 }}{{ $position === 0 ? ' · Primary' : '' }}</span>
                            <input name="phones[{{ $position }}]" type="tel" value="{{ $phone }}" maxlength="40" required>
                        </label>
                        <div class="actions" data-phone-actions hidden>
                            <button class="button secondary small-button" type="button" data-phone-move="-1" aria-label="Move phone up">↑</button>
                            <button class="button secondary small-button" type="button" data-phone-move="1" aria-label="Move phone down">↓</button>
                            <button class="button secondary small-button" type="button" data-phone-remove>Remove</button>
                        </div>
                    </li>
                @endforeach
            </ol>
            <button class="button secondary small-button" type="button" data-phone-add hidden>Add phone number</button>
        </fieldset>
        <div class="field">
            <label for="map_url">Google Maps URL</label>
            <input id="map_url" name="map_url" type="url" value="{{ old('map_url', $studio->map_url) }}" maxlength="2048" placeholder="https://maps.app.goo.gl/…">
        </div>
        <div class="studio-field-grid">
            @foreach (['latitude' => 'Latitude (optional)', 'longitude' => 'Longitude (optional)'] as $field => $label)
                <div class="field">
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="number" step="any" value="{{ old($field, $studio->$field) }}">
                </div>
            @endforeach
            <div class="field">
                <label for="studio-status">Status</label>
                <select id="studio-status" name="status" required>
                    @foreach (\App\Models\Studio::STATUSES as $status)
                        <option value="{{ $status }}" @selected(old('status', $studio->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="sort_order">Display order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="1000000" value="{{ old('sort_order', $studio->sort_order) }}" required>
            </div>
        </div>
        <div class="actions">
            <button class="button" type="submit">{{ $studio->exists ? 'Save studio' : 'Create studio' }}</button>
            @if ($studio->exists)
                <a class="button secondary" href="{{ route('admin.studios.show', $studio) }}">View studio</a>
            @endif
        </div>
    </form>
    @if ($studio->exists)
        @include('admin.studios.gallery')
    @else
        <p class="notice">Create the studio first, then upload its gallery images.</p>
    @endif
@endsection
