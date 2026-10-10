<?php

namespace App\Http\Requests\Franchise;

use App\Services\Franchise\OtpService;
use App\Services\Franchise\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class SendOtpRequest extends FormRequest
{
    private ?string $normalizedMobile = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(OtpService::PURPOSES)],
            'mobile' => ['bail', 'required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:50', "regex:/\A\p{L}[\p{L}\s'.-]*\z/u"],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (strlen($this->getContent()) > 2048) {
                $validator->errors()->add('request', 'The OTP request is too large.');
            }

            foreach (array_diff(array_keys($this->all()), ['purpose', 'mobile', 'name', '_token']) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if (! $validator->errors()->has('mobile') && is_string($this->input('mobile'))) {
                $this->normalizedMobile = app(PhoneNumberNormalizer::class)->normalizeMobile($this->input('mobile'));

                if ($this->normalizedMobile === null) {
                    $validator->errors()->add('mobile', 'Enter a valid international mobile number.');
                }
            }
        }];
    }

    public function normalizedMobile(): string
    {
        return $this->normalizedMobile ?? throw new LogicException('Mobile number is only available after successful validation.');
    }
}
