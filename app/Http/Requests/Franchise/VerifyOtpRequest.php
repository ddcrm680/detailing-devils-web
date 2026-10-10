<?php

namespace App\Http\Requests\Franchise;

use App\Services\Franchise\OtpService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(OtpService::PURPOSES)],
            'otp' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (strlen($this->getContent()) > 2048) {
                $validator->errors()->add('request', 'The OTP request is too large.');
            }

            foreach (array_diff(array_keys($this->all()), ['purpose', 'otp', '_token']) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        }];
    }
}
