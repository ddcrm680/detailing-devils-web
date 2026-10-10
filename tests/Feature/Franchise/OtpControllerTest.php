<?php

namespace Tests\Feature\Franchise;

use App\Contracts\Franchise\SmsDelivery;
use App\Services\Franchise\OtpService;
use App\Services\Franchise\SmsAlertDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\Franchise\FakeSmsDelivery;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    private FakeSmsDelivery $sms;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('services.sms_alert', [
            'enabled' => true,
            'storage_ready' => true,
            'api_key' => 'example-key',
            'sender' => 'EXAMPLE',
        ]);
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('b', 40));

        $this->sms = new FakeSmsDelivery;
        $this->app->instance(SmsDelivery::class, $this->sms);
    }

    public function test_send_and_verify_prime_with_encrypted_session_context_and_single_use_proof(): void
    {
        $this->postJson('/otp/send', $this->sendPayload())
            ->assertOk()->assertJsonPath('success', true)->assertJsonMissingPath('status');

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+919876543210', $this->sms->sent[0]['mobile']);
        $context = $this->app['session']->driver()->get('franchise.otp.pending.prime');
        $this->assertIsArray($context);
        $this->assertNotSame('+919876543210', $context['mobile']);
        $this->assertSame('+919876543210', Crypt::decryptString($context['mobile']));

        $code = $this->sentCode();
        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => $code])
            ->assertOk()->assertJsonPath('success', true);
        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => $code])
            ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonMissingPath('status');

        $otp = app(OtpService::class);
        $this->assertTrue($otp->consumeVerification('prime', '+919876543210'));
        $this->assertFalse($otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_fofo_context_cannot_be_verified_as_prime(): void
    {
        $this->postJson('/otp/send', $this->sendPayload('fofo'))->assertOk();
        $code = $this->sentCode();

        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => $code])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson('/otp/verify', ['purpose' => 'fofo', 'otp' => $code])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_invalid_payloads_return_laravel_validation_errors_without_sending(): void
    {
        $this->postJson('/otp/send', ['purpose' => 'prime', 'mobile' => 'invalid', 'extra' => 'no'])
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile', 'extra']);
        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => '12'])
            ->assertUnprocessable()->assertJsonValidationErrors(['otp']);

        $this->assertSame([], $this->sms->sent);
    }

    public function test_disabled_or_incomplete_configuration_returns_503_without_sending(): void
    {
        config()->set('services.sms_alert.enabled', false);
        $this->postJson('/otp/send', $this->sendPayload())->assertStatus(503)->assertJsonPath('success', false);

        config()->set('services.sms_alert.enabled', true);
        config()->set('services.sms_alert.storage_ready', false);
        $this->postJson('/otp/send', $this->sendPayload())->assertStatus(503)->assertJsonPath('success', false);

        config()->set('services.sms_alert.storage_ready', true);
        config()->set('services.sms_alert.api_key', null);
        $this->postJson('/otp/send', $this->sendPayload())->assertStatus(503)->assertJsonPath('success', false);

        $this->assertSame([], $this->sms->sent);
    }

    public function test_missing_applicant_name_returns_validation_error_without_sending(): void
    {
        $this->postJson('/otp/send', ['purpose' => 'prime', 'mobile' => '+919876543210'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->assertSame([], $this->sms->sent);
    }

    public function test_cooldown_returns_429_with_retry_after(): void
    {
        $this->postJson('/otp/send', $this->sendPayload())->assertOk();

        $this->postJson('/otp/send', $this->sendPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('success', false);

        $this->assertCount(1, $this->sms->sent);
    }

    public function test_wrong_codes_never_produce_success(): void
    {
        $this->postJson('/otp/send', $this->sendPayload())->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => '000000'])
                ->assertUnprocessable()->assertJsonPath('success', false);
        }

        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => $this->sentCode()])
            ->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_expired_pending_context_cannot_verify(): void
    {
        $this->postJson('/otp/send', $this->sendPayload())->assertOk();
        $this->travel(601)->seconds();

        $this->postJson('/otp/verify', ['purpose' => 'prime', 'otp' => $this->sentCode()])
            ->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_provider_rate_limit_maps_to_429_without_returning_the_code(): void
    {
        $this->app->forgetInstance(SmsDelivery::class);
        $this->app->bind(SmsDelivery::class, SmsAlertDelivery::class);
        Http::fake(['https://www.smsalert.co.in/*' => Http::response(['status' => 'error'], 429, ['Retry-After' => '45'])]);

        $this->postJson('/otp/send', $this->sendPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After', '45')
            ->assertJsonPath('success', false)
            ->assertDontSee('123456');

        Http::assertSentCount(1);
    }

    public function test_rejected_and_throwing_fake_delivery_return_503_and_allow_a_retry(): void
    {
        $this->sms->accept = false;
        $this->postJson('/otp/send', $this->sendPayload())->assertStatus(503)->assertJsonPath('success', false);

        $this->sms->throw = true;
        $this->postJson('/otp/send', $this->sendPayload())->assertStatus(503)->assertJsonPath('success', false);

        $this->sms->throw = false;
        $this->sms->accept = true;
        $this->postJson('/otp/send', $this->sendPayload())->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $this->sms->sent);
    }

    /** @return array{purpose: string, mobile: string, name: string} */
    private function sendPayload(string $purpose = 'prime'): array
    {
        return ['purpose' => $purpose, 'mobile' => '+919876543210', 'name' => 'Asha'];
    }

    private function sentCode(): string
    {
        $message = $this->sms->sent[0]['message'];
        $this->assertMatchesRegularExpression('/\AHello Asha, [0-9]{6} is your OTP for Signup at Detailing Devils India Pvt Ltd\.\z/', $message);
        preg_match('/\b[0-9]{6}\b/', $message, $matches);

        return $matches[0];
    }
}
