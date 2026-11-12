<?php

namespace App\Http\Requests\Admin;

use App\Models\Studio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-studios') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('studios', 'slug')->ignore($this->route('studio')),
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
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens for the slug.',
            'phones.*.regex' => 'Enter a phone number with its country code; spaces and brackets are allowed.',
            'country_code.regex' => 'Enter a verified two-letter uppercase country code, or leave it blank.',
        ];
    }
}
