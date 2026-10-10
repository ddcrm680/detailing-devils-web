<?php

namespace Tests\Feature\Franchise;

use App\Services\Franchise\OtpDeliveryService;
use App\Services\Franchise\OtpException;
use App\Services\Franchise\OtpService;
use App\Services\Franchise\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\Fakes\Franchise\FakeSmsDelivery;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $otp;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('app.key', 'local-otp-test-key');
        Cache::store('array')->flush();
        $this->sessionId('otp-test-session-one');
        $this->otp = app(OtpService::class);
    }

    public function test_it_stores_only_a_hash_and_consumes_a_verified_proof_once(): void
    {
        $code = $this->otp->createChallenge('prime', '+919876543210')->code;
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);

        $record = Cache::store('array')->get($this->challengeKey('prime'));
        $this->assertSame('pending', $record['state']);
        $this->assertNotSame($code, $record['code_hash']);
        $this->assertTrue(Hash::check($code, $record['code_hash']));
        $this->assertStringNotContainsString('+919876543210', json_encode($record));

        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $code));
        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $code));
        $this->assertArrayNotHasKey('code_hash', Cache::store('array')->get($this->challengeKey('prime')));
        $this->assertTrue($this->otp->consumeVerification('prime', '+919876543210'));
        $this->assertFalse($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_wrong_codes_exhaust_the_verification_attempts(): void
    {
        $code = $this->otp->createChallenge('prime', '+919876543210')->code;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', '000000'));
        }

        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $code));
        $this->assertFalse($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_a_challenge_and_verification_proof_expire(): void
    {
        $code = $this->otp->createChallenge('prime', '+919876543210')->code;
        $this->travel(601)->seconds();
        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $code));

        $code = $this->otp->createChallenge('prime', '+919876543210')->code;
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $code));
        $this->travel(601)->seconds();
        $this->assertFalse($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_resending_supersedes_the_previous_code_and_proof(): void
    {
        $oldCode = $this->otp->createChallenge('prime', '+919876543210')->code;
        $this->travel(31)->seconds();
        $newCode = $this->otp->createChallenge('prime', '+919876543210')->code;

        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $oldCode));
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $newCode));

        $this->travel(31)->seconds();
        $this->otp->createChallenge('prime', '+919876543210');
        $this->assertFalse($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_changing_the_phone_replaces_the_challenge_for_the_same_form(): void
    {
        $oldCode = $this->otp->createChallenge('prime', '+919876543210')->code;
        $this->travel(31)->seconds();
        $newCode = $this->otp->createChallenge('prime', '+447123456789')->code;

        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $oldCode));
        $this->assertTrue($this->otp->verifyChallenge('prime', '+447123456789', $newCode));
    }

    public function test_cooldown_and_per_session_sending_limit_are_enforced(): void
    {
        $this->otp->createChallenge('prime', '+919876543210');

        try {
            $this->otp->createChallenge('prime', '+919876543210');
            $this->fail('A resend during cooldown must be rejected.');
        } catch (OtpException $exception) {
            $this->assertSame(OtpException::COOLDOWN, $exception->reason);
        }

        for ($send = 1; $send < 5; $send++) {
            $this->travel(31)->seconds();
            $this->otp->createChallenge('prime', '+919876543210');
        }

        $this->travel(31)->seconds();

        try {
            $this->otp->createChallenge('prime', '+919876543210');
            $this->fail('The per-session send limit must be enforced.');
        } catch (OtpException $exception) {
            $this->assertSame(OtpException::SEND_LIMIT, $exception->reason);
        }
    }

    public function test_the_phone_sending_limit_applies_across_sessions(): void
    {
        for ($send = 0; $send < 10; $send++) {
            $this->sessionId('otp-phone-limit-session-'.$send);
            $this->otp->createChallenge($send % 2 === 0 ? 'prime' : 'fofo', '+919876543210');
        }

        $this->sessionId('otp-phone-limit-session-eleven');

        try {
            $this->otp->createChallenge('prime', '+919876543210');
            $this->fail('The per-phone send limit must be enforced.');
        } catch (OtpException $exception) {
            $this->assertSame(OtpException::SEND_LIMIT, $exception->reason);
        }
    }

    public function test_sending_limits_reset_after_their_window_expires(): void
    {
        for ($send = 0; $send < 5; $send++) {
            $this->otp->createChallenge('prime', '+919876543210');
            $this->travel(31)->seconds();
        }

        $this->travel(3601)->seconds();
        $code = $this->otp->createChallenge('prime', '+919876543210')->code;

        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $code));
    }

    public function test_phone_purpose_and_session_mismatches_cannot_verify_or_consume(): void
    {
        $code = $this->otp->createChallenge('prime', '+919876543210')->code;

        $this->assertFalse($this->otp->verifyChallenge('fofo', '+919876543210', $code));
        $this->assertFalse($this->otp->verifyChallenge('prime', '+447123456789', $code));

        $this->sessionId('otp-test-session-two');
        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $code));

        $this->sessionId('otp-test-session-one');
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $code));
        $this->assertFalse($this->otp->consumeVerification('fofo', '+919876543210'));
        $this->assertFalse($this->otp->consumeVerification('prime', '+447123456789'));

        $this->sessionId('otp-test-session-two');
        $this->assertFalse($this->otp->consumeVerification('prime', '+919876543210'));
        $this->sessionId('otp-test-session-one');
        $this->assertTrue($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_fofo_is_supported_and_invalid_phones_or_purposes_are_rejected(): void
    {
        $code = $this->otp->createChallenge('fofo', '+447123456789')->code;
        $this->assertTrue($this->otp->verifyChallenge('fofo', '+447123456789', $code));

        $this->expectException(InvalidArgumentException::class);
        $this->otp->createChallenge('prime', 'not-a-phone');
    }

    public function test_prime_and_fofo_challenges_remain_independent_within_one_session(): void
    {
        $primeCode = $this->otp->createChallenge('prime', '+919876543210')->code;
        $fofoCode = $this->otp->createChallenge('fofo', '+919876543210')->code;

        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $primeCode));
        $this->assertTrue($this->otp->verifyChallenge('fofo', '+919876543210', $fofoCode));
        $this->assertTrue($this->otp->consumeVerification('prime', '+919876543210'));
        $this->assertTrue($this->otp->consumeVerification('fofo', '+919876543210'));
    }

    public function test_failed_delivery_cancels_only_its_challenge_and_allows_an_immediate_retry(): void
    {
        $sms = new FakeSmsDelivery;
        $sms->accept = false;
        $failed = $this->otp->createChallenge('prime', '+919876543210');

        $this->assertFalse($sms->send('+919876543210', "Code {$failed->code}"));
        $this->assertTrue($this->otp->cancelChallenge('prime', '+919876543210', $failed->id));
        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $failed->code));
        $this->assertFalse($this->otp->cancelChallenge('prime', '+919876543210', $failed->id));

        $sms->accept = true;
        $retry = $this->otp->createChallenge('prime', '+919876543210');
        $this->assertTrue($sms->send('+919876543210', "Code {$retry->code}"));
        $this->assertSame([['mobile' => '+919876543210', 'message' => "Code {$retry->code}"]], $sms->sent);
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $retry->code));
    }

    public function test_a_delayed_failure_cannot_cancel_a_newer_challenge_or_release_its_quota(): void
    {
        $old = $this->otp->createChallenge('prime', '+919876543210');
        $this->travel(31)->seconds();
        $new = $this->otp->createChallenge('prime', '+919876543210');

        $this->assertNotSame($old->id, $new->id);
        $this->assertFalse($this->otp->cancelChallenge('prime', '+919876543210', $old->id));
        $this->assertSame(2, Cache::store('array')->get($this->challengeKey('prime').':sends')['count']);
        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $old->code));
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $new->code));
        $this->assertFalse($this->otp->cancelChallenge('prime', '+919876543210', $new->id));
        $this->assertTrue($this->otp->consumeVerification('prime', '+919876543210'));
    }

    public function test_delivery_failures_clear_cooldown_but_preserve_the_hourly_send_limit(): void
    {
        $sms = new FakeSmsDelivery;
        $sms->accept = false;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $challenge = $this->otp->createChallenge('prime', '+919876543210');
            $this->assertFalse($sms->send('+919876543210', "Code {$challenge->code}"));
            $this->assertTrue($this->otp->cancelChallenge('prime', '+919876543210', $challenge->id));
        }

        $this->assertSame(5, Cache::store('array')->get($this->challengeKey('prime').':sends')['count']);

        try {
            $this->otp->createChallenge('prime', '+919876543210');
            $this->fail('Failed delivery attempts must still count toward the hourly limit.');
        } catch (OtpException $exception) {
            $this->assertSame(OtpException::SEND_LIMIT, $exception->reason);
        }
    }

    public function test_delivery_exceptions_can_be_cancelled_without_leaving_an_active_code(): void
    {
        $sms = new FakeSmsDelivery;
        $sms->throw = true;
        $challenge = $this->otp->createChallenge('prime', '+919876543210');

        try {
            $sms->send('+919876543210', "Code {$challenge->code}");
            $this->fail('The fake must simulate a provider exception.');
        } catch (\RuntimeException) {
            $this->assertTrue($this->otp->cancelChallenge('prime', '+919876543210', $challenge->id));
        }

        $this->assertFalse($this->otp->verifyChallenge('prime', '+919876543210', $challenge->code));
        $this->otp->createChallenge('prime', '+919876543210');
    }

    public function test_cancellation_is_bound_to_phone_purpose_and_session(): void
    {
        $challenge = $this->otp->createChallenge('prime', '+919876543210');

        $this->assertFalse($this->otp->cancelChallenge('fofo', '+919876543210', $challenge->id));
        $this->assertFalse($this->otp->cancelChallenge('prime', '+447123456789', $challenge->id));
        $this->assertFalse($this->otp->cancelChallenge('prime', '+919876543210', 'invalid-id'));

        $this->sessionId('another-session');
        $this->assertFalse($this->otp->cancelChallenge('prime', '+919876543210', $challenge->id));

        $this->sessionId('otp-test-session-one');
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $challenge->code));
    }

    public function test_delivery_coordinator_uses_the_fake_and_keeps_an_accepted_code_active(): void
    {
        $sms = new FakeSmsDelivery;

        $this->assertTrue($this->delivery($sms)->send('fofo', '+44 7123 456789', 'Asha'));
        $this->assertCount(1, $sms->sent);
        $this->assertSame('+447123456789', $sms->sent[0]['mobile']);
        $this->assertMatchesRegularExpression('/\AHello Asha, [0-9]{6} is your OTP for Signup at Detailing Devils India Pvt Ltd\.\z/', $sms->sent[0]['message']);
        $this->assertTrue($this->otp->verifyChallenge('fofo', '+447123456789', $this->sentCode($sms)));
    }

    public function test_delivery_coordinator_cancels_rejections_and_allows_retry(): void
    {
        $sms = new FakeSmsDelivery;
        $sms->accept = false;

        $this->assertFalse($this->delivery($sms)->send('prime', '+919876543210', 'Asha'));
        $this->assertSame('cancelled', Cache::store('array')->get($this->challengeKey('prime'))['state']);

        $sms->accept = true;
        $this->assertTrue($this->delivery($sms)->send('prime', '+919876543210', 'Asha'));
        $this->assertTrue($this->otp->verifyChallenge('prime', '+919876543210', $this->sentCode($sms)));
    }

    public function test_delivery_coordinator_cancels_provider_exceptions(): void
    {
        $sms = new FakeSmsDelivery;
        $sms->throw = true;

        try {
            $this->delivery($sms)->send('prime', '+919876543210', 'Asha');
            $this->fail('A provider exception must propagate.');
        } catch (\RuntimeException) {
            $this->assertSame('cancelled', Cache::store('array')->get($this->challengeKey('prime'))['state']);
        }

        $sms->throw = false;
        $this->assertTrue($this->delivery($sms)->send('prime', '+919876543210', 'Asha'));
    }

    private function delivery(FakeSmsDelivery $sms): OtpDeliveryService
    {
        return new OtpDeliveryService($this->otp, $sms, app(PhoneNumberNormalizer::class));
    }

    private function sentCode(FakeSmsDelivery $sms): string
    {
        preg_match('/\b[0-9]{6}\b/', $sms->sent[0]['message'], $matches);

        return $matches[0];
    }

    private function sessionId(string $id): void
    {
        $this->app['session']->driver()->setId(substr(hash('sha256', $id), 0, 40));
    }

    private function challengeKey(string $purpose): string
    {
        $sessionId = $this->app['session']->driver()->getId();

        return 'franchise:otp:session:'.hash_hmac('sha256', "{$purpose}|{$sessionId}", config('app.key'));
    }
}
