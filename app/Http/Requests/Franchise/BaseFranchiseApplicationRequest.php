<?php

namespace App\Http\Requests\Franchise;

use App\Services\Franchise\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

abstract class BaseFranchiseApplicationRequest extends FormRequest
{
    private ?string $normalizedMobile = null;

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('name') && ! $validator->errors()->has('lname')) {
                $fullName = trim($this->input('name').' '.$this->input('lname'));
                if (mb_strlen($fullName) > 100) {
                    $validator->errors()->add('lname', 'Your full name must be 100 characters or fewer.');
                }
            }

            if ($validator->errors()->has('phone')) {
                return;
            }

            $this->normalizedMobile = app(PhoneNumberNormalizer::class)->normalizeMobile($this->input('phone'));
            if ($this->normalizedMobile === null) {
                $validator->errors()->add('phone', 'Enter a valid international mobile number.');
            }
        }];
    }

    public function normalizedMobile(): string
    {
        if ($this->normalizedMobile === null) {
            throw new LogicException('The mobile number is only available after successful validation.');
        }

        return $this->normalizedMobile;
    }
}
