<?php

namespace Tests\Unit;

use App\Services\Franchise\PhoneNumberNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    public function test_it_normalizes_indian_and_international_mobile_numbers(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        $this->assertSame('+919876543210', $normalizer->normalizeMobile('+91 98765 43210'));
        $this->assertTrue($normalizer->matchesVisibleNumber('98765 43210', '+919876543210'));

        $this->assertSame('+447123456789', $normalizer->normalizeMobile('+44 7123 456789'));
        $this->assertTrue($normalizer->matchesVisibleNumber('7123 456789', '+447123456789'));
    }

    public function test_it_rejects_invalid_numbers_and_a_mismatched_visible_number(): void
    {
        $normalizer = new PhoneNumberNormalizer;

        $this->assertNull($normalizer->normalizeMobile('+91987654321'));
        $this->assertNull($normalizer->normalizeMobile('9876543210'));
        $this->assertNull($normalizer->normalizeMobile('+911141234567'));
        $this->assertFalse($normalizer->matchesVisibleNumber('9123456789', '+919876543210'));
        $this->assertFalse($normalizer->matchesVisibleNumber('987654321', '+91987654321'));
    }
}
