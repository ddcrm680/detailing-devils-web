<?php

namespace Tests\Feature\Franchise;

use App\Contracts\Franchise\SmsDelivery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\Fakes\Franchise\FakeSmsDelivery;
use Tests\TestCase;

class PrimeLeadSubmissionTest extends TestCase
{
    private FakeSmsDelivery $sms;

    protected function setUp(): void
    {
        parent::setUp();

        // Never create even a test table unless this process uses isolated memory.
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Prime lead tests require an isolated in-memory SQLite database.');
        }

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('services.prime.submission_enabled', true);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('services.sms_alert', [
            'enabled' => true,
            'storage_ready' => true,
            'api_key' => 'example-key',
            'sender' => 'EXAMPLE',
        ]);
        config()->set('mail.prime_team_address', 'team@example.test');
        Cache::store('array')->flush();
        Http::preventStrayRequests();
        Log::spy();
        // Mail::fake() does not record view-based Mail::send() calls.
        Mail::spy();
        $this->withCredentials()->withCookie(config('session.cookie'), str_repeat('b', 40));

        $this->sms = new FakeSmsDelivery;
        $this->app->instance(SmsDelivery::class, $this->sms);

        Schema::create('tbl_common_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('record_type', 15);
            $table->string('profession')->nullable();
            $table->string('age', 50)->nullable();
            $table->string('experience')->nullable();
            $table->string('newbussiness')->nullable();
            $table->string('type_of_bussiness')->nullable();
            $table->string('existingfacility')->nullable();
            $table->string('elaboratelocation')->nullable();
            $table->string('spacearound')->nullable();
            $table->string('territory_city', 100)->nullable();
            $table->string('territory')->nullable();
            $table->string('wantstart')->nullable();
            $table->string('bussinessowner')->nullable();
            $table->string('contacted')->nullable();
            $table->string('bussiness_description', 255)->nullable();
            $table->string('current_address')->nullable();
            $table->date('created_date')->nullable();
            $table->timestamp('add_franchise_date')->nullable();
            $table->boolean('consent')->nullable();
            $table->string('closed_leads', 2);
        });
    }

    public function test_disabled_submission_cannot_save_or_schedule_emails_even_with_verified_otp(): void
    {
        $this->verifyMobile();
        config()->set('services.prime.submission_enabled', false);

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('created');

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::shouldNotHaveReceived('send');
    }

    public function test_verified_application_is_saved_once_with_legacy_mapping(): void
    {
        $this->verifyMobile();

        $application = $this->validApplication();
        $application['middle_name'] = 'Kumar';
        $application['business_experience'] = 'yes';
        $application['business_name'] = 'Detailing Studio';
        $application['industry'] = 'Automotive';
        $application['property_status'] = 'yes';
        $application['property_location'] = 'Sector 18, Noida';
        $application['carpet_area'] = 1800;
        $application['road_facing'] = 'yes';
        $application['partnership_reason'] = 'I want to build a studio.';

        $this->postJson(route('prime.submit'), $application)
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        $lead = DB::table('tbl_common_leads')->first();
        $this->assertSame('Asha Kumar Sharma', $lead->name);
        $this->assertSame('919876543210', $lead->phone);
        $this->assertSame('919876543210', $lead->whatsapp_number);
        $this->assertSame('asha@example.test', $lead->email);
        $this->assertSame('prime-franchise', $lead->record_type);
        $this->assertSame('Detailing Studio', $lead->newbussiness);
        $this->assertSame('Sector 18, Noida | Road Facing: yes', $lead->elaboratelocation);
        $this->assertSame('Phone Call, WhatsApp', $lead->contacted);
        $this->assertSame('Noida, Uttar Pradesh, 201301', $lead->current_address);
        $this->assertSame('I want to build a studio.', $lead->bussiness_description);
        $this->assertSame('0', $lead->closed_leads);
        $this->assertMailAttempt('emails.franchise.prime-team', 'team@example.test', 'New Detailing Devils Prime Franchise Enquiry');
        $this->assertMailAttempt('emails.franchise.prime-applicant', 'asha@example.test', 'Thank You for Your Detailing Devils Prime Franchise Application');

        $this->postJson(route('prime.submit'), $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public function test_duplicate_lead_returns_created_false_after_consuming_proof(): void
    {
        DB::table('tbl_common_leads')->insert([
            'name' => 'Existing', 'phone' => '919876543210',
            'record_type' => 'prime-franchise', 'closed_leads' => '0',
        ]);
        $this->verifyMobile();

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', false);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Mail::shouldNotHaveReceived('send');

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
    }

    public function test_comma_separated_team_recipients_receive_one_team_email(): void
    {
        config()->set('mail.prime_team_address', ' team@example.test, , second@example.test ');
        config()->set('site.brand', 'Example Studios');
        $this->verifyMobile();
        $application = $this->validApplication();
        $application['middle_name'] = 'Kumar';

        $this->postJson(route('prime.submit'), $application)
            ->assertOk()->assertJsonPath('created', true);

        $this->assertMailAttempt('emails.franchise.prime-team', ['team@example.test', 'second@example.test'], 'New Detailing Devils Prime Franchise Enquiry');
        $this->assertMailAttempt('emails.franchise.prime-applicant', 'asha@example.test', 'Thank You for Your Detailing Devils Prime Franchise Application');
    }

    public function test_invalid_team_recipients_are_ignored(): void
    {
        config()->set('mail.prime_team_address', 'invalid, team@example.test, bad@, ');
        $this->verifyMobile();
        $application = $this->validApplication();
        $application['middle_name'] = 'Kumar';

        $this->postJson(route('prime.submit'), $application)
            ->assertOk()->assertJsonPath('created', true);

        $this->assertMailAttempt('emails.franchise.prime-team', 'team@example.test', 'New Detailing Devils Prime Franchise Enquiry');
        $this->assertMailAttempt('emails.franchise.prime-applicant', 'asha@example.test', 'Thank You for Your Detailing Devils Prime Franchise Application');
    }

    public function test_all_invalid_team_recipients_skip_only_the_team_email(): void
    {
        config()->set('mail.prime_team_address', 'invalid, bad@, ');
        $this->verifyMobile();
        $application = $this->validApplication();
        $application['middle_name'] = 'Kumar';

        $this->postJson(route('prime.submit'), $application)
            ->assertOk()->assertJsonPath('created', true);

        Mail::shouldHaveReceived('send')->once();
        $this->assertMailAttempt('emails.franchise.prime-applicant', 'asha@example.test', 'Thank You for Your Detailing Devils Prime Franchise Application');
    }

    public function test_same_phone_in_another_record_type_does_not_block_prime(): void
    {
        DB::table('tbl_common_leads')->insert([
            'name' => 'Other lead', 'phone' => '919876543210',
            'record_type' => 'other', 'closed_leads' => '0',
        ]);
        $this->verifyMobile();

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);
        $this->assertSame(2, DB::table('tbl_common_leads')->count());
        $this->assertSame(1, DB::table('tbl_common_leads')->where('phone', '919876543210')->where('record_type', 'other')->count());
        $this->assertSame(1, DB::table('tbl_common_leads')->where('phone', '919876543210')->where('record_type', 'prime-franchise')->count());
    }

    public function test_fofo_historical_phone_matching_does_not_change_prime_duplicate_behavior(): void
    {
        DB::table('tbl_common_leads')->insert([
            'name' => 'Existing', 'phone' => '9876543210',
            'record_type' => 'prime-franchise', 'closed_leads' => '0',
        ]);
        $this->verifyMobile();

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);

        $this->assertSame(2, DB::table('tbl_common_leads')->count());
        $this->assertSame(1, DB::table('tbl_common_leads')
            ->where('phone', '919876543210')->where('record_type', 'prime-franchise')->count());
    }

    public function test_submission_without_verified_otp_cannot_save(): void
    {
        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_existing_prime_lead_does_not_bypass_otp(): void
    {
        DB::table('tbl_common_leads')->insert([
            'name' => 'Existing', 'phone' => '919876543210',
            'record_type' => 'prime-franchise', 'closed_leads' => '0',
        ]);

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public function test_proof_for_a_different_mobile_cannot_save(): void
    {
        $this->verifyMobile();
        $application = $this->validApplication();
        $application['mobile_number'] = '7123 456789';
        $application['mobile_e164'] = '+447123456789';
        $application['whatsapp_e164'] = '+447123456789';

        $this->postJson(route('prime.submit'), $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_insert_failure_returns_safe_error_and_does_not_reuse_proof(): void
    {
        $this->verifyMobile();
        Schema::table('tbl_common_leads', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertStatus(503)->assertJsonPath('success', false)
            ->assertDontSee('SQLSTATE');
        Mail::shouldNotHaveReceived('send');

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertUnprocessable()->assertJsonValidationErrors(['mobile_number']);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_team_email_failure_does_not_prevent_applicant_email_attempt(): void
    {
        $this->assertIndependentEmailFailure('emails.franchise.prime-team');
    }

    public function test_applicant_email_failure_does_not_change_saved_result(): void
    {
        $this->assertIndependentEmailFailure('emails.franchise.prime-applicant');
    }

    public function test_log_mailer_does_not_log_applicant_email_content(): void
    {
        config()->set('mail.default', 'log');
        $this->verifyMobile();

        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        Mail::shouldNotHaveReceived('send');
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public function test_applicant_email_uses_public_https_assets(): void
    {
        URL::forceRootUrl('https://www.detailingdevils.com');
        URL::forceScheme('https');

        $html = view('emails.franchise.prime-applicant', ['name' => 'Test'])->render();

        $this->assertStringContainsString('src="https://www.detailingdevils.com/img/dd-logo-top.png"', $html);
        $this->assertStringContainsString('href="https://www.detailingdevils.com/pdf/Prime-Franchise-Proposal.pdf"', $html);
        $this->assertFileExists(public_path('img/dd-logo-top.png'));
        $this->assertFileExists(public_path('pdf/Prime-Franchise-Proposal.pdf'));
    }

    private function assertIndependentEmailFailure(string $failedView): void
    {
        $attempts = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view) use (&$attempts, $failedView): void {
            $attempts[] = $view;

            if ($view === $failedView) {
                throw new RuntimeException('Simulated mail failure.');
            }
        });

        $this->verifyMobile();
        $this->postJson(route('prime.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame([
            'emails.franchise.prime-team',
            'emails.franchise.prime-applicant',
        ], $attempts);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    private function assertMailAttempt(string $view, string|array $recipient, string $subject): void
    {
        Mail::shouldHaveReceived('send')->withArgs(function (string $sentView, array $data, callable $callback) use ($view, $recipient, $subject): bool {
            if ($sentView !== $view) {
                return false;
            }

            $this->assertSame(0, DB::transactionLevel());
            $message = new Message(new Email);
            $callback($message);
            $email = $message->getSymfonyMessage();

            $this->assertSame($subject, $email->getSubject());
            $this->assertSame((array) $recipient, array_map(
                static fn ($address): string => $address->getAddress(), $email->getTo()
            ));
            $html = view($view, $data)->render();
            $this->assertStringContainsString('Asha Kumar Sharma', $html);
            $this->assertStringContainsString(config('site.brand'), $html);

            if ($view === 'emails.franchise.prime-applicant') {
                $this->assertStringContainsString(asset('img/dd-logo-top.png'), $html);
                $this->assertStringContainsString(asset('pdf/Prime-Franchise-Proposal.pdf'), $html);
                $this->assertStringContainsString('What Happens Next?', $html);
            } else {
                $this->assertStringContainsString('Property Details', $html);
                $this->assertStringContainsString('Preferred Contact', $html);
            }

            return true;
        })->once();
    }

    private function verifyMobile(): void
    {
        $this->postJson(route('otp.send'), [
            'purpose' => 'prime', 'mobile' => '+919876543210', 'name' => 'Asha',
        ])->assertOk();

        preg_match('/\b[0-9]{6}\b/', $this->sms->sent[0]['message'], $matches);
        $this->postJson(route('otp.verify'), [
            'purpose' => 'prime', 'otp' => $matches[0],
        ])->assertOk();
    }

    private function validApplication(): array
    {
        return [
            'first_name' => 'Asha', 'last_name' => 'Sharma',
            'mobile_number' => '9876543210', 'mobile_e164' => '+919876543210',
            'same_as_mobile' => '1', 'whatsapp_number' => '',
            'whatsapp_e164' => '+919876543210', 'email' => 'asha@example.test',
            'age_group' => '25+', 'occupation' => 'Entrepreneur',
            'city' => 'Noida', 'state' => 'Uttar Pradesh', 'pin_code' => '201301',
            'business_experience' => 'no', 'property_status' => 'looking_to_rent',
            'preferred_city' => 'Noida', 'start_timeline' => 'Within 1-3 months',
            'decision_maker' => 'yes', 'contact_methods' => ['Phone Call', 'WhatsApp'],
            'consent' => '1', 'website' => '',
        ];
    }
}
