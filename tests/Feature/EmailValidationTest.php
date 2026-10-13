<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Email validation tests require isolated in-memory SQLite.');
        }

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('services.sms_alert.enabled', false);
        config()->set('services.zerobounce.api_key', 'test-key');
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_valid_email_is_confirmed_without_exposing_provider_details(): void
    {
        Http::fake(['api.zerobounce.net/*' => Http::response(['status' => 'valid', 'address' => 'person@example.com'])]);

        $this->postJson(route('email.validate'), ['email' => ' person@example.com '])
            ->assertOk()
            ->assertExactJson(['success' => true, 'result' => 'valid', 'message' => 'Valid email.']);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.zerobounce.net/v2/validate'
            && $request['api_key'] === 'test-key'
            && $request['email'] === 'person@example.com');
    }

    public function test_valid_result_creates_a_short_lived_proof_for_the_normalized_email_in_this_session(): void
    {
        Http::fake(['api.zerobounce.net/*' => Http::response(['status' => 'valid'])]);

        $this->postJson(route('email.validate'), ['email' => ' Person@Example.COM '])
            ->assertOk()
            ->assertSessionHas('email_validation.proof');

        $proof = session('email_validation.proof');
        $this->assertSame(
            hash_hmac('sha256', 'person@example.com', (string) config('app.key')),
            $proof['email_hash']
        );
        $this->assertGreaterThanOrEqual(now()->addMinutes(10)->timestamp - 1, $proof['expires_at']);
        $this->assertLessThanOrEqual(now()->addMinutes(10)->timestamp + 1, $proof['expires_at']);
        $this->assertArrayNotHasKey('email', $proof);

        session()->invalidate();
        $this->assertNull(session('email_validation.proof'));
    }

    public function test_invalid_and_unavailable_checks_clear_a_prior_proof(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'valid'])
            ->push(['status' => 'invalid'])
            ->push(['status' => 'valid'])
            ->push(['error' => 'provider failure'], 503);

        $this->postJson(route('email.validate'), ['email' => 'first@example.com'])->assertOk();
        $this->assertNotNull(session('email_validation.proof'));

        $this->postJson(route('email.validate'), ['email' => 'second@example.com'])
            ->assertJsonPath('result', 'invalid')
            ->assertSessionMissing('email_validation.proof');

        $this->postJson(route('email.validate'), ['email' => 'first@example.com'])->assertOk();
        $this->assertNotNull(session('email_validation.proof'));

        $this->postJson(route('email.validate'), ['email' => 'third@example.com'])
            ->assertStatus(503)
            ->assertSessionMissing('email_validation.proof');
    }

    public function test_only_provider_valid_status_is_accepted(): void
    {
        foreach (['invalid', 'catch-all', 'unknown', 'do_not_mail'] as $status) {
            Http::fake(['api.zerobounce.net/*' => Http::response(['status' => $status])]);

            $this->postJson(route('email.validate'), ['email' => 'person@example.com'])
                ->assertOk()
                ->assertExactJson([
                    'success' => false,
                    'result' => 'invalid',
                    'message' => 'Email could not be verified as deliverable.',
                ]);
        }
    }

    public function test_invalid_format_is_rejected_before_provider_call(): void
    {
        Http::fake();

        foreach (['invalid', '', ['nested'], str_repeat('a', 255).'@example.com'] as $email) {
            $this->postJson(route('email.validate'), ['email' => $email])
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath('result', 'invalid')
                ->assertJsonPath('errors.email.0', 'Enter a valid email address.');
        }

        Http::assertNothingSent();
    }

    public function test_missing_api_key_fails_without_provider_call(): void
    {
        config()->set('services.zerobounce.api_key', null);
        Http::fake();

        $this->postJson(route('email.validate'), ['email' => 'person@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('result', 'unavailable');

        Http::assertNothingSent();
    }

    public function test_provider_failure_and_unexpected_response_are_unavailable(): void
    {
        Http::fake(['api.zerobounce.net/*' => Http::response(['error' => 'provider error'], 500)]);
        $this->postJson(route('email.validate'), ['email' => 'person@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('result', 'unavailable')
            ->assertDontSee('provider error');

        Http::fake(['api.zerobounce.net/*' => Http::response(['error' => 'invalid key'])]);
        $this->postJson(route('email.validate'), ['email' => 'person@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('result', 'unavailable');
    }

    public function test_connection_failure_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('network failure'));

        $this->postJson(route('email.validate'), ['email' => 'person@example.com'])
            ->assertStatus(503)
            ->assertJsonPath('result', 'unavailable')
            ->assertDontSee('network failure');
    }

    public function test_throttle_limits_paid_validation_calls(): void
    {
        Http::fake(['api.zerobounce.net/*' => Http::response(['status' => 'valid'])]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('email.validate'), ['email' => 'person@example.com'])->assertOk();
        }

        $this->postJson(route('email.validate'), ['email' => 'person@example.com'])->assertTooManyRequests();
        Http::assertSentCount(5);
    }
}
