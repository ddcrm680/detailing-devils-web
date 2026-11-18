<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentUrl;
use App\Models\Studio;
use App\Services\StudioUrls;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->user()?->can('manage-studios')) {
            return;
        }
        $studio = $this->route('studio');
        if ($studio instanceof Studio) {
            if ($this->input('slug_mode') !== 'manual') {
                $this->merge(['slug' => $studio->slug]);
            }

            return;
        }
        if ($this->input('slug_mode') === 'manual'
            || ($this->input('slug_mode') !== 'auto' && $this->filled('slug'))) {
            return;
        }
        $this->merge(['slug' => null]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-studios') === true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'bail', 'required', 'string', 'max:255',
                function ($attribute, $value, $fail) {
                    $normalize = fn (string $name): string => mb_strtolower(preg_replace('/^\s+|\s+$/u', '', $name));
                    $studio = $this->route('studio');
                    $duplicate = Studio::query()
                        ->when($studio instanceof Studio, fn ($query) => $query->whereKeyNot($studio->id))
                        ->pluck('name')
                        ->contains(fn ($name) => $normalize($name) === $normalize($value));
                    if ($duplicate) {
                        $fail('A studio with this name already exists.');
                    }
                },
            ],
            'slug' => [
                'bail', $this->route('studio') ? 'required' : 'nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
                Rule::unique('studios', 'slug')->ignore($this->route('studio')),
                function ($attribute, $value, $fail) {
                    $studio = $this->route('studio');
                    if ((! $studio || $value !== $studio->slug)
                        && ContentUrl::query()->whereIn('path', StudioUrls::paths($value))->exists()) {
                        $fail('This URL is reserved. Please choose another.');
                    }
                },
            ],
            'address' => ['required', 'string', 'max:4000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'phones' => ['required', 'array', 'min:1', 'max:10'],
            'phones.*' => [
                'required', 'string', 'max:40',
                'regex:/^(?:\+|\(\+?)?[0-9][0-9 ()-]*[0-9]$/',
                function ($attribute, $value, $fail) {
                    $digits = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('Each phone must contain 7–15 digits, including the country code.');
                    }
                },
            ],
            'map_url' => [
                'nullable', 'string', 'max:2048', 'url:https',
                function ($attribute, $value, $fail) {
                    $parts = parse_url($value);
                    $host = strtolower($parts['host'] ?? '');
                    $path = $parts['path'] ?? '';
                    $googleMaps = in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
                        || (in_array($host, ['google.com', 'www.google.com'], true)
                            && preg_match('~^/maps(?:/|$)~', $path))
                        || ($host === 'goo.gl' && str_starts_with($path, '/maps/'));
                    if (! $googleMaps || isset($parts['user']) || isset($parts['pass'])
                        || (isset($parts['port']) && $parts['port'] !== 443)
                        || str_contains($value, '\\')) {
                        $fail('Use an HTTPS Google Maps sharing or location link.');
                    }
                },
            ],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(Studio::STATUSES)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'This URL is already in use. Please choose another.',
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens.',
            'phones.*.regex' => 'Enter a phone number with its country code; spaces and brackets are allowed.',
            'country_code.regex' => 'Enter a verified two-letter uppercase country code, or leave it blank.',
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => 'Studio Page URL',
            'phones.*' => 'phone number',
            'map_url' => 'Google Maps link',
            'country_code' => 'country code',
            'sort_order' => 'display order',
        ];
    }
}
