<?php

namespace App\Http\Requests\Franchise;

use App\Services\Franchise\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StorePrimeRequest extends FormRequest
{
    private ?string $normalizedMobile = null;

    private ?string $normalizedWhatsapp = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $separateWhatsapp = ! $this->boolean('same_as_mobile');
        $primeValues = fn (string $group): array => array_column(config("form_options.prime.{$group}"), 'value');

        // The browser supplies E.164 fields; the after hook verifies them against the
        // visible numbers with libphonenumber before they can be used by the backend.
        $visiblePhone = ['string', 'min:4', 'max:32', 'regex:/\A[+0-9(). -]+\z/'];
        $e164Phone = ['string', 'regex:/\A\+[1-9]\d{7,14}\z/'];

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
            'middle_name' => ['nullable', 'string', 'min:2', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
            'mobile_number' => ['required', ...$visiblePhone],
            'mobile_e164' => ['required', ...$e164Phone],
            'same_as_mobile' => ['sometimes', 'accepted'],
            'whatsapp_number' => [Rule::requiredIf($separateWhatsapp), 'nullable', ...$visiblePhone],
            'whatsapp_e164' => ['required', ...$e164Phone, Rule::when(! $separateWhatsapp, ['same:mobile_e164'])],
            'email' => ['required', 'string', 'email:rfc', 'max:120'],
            'age_group' => ['required', Rule::in($primeValues('age_groups'))],
            'occupation' => ['required', Rule::in($primeValues('occupations'))],

            'city' => ['required', 'string', 'min:2', 'max:80', "regex:/\A\p{L}[\p{L}\s'.()-]*\z/u"],
            'state' => ['required', Rule::in(config('form_options.common.states'))],
            'pin_code' => ['nullable', 'string', 'regex:/\A[1-9][0-9]{5}\z/'],

            'business_experience' => ['required', Rule::in($primeValues('business_experience'))],
            'business_name' => [Rule::requiredIf($this->input('business_experience') === 'yes'), 'nullable', 'string', 'min:2', 'max:120'],
            'industry' => [Rule::requiredIf($this->input('business_experience') === 'yes'), 'nullable', 'string', 'min:2', 'max:100'],

            'property_status' => ['required', Rule::in($primeValues('property_status'))],
            'property_location' => [Rule::requiredIf($this->input('property_status') === 'yes'), 'nullable', 'string', 'min:3', 'max:180'],
            'carpet_area' => [Rule::requiredIf($this->input('property_status') === 'yes'), 'nullable', 'numeric', 'between:100,100000'],
            'road_facing' => [Rule::requiredIf($this->input('property_status') === 'yes'), 'nullable', Rule::in($primeValues('road_facing'))],

            'preferred_city' => ['nullable', 'string', 'max:80'],
            'preferred_area' => ['nullable', 'string', 'max:120'],
            'start_timeline' => ['required', Rule::in($primeValues('timelines'))],
            'decision_maker' => ['required', Rule::in($primeValues('decision_maker'))],
            'contact_methods' => ['required', 'array', 'min:1', 'max:3'],
            'contact_methods.*' => ['required', 'string', 'distinct', Rule::in($primeValues('contact_methods'))],
            'partnership_reason' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $normalizer = app(PhoneNumberNormalizer::class);
            $this->normalizedMobile = $this->validatePhonePair($validator, $normalizer, 'mobile_number', 'mobile_e164');

            if ($this->boolean('same_as_mobile')) {
                if ($this->normalizedMobile !== null && ! $validator->errors()->has('whatsapp_e164')) {
                    $this->normalizedWhatsapp = $this->normalizedMobile;
                }
            } else {
                $this->normalizedWhatsapp = $this->validatePhonePair($validator, $normalizer, 'whatsapp_number', 'whatsapp_e164');
            }
        }];
    }

    /** @return array{mobile_e164: string, whatsapp_e164: string} */
    public function normalizedPhoneNumbers(): array
    {
        if ($this->normalizedMobile === null || $this->normalizedWhatsapp === null) {
            throw new LogicException('Phone numbers are only available after successful validation.');
        }

        return [
            'mobile_e164' => $this->normalizedMobile,
            'whatsapp_e164' => $this->normalizedWhatsapp,
        ];
    }

    private function validatePhonePair(Validator $validator, PhoneNumberNormalizer $normalizer, string $visibleField, string $internationalField): ?string
    {
        if ($validator->errors()->has($visibleField) || $validator->errors()->has($internationalField)) {
            return null;
        }

        $visible = $this->input($visibleField);
        $international = $this->input($internationalField);

        if (! is_string($visible) || ! is_string($international)) {
            return null;
        }

        $normalized = $normalizer->normalizeMobile($international);

        if ($normalized === null) {
            $message = $internationalField === 'whatsapp_e164'
                ? 'Enter a valid international WhatsApp number.'
                : 'Enter a valid international mobile number.';
            $validator->errors()->add($internationalField, $message);

            return null;
        }

        if (! $normalizer->matchesVisibleNumber($visible, $normalized)) {
            $validator->errors()->add($visibleField, 'This phone number does not match its international value.');

            return null;
        }

        return $normalized;
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'Enter a valid first name.',
            'middle_name.regex' => 'Enter a valid middle name.',
            'last_name.regex' => 'Enter a valid last name.',
            'mobile_number.regex' => 'Enter a valid mobile number.',
            'mobile_e164.regex' => 'Enter a valid international mobile number.',
            'whatsapp_number.required' => 'Enter your WhatsApp number when it differs from your mobile number.',
            'whatsapp_number.regex' => 'Enter a valid WhatsApp number.',
            'whatsapp_e164.regex' => 'Enter a valid international WhatsApp number.',
            'whatsapp_e164.same' => 'Your WhatsApp number must match your mobile number when “Same as mobile” is selected.',
            'pin_code.regex' => 'Enter a valid six-digit Indian PIN code.',
            'business_name.required' => 'Enter the name of your business.',
            'industry.required' => 'Enter your industry.',
            'property_location.required' => 'Enter the property location.',
            'carpet_area.required' => 'Enter the property carpet area.',
            'road_facing.required' => 'Select whether the property is road-facing.',
            'contact_methods.required' => 'Choose at least one contact method.',
            'contact_methods.*.in' => 'Choose a listed contact method.',
            'consent.accepted' => 'Please provide consent to continue.',
            'website.prohibited' => 'The application could not be accepted.',
        ];
    }
}
