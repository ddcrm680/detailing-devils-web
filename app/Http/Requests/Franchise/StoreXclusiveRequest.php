<?php

namespace App\Http\Requests\Franchise;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreXclusiveRequest extends BaseFranchiseApplicationRequest
{
    public function authorize(): bool
    {
        // Reject the disabled endpoint before location rules can query the CRM.
        if (! config('services.xclusive.submission_enabled')) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'X-Clusive applications are not accepting submissions yet.',
            ], 503));
        }

        return true;
    }

    public function rules(): array
    {
        $manualLocation = $this->input('country') === 'other';
        $businessOwner = $this->input('bussinessowner') === 'YES';
        $automotiveExperience = $this->input('experienceautomotive') === 'YES';
        $automotiveFacility = $this->input('existingautomotive') === 'YES';
        $hasProperty = $this->input('spacearound') === 'YES';

        return [
            'name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
            'lname' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:32', 'regex:/\A\+[0-9().\s-]+\z/'],
            'dob' => ['required', 'date_format:Y-m-d', 'before:today'],
            'profession' => ['nullable', 'string', 'max:150'],

            'country' => ['required', Rule::when(! $manualLocation, ['integer', 'min:1', Rule::exists('tbl_country_phone', 'id')])],
            'state' => [Rule::prohibitedIf($manualLocation), Rule::requiredIf(! $manualLocation), 'integer', 'min:1', Rule::exists('tbl_states', 'id')->where('country_id', $this->input('country'))],
            'city' => [Rule::prohibitedIf($manualLocation), Rule::requiredIf(! $manualLocation), 'integer', 'min:1', Rule::exists('tbl_cities', 'id')->where('state_id', $this->input('state'))],
            'another_country_name' => [Rule::excludeIf(! $manualLocation), Rule::requiredIf($manualLocation), 'string', 'max:150'],
            'another_state_name' => [Rule::excludeIf(! $manualLocation), Rule::requiredIf($manualLocation), 'string', 'max:100'],
            'another_city_name' => [Rule::excludeIf(! $manualLocation), Rule::requiredIf($manualLocation), 'string', 'max:100'],

            'hearabout' => ['required', Rule::in(config('form_options.xclusive.hearabout'))],
            'contacted' => ['required', Rule::in(config('form_options.xclusive.contacted'))],
            'franchiseapply' => ['required', 'string', 'max:100'],
            'wantstart' => ['required', Rule::in(array_column(config('form_options.xclusive.wantstart'), 'value'))],
            'newbussiness' => ['required', 'string', 'max:255'],

            'bussinessowner' => ['required', Rule::in(config('form_options.xclusive.yes_no'))],
            'type_of_bussiness' => [Rule::excludeIf(! $businessOwner), Rule::requiredIf($businessOwner), 'string', 'max:255'],
            'product_service' => [Rule::excludeIf(! $businessOwner), Rule::requiredIf($businessOwner), 'string', 'max:255'],
            'bussiness_description' => [Rule::excludeIf(! $businessOwner), Rule::requiredIf($businessOwner), 'string', 'max:255'],
            'experienceautomotive' => ['required', Rule::in(config('form_options.xclusive.yes_no'))],
            'experience' => [Rule::excludeIf(! $automotiveExperience), Rule::requiredIf($automotiveExperience), 'string', 'max:255'],
            'existingautomotive' => ['required', Rule::in(config('form_options.xclusive.yes_no'))],
            'existingfacility' => [Rule::excludeIf(! $automotiveFacility), Rule::requiredIf($automotiveFacility), 'string', 'max:255'],

            'spacearound' => ['required', Rule::in(config('form_options.xclusive.yes_no'))],
            'elaboratelocation' => [Rule::excludeIf(! $hasProperty), Rule::requiredIf($hasProperty), 'string', 'max:255'],
            'locationimage' => [Rule::excludeIf(! $hasProperty), Rule::requiredIf($hasProperty), 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10000', 'dimensions:max_width=10240,max_height=7680'],
            'spendfranchise' => ['required', Rule::in(config('form_options.xclusive.spendfranchise'))],
            'civillitigation' => ['required', Rule::in(config('form_options.xclusive.civillitigation'))],
            'website' => ['nullable', 'string', 'url:http,https', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Enter a valid first name.',
            'lname.regex' => 'Enter a valid last name.',
            'country.integer' => 'Select a listed country or choose Other country.',
            'state.exists' => 'Select a state in the chosen country.',
            'city.exists' => 'Select a city in the chosen state.',
            'phone.regex' => 'Enter a mobile number with its international calling code.',
            'locationimage.required' => 'Upload a location image when you have a property.',
            'locationimage.mimes' => 'Choose a JPEG, PNG or WebP location image.',
        ];
    }
}
