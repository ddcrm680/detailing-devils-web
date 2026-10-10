<?php

namespace App\Services\Franchise;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    private readonly PhoneNumberUtil $phoneUtil;

    public function __construct()
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
    }

    public function normalizeMobile(string $international): ?string
    {
        if (! str_starts_with($international, '+')) {
            return null;
        }

        try {
            $number = $this->phoneUtil->parse($international);
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->phoneUtil->isValidNumber($number)) {
            return null;
        }

        if (! in_array($this->phoneUtil->getNumberType($number), [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
            return null;
        }

        return $this->phoneUtil->format($number, PhoneNumberFormat::E164);
    }

    public function matchesVisibleNumber(string $visible, string $normalized): bool
    {
        if ($this->normalizeMobile($normalized) !== $normalized) {
            return false;
        }

        try {
            $international = $this->phoneUtil->parse($normalized);
            $region = $this->phoneUtil->getRegionCodeForNumber($international);

            if ($region === null || $region === '001') {
                return false;
            }

            $national = $this->phoneUtil->parse($visible, $region);

            return $this->phoneUtil->format($national, PhoneNumberFormat::E164) === $normalized;
        } catch (NumberParseException) {
            return false;
        }
    }
}
