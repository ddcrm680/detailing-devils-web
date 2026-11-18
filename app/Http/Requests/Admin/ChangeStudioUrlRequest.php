<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeStudioUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-studios') === true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'expected_slug' => ['required', 'string', 'max:191'],
            'confirm_url_change' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens.',
            'confirm_url_change.accepted' => 'Please confirm the URL change.',
        ];
    }

    public function attributes(): array
    {
        return ['slug' => 'Studio Page URL'];
    }
}
