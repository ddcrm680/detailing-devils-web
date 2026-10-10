<?php

namespace Tests\Feature\Franchise;

use App\Http\Requests\Franchise\SendOtpRequest;
use App\Http\Requests\Franchise\VerifyOtpRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OtpRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_test/otp-send-validation', function (SendOtpRequest $request) {
            return response()->json(['mobile' => $request->normalizedMobile()]);
        });
        Route::post('/_test/otp-verify-validation', function (VerifyOtpRequest $request) {
            return response()->json($request->validated());
        });
    }

    public function test_send_accepts_supported_purposes_and_normalizes_international_mobiles(): void
    {
        $this->postJson('/_test/otp-send-validation', [
            'purpose' => 'prime', 'mobile' => '+919876543210', 'name' => 'Asha',
        ])->assertOk()->assertJsonPath('mobile', '+919876543210');

        $this->postJson('/_test/otp-send-validation', [
            'purpose' => 'fofo', 'mobile' => '+44 7123 456789', 'name' => 'Asha',
        ])->assertOk()->assertJsonPath('mobile', '+447123456789');
    }

    public function test_send_rejects_invalid_purpose_mobile_name_and_extra_fields(): void
    {
        $this->postJson('/_test/otp-send-validation', [
            'purpose' => 'admin', 'mobile' => '+91987654321', 'name' => str_repeat('A', 51), 'debug' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose', 'mobile', 'name', 'debug'])
            ->assertJsonStructure(['message', 'errors' => ['purpose', 'mobile', 'name', 'debug']]);
    }

    public function test_send_requires_applicant_name_for_the_existing_otp_message(): void
    {
        $this->postJson('/_test/otp-send-validation', [
            'purpose' => 'prime', 'mobile' => '+919876543210',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_send_rejects_a_oversized_request_body(): void
    {
        $this->postJson('/_test/otp-send-validation', [
            'purpose' => 'prime', 'mobile' => '+919876543210', 'name' => str_repeat('A', 2100),
        ])->assertUnprocessable()->assertJsonValidationErrors(['request']);
    }

    public function test_verify_accepts_six_digit_string_including_leading_zero(): void
    {
        $this->postJson('/_test/otp-verify-validation', [
            'purpose' => 'fofo', 'otp' => '012345',
        ])->assertOk()->assertJsonPath('otp', '012345');
    }

    public function test_verify_rejects_bad_codes_unsupported_purposes_and_unexpected_mobile(): void
    {
        $this->postJson('/_test/otp-verify-validation', [
            'purpose' => 'other', 'otp' => '12345a', 'mobile' => '+919876543210',
        ])->assertUnprocessable()->assertJsonValidationErrors(['purpose', 'otp', 'mobile']);

        $this->postJson('/_test/otp-verify-validation', [
            'purpose' => 'prime', 'otp' => 123456,
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);
    }

    public function test_verify_rejects_an_oversized_request_body(): void
    {
        $this->postJson('/_test/otp-verify-validation', [
            'purpose' => 'prime', 'otp' => '123456', 'extra' => str_repeat('x', 2100),
        ])->assertUnprocessable()->assertJsonValidationErrors(['request', 'extra']);
    }
}
