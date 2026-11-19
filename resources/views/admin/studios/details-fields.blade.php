@php
    $catalogue = \App\Support\StudioServices::all();
    $newDefaults = ! $studio->exists && ! old('studio_details_present');
    $selectedServices = old('studio_details_present') ? old('services', []) : ($studio->services ?? ($newDefaults ? array_keys($catalogue) : []));
    $selectedServices = is_array($selectedServices) ? $selectedServices : [];
    $defaultWeek = array_fill_keys(array_keys(\App\Models\Studio::WEEKDAYS), ['closed' => false, 'opens' => '10:00', 'closes' => '19:00']);
    $week = old('studio_details_present') ? old('opening_hours', []) : ($studio->opening_hours ?? ($newDefaults ? $defaultWeek : []));
    $week = is_array($week) ? $week : [];
@endphp
<input type="hidden" name="studio_details_present" value="1">
<details class="panel form-stack studio-form-section studio-services" data-studio-panel open>
    <summary @if ($errors->get("services*")) aria-invalid="true" @endif><h2>Services Offered</h2></summary>
    <div class="studio-details-tools" data-service-actions hidden>
        <button class="button secondary small-button" type="button" data-services-all>Select All</button>
        <button class="button secondary small-button" type="button" data-services-clear>Clear</button>
    </div>
    <div class="studio-services-options">
        @foreach ($catalogue as $id => $service)
            <label class="check">
                <input type="checkbox" name="services[]" value="{{ $id }}" @checked(in_array($id, $selectedServices, true))>
                {{ $service['name'] }}
            </label>
        @endforeach
    </div>
    @foreach ($errors->get('services*') as $messages)
        @foreach ($messages as $message)
            <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @endforeach
    @endforeach
</details>
<details class="panel form-stack studio-form-section studio-opening-hours" data-studio-panel data-opening-hours open>
    <summary @if ($errors->get("opening_hours*")) aria-invalid="true" @endif><h2>Opening Hours</h2></summary>
    <div class="studio-hours-compact" data-hours-compact hidden>
        <div class="studio-hours-pair">
            <div class="field"><label for="common-hours-opens">Opens</label><input type="time" id="common-hours-opens" data-common-opens></div>
            <div class="field"><label for="common-hours-closes">Closes</label><input type="time" id="common-hours-closes" data-common-closes></div>
        </div>
        <label class="check"><input type="checkbox" data-open-all-days> Open All 7 Days</label>
        <div class="studio-details-tools">
            <button class="button secondary small-button" type="button" data-hours-preset>Use 10 AM to 7 PM</button>
            <button class="button secondary small-button" type="button" data-hours-clear>Clear Hours</button>
        </div>
    </div>
    <div class="studio-hours-meta">
        <span class="muted small" data-hours-status role="status"></span>
        @if ($newDefaults)<span class="muted small">Starting hours. Confirm before saving.</span>@endif
    </div>
    @error('opening_hours')<span class="invalid-feedback" role="alert">{{ $message }}</span>@enderror
    <details class="studio-hours-customize" data-hours-customize open>
        <summary>Customize Days</summary>
        <div class="studio-week-labels" aria-hidden="true"><span>Day</span><span>Opens</span><span>Closes</span><span>Closed</span></div>
        @foreach (\App\Models\Studio::WEEKDAYS as $day => $label)
            @php
                $hours = is_array($week[$day] ?? null) ? $week[$day] : [];
                $closed = in_array($hours['closed'] ?? false, [true, 1, '1'], true);
            @endphp
            <div class="studio-hours-row" data-hours-day="{{ $day }}">
                <span class="studio-hours-day">{{ $label }}</span>
                @foreach (['opens' => 'Opens', 'closes' => 'Closes'] as $key => $text)
                    <div class="field studio-hours-time">
                        <label class="sr-only" for="hours-{{ $day }}-{{ $key }}">{{ $label }} {{ strtolower($text) }}</label>
                        <input type="time" id="hours-{{ $day }}-{{ $key }}" name="opening_hours[{{ $day }}][{{ $key }}]" value="{{ is_string($hours[$key] ?? null) ? $hours[$key] : '' }}" aria-label="{{ $label }} {{ strtolower($text) }}" data-hours-{{ $key }} @if ($errors->has("opening_hours.$day.$key")) aria-invalid="true" aria-describedby="hours-{{ $day }}-{{ $key }}-error" @endif>
                        @error("opening_hours.$day.$key")<span class="invalid-feedback" id="hours-{{ $day }}-{{ $key }}-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
                <label class="check studio-hours-closed">
                    <input type="hidden" name="opening_hours[{{ $day }}][closed]" value="0">
                    <input type="checkbox" name="opening_hours[{{ $day }}][closed]" value="1" data-hours-closed @checked($closed) aria-label="{{ $label }} closed"> <span class="studio-hours-closed-text">Closed</span>
                </label>
                @foreach (['closed', ''] as $key)
                    @error(rtrim("opening_hours.$day.$key", '.'))<span class="invalid-feedback studio-hours-error" role="alert">{{ $message }}</span>@enderror
                @endforeach
            </div>
        @endforeach
    </details>
</details>
