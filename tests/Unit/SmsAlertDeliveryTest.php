<?php

namespace Tests\Unit;

use App\Services\Franchise\SmsAlertDelivery;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsAlertDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sms_alert', [
            'enabled' => true,
            'storage_ready' => false,
            'api_key' => 'example-key',
            'sender' => 'EXAMPLE',
        ]);
        Http::preventStrayRequests();
    }

    public function test_it_posts_api_key_and_complete_message_in_the_form_body_not_the_url(): void
    {
        Http::fake(['https://www.smsalert.co.in/*' => Http::response([
            'status' => 'success',
            'description' => ['batch_dtl' => [['status' => 'AWAITED-DLR']]],
        ])]);

        $this->assertTrue(app(SmsAlertDelivery::class)->send('+919876543210', 'Hello Asha, 012345 is your OTP for Signup at Detailing Devils India Pvt Ltd.'));

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://www.smsalert.co.in/api/push.json'
                && $request['apikey'] === 'example-key'
                && $request['sender'] === 'EXAMPLE'
                && $request['mobileno'] === '919876543210'
                && $request['text'] === 'Hello Asha, 012345 is your OTP for Signup at Detailing Devils India Pvt Ltd.'
                && count($request->data()) === 4;
        });
    }

    public function test_disabled_or_missing_configuration_makes_no_http_request(): void
    {
        Http::fake();
        config()->set('services.sms_alert.enabled', false);
        $this->assertFalse(app(SmsAlertDelivery::class)->send('+919876543210', 'Message'));

        config()->set('services.sms_alert.enabled', true);
        config()->set('services.sms_alert.api_key', null);
        $this->assertFalse(app(SmsAlertDelivery::class)->send('+919876543210', 'Message'));
        Http::assertNothingSent();
    }

    public function test_provider_rejection_and_blocklisted_recipient_are_not_success(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'error', 'description' => 'rejected'], 200)
            ->push(['status' => 'success', 'description' => ['batch_dtl' => [['status' => 'BLACKLST']]]], 200);

        $adapter = app(SmsAlertDelivery::class);
        $this->assertFalse($adapter->send('+919876543210', 'Message'));
        $this->assertFalse($adapter->send('+919876543210', 'Message'));
    }

    public function test_missing_batch_details_does_not_count_as_an_accepted_sms(): void
    {
        Http::fake(['https://www.smsalert.co.in/*' => Http::response(['status' => 'success'], 200)]);

        $this->assertFalse(app(SmsAlertDelivery::class)->send('+919876543210', 'Message'));
    }
}
