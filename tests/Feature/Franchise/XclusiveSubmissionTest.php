<?php

namespace Tests\Feature\Franchise;

use App\Http\Requests\Franchise\StoreFofoRequest;
use App\Http\Requests\Franchise\StoreXclusiveRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class XclusiveSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('X-Clusive tests require isolated in-memory SQLite.');
        }

        config()->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('services.xclusive.submission_enabled', false);
        config()->set('services.xclusive.call_enabled', false);
        config()->set('services.fofo.submission_enabled', false);
        config()->set('services.fofo.call_enabled', false);
        config()->set('services.franchise_call.url', null);
        config()->set('services.prime.submission_enabled', false);
        config()->set('services.sms_alert.enabled', false);
        Mail::fake();
        Http::preventStrayRequests();
        Storage::fake('fofo_images');

        Schema::create('tbl_country_phone', function (Blueprint $table): void {
            $table->id();
            $table->string('countryname');
        });
        Schema::create('tbl_states', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id');
        });
        Schema::create('tbl_cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('state_id');
        });
        DB::table('tbl_country_phone')->insert(['id' => 1, 'countryname' => 'India']);
        DB::table('tbl_states')->insert(['id' => 10, 'name' => 'Delhi', 'country_id' => 1]);
        DB::table('tbl_cities')->insert(['id' => 100, 'name' => 'New Delhi', 'state_id' => 10]);

        Schema::create('tbl_common_leads', function (Blueprint $table): void {
            $table->id();
            foreach ([
                'name', 'email', 'country', 'state', 'city', 'phone', 'website',
                'profession', 'contacted', 'hearabout', 'newbussiness', 'wantstart',
                'bussinessowner', 'type_of_bussiness', 'product_service',
                'bussiness_description', 'experienceautomotive', 'experience',
                'existingautomotive', 'existingfacility', 'spacearound',
                'elaboratelocation', 'whichcityfranchise', 'civillitigation',
                'spendfranchise', 'image_franchise', 'record_type', 'closed_leads',
            ] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('dob')->nullable();
            $table->date('created_date')->nullable();
        });
    }

    public function test_page_owns_its_view_and_entry_with_shared_controls_and_protected_submission_route(): void
    {
        config()->set('database.default', 'unconfigured');
        $this->assertSame(url('/x-clusive-franchise-form'), route('xclusive.show'));
        $this->assertSame(url('/franchise/xclusive'), route('xclusive.submit'));
        $route = Route::getRoutes()->getByName('xclusive.submit');
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('throttle:5,1', $route->gatherMiddleware());

        $response = $this->get(route('xclusive.show'))->assertOk()->assertViewIs('forms.xclusive')
            ->assertSee('X-Clusive Franchise Application | Detailing Devils')
            ->assertSee('X-Clusive Franchise')
            ->assertSee('data-submit-url="'.route('xclusive.submit').'"', false)
            ->assertSee('data-email-validation-url="'.route('email.validate').'"', false)
            ->assertSee('assets/css/fofo.css')->assertSee('assets/js/common/franchise-form.js')
            ->assertSee('assets/js/modules/xclusive.js')->assertDontSee('assets/js/modules/fofo.js')
            ->assertSeeInOrder(['assets/js/common/franchise-form.js', 'assets/js/modules/xclusive.js'])
            ->assertSee('intl-tel-input@29.1.2')->assertSee('name="_token"', false)
            ->assertSee('id="fofo-submit" disabled', false);

        $fofoHtml = $this->get(route('fofo.show'))->assertOk()->getContent();
        preg_match_all('/\bname="([^"]+)"/', $fofoHtml, $fofoNames);
        preg_match_all('/\bname="([^"]+)"/', $response->getContent(), $xclusiveNames);
        $this->assertSame($fofoNames[1], $xclusiveNames[1]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('models')]
    public function test_questionnaire_choices_are_independent_in_rendering_and_validation(string $model): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.submission_enabled', true);
        $other = $model === 'fofo' ? 'xclusive' : 'fofo';
        config()->set("form_options.{$model}.hearabout", ['Isolated choice']);

        $this->get(route("{$model}.show"))->assertOk()
            ->assertSee('<option value="Isolated choice">', false)
            ->assertDontSee('<option value="Google-Search">', false);
        $this->get(route("{$other}.show"))->assertOk()
            ->assertSee('<option value="Google-Search">', false)
            ->assertDontSee('<option value="Isolated choice">', false);

        $this->postJson(route("{$model}.submit"), $this->application())
            ->assertUnprocessable()->assertJsonValidationErrors('hearabout');
        $this->proof();
        $this->postJson(route("{$other}.submit"), $this->application())
            ->assertOk()->assertJsonPath('created', true);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public static function models(): array
    {
        return [['fofo'], ['xclusive']];
    }

    public function test_separate_model_mappings_preserve_identical_existing_crm_rows_and_uploads(): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.submission_enabled', true);
        $rows = [];
        foreach (['fofo' => 'FOFO', 'xclusive' => 'xclusive'] as $model => $recordType) {
            $application = array_replace($this->application(), [
                'bussinessowner' => 'YES', 'type_of_bussiness' => 'Retail',
                'product_service' => 'QA services', 'bussiness_description' => 'QA business',
                'experienceautomotive' => 'YES', 'experience' => 'QA experience',
                'existingautomotive' => 'YES', 'existingfacility' => 'QA facility',
                'spacearound' => 'YES', 'elaboratelocation' => 'QA property',
                'locationimage' => UploadedFile::fake()->image('property.png', 20, 20),
            ]);
            $this->proof();
            $this->post(route("{$model}.submit"), $application, ['Accept' => 'application/json'])
                ->assertOk()->assertJsonPath('created', true);
            $row = (array) DB::table('tbl_common_leads')->where('record_type', $recordType)->first();
            $this->assertSame($recordType, $row['record_type']);
            $this->assertSame('919876543210', $row['phone']);
            $this->assertSame('Asha Sharma', $row['name']);
            $this->assertSame('New Delhi', $row['whichcityfranchise']);
            $this->assertSame('QA property', $row['elaboratelocation']);
            Storage::disk('fofo_images')->assertExists($row['image_franchise']);
            unset($row['id'], $row['record_type'], $row['image_franchise']);
            $rows[$model] = $row;
        }
        $this->assertSame($rows['fofo'], $rows['xclusive']);
        $this->assertCount(2, Storage::disk('fofo_images')->allFiles());
    }

    public function test_configuration_defaults_to_disabled_and_rejects_before_database_validation(): void
    {
        $config = require base_path('config/services.php');
        $this->assertFalse($config['xclusive']['submission_enabled']);
        config()->set('database.default', 'unconfigured');
        $this->postJson(route('xclusive.submit'), [])->assertStatus(503)->assertJsonPath('success', false);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_enablement_is_independent_of_fofo_and_prime(): void
    {
        config()->set('services.fofo.submission_enabled', true);
        config()->set('services.prime.submission_enabled', true);
        $this->get(route('xclusive.show'))->assertSee('id="fofo-submit" disabled', false);
        $this->postJson(route('xclusive.submit'), [])->assertStatus(503);

        $this->enableSubmission();
        config()->set('services.fofo.submission_enabled', false);
        config()->set('services.prime.submission_enabled', false);
        $this->get(route('xclusive.show'))->assertDontSee('id="fofo-submit" disabled', false);
        $this->get(route('fofo.show'))->assertSee('id="fofo-submit" disabled', false);
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
    }

    public function test_new_application_uses_shared_mapping_and_server_controlled_identity(): void
    {
        $this->enableSubmission();
        $this->proof();
        $application = $this->application() + ['record_type' => 'FOFO', 'model' => 'prime', 'franchise_type' => 'prime-franchise'];
        $this->postJson(route('xclusive.submit'), $application)->assertOk()
            ->assertJsonPath('created', true)->assertJsonPath('success', true);
        $lead = DB::table('tbl_common_leads')->sole();
        $this->assertSame('xclusive', $lead->record_type);
        $this->assertSame('919876543210', $lead->phone);
        $this->assertSame('Asha Sharma', $lead->name);
        $this->assertSame(['India', 'Delhi', 'New Delhi'], [$lead->country, $lead->state, $lead->city]);
        $this->assertSame('New Delhi', $lead->whichcityfranchise);
        $this->assertNull(session('email_validation.proof'));
        Http::assertNothingSent();
    }

    #[DataProvider('requiredFields')]
    public function test_independent_validation_rejects_missing_required_fields(string $field): void
    {
        $this->assertFalse(is_subclass_of(StoreXclusiveRequest::class, StoreFofoRequest::class));
        $this->enableSubmission();
        $application = $this->application();
        unset($application[$field]);
        $this->postJson(route('xclusive.submit'), $application)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public static function requiredFields(): array
    {
        return array_map(fn (string $field): array => [$field], [
            'name', 'lname', 'email', 'phone', 'dob', 'country', 'state', 'city',
            'hearabout', 'contacted', 'franchiseapply', 'wantstart', 'newbussiness',
            'bussinessowner', 'experienceautomotive', 'existingautomotive',
            'spacearound', 'spendfranchise', 'civillitigation',
        ]);
    }

    public function test_conditional_validation_and_location_relationships_are_preserved(): void
    {
        $this->enableSubmission();
        $application = array_replace($this->application(), [
            'bussinessowner' => 'YES', 'experienceautomotive' => 'YES',
            'existingautomotive' => 'YES', 'spacearound' => 'YES', 'state' => 999, 'city' => 999,
        ]);
        $this->postJson(route('xclusive.submit'), $application)->assertUnprocessable()->assertJsonValidationErrors([
            'type_of_bussiness', 'product_service', 'bussiness_description', 'experience',
            'existingfacility', 'elaboratelocation', 'locationimage', 'state', 'city',
        ]);
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
    }

    public function test_email_proof_is_required_matches_email_and_expires(): void
    {
        $this->enableSubmission();
        foreach ([null, 'other@example.test', 'expired'] as $proof) {
            if ($proof !== null) {
                $this->proof($proof === 'expired' ? 'asha@example.test' : $proof, $proof === 'expired' ? now()->subSecond()->timestamp : null);
            }
            $this->postJson(route('xclusive.submit'), $this->application() + ['email_valid' => true])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_real_email_endpoint_with_mocked_provider_creates_the_required_proof(): void
    {
        $this->enableSubmission();
        config()->set('services.zerobounce.api_key', 'test-only-key');
        Http::fake(['https://api.zerobounce.net/v2/validate' => Http::response(['status' => 'valid'])]);
        $this->postJson(route('email.validate'), ['email' => 'asha@example.test'])->assertOk()->assertJsonPath('result', 'valid');
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        Http::assertSentCount(1);
    }

    #[DataProvider('historicalPhones')]
    public function test_duplicate_is_scoped_to_xclusive_and_requires_fresh_proof(string $phone): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        DB::table('tbl_common_leads')->insert(['phone' => $phone, 'record_type' => 'xclusive']);
        $this->postJson(route('xclusive.submit'), $this->application())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', false);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public static function historicalPhones(): array
    {
        return [['9876543210'], ['09876543210'], ['919876543210'], ['+919876543210']];
    }

    public function test_fofo_and_prime_leads_do_not_block_xclusive_and_existing_fofo_stays_independent(): void
    {
        $this->enableSubmission();
        DB::table('tbl_common_leads')->insert(['phone' => '919876543210', 'record_type' => 'prime-franchise']);
        config()->set('services.fofo.submission_enabled', true);
        $this->proof();
        $this->postJson(route('fofo.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        $this->assertSame(['FOFO', 'prime-franchise', 'xclusive'], DB::table('tbl_common_leads')->orderBy('record_type')->pluck('record_type')->all());
        $this->proof();
        $this->postJson(route('fofo.submit'), $this->application())->assertOk()->assertJsonPath('created', false);
    }

    public function test_property_image_and_all_conditional_fields_use_existing_upload_handling(): void
    {
        $this->enableSubmission();
        $this->proof();
        $application = array_replace($this->application(), [
            'bussinessowner' => 'YES', 'type_of_bussiness' => 'Studio', 'product_service' => 'Detailing',
            'bussiness_description' => 'Two employees', 'experienceautomotive' => 'YES', 'experience' => 'Five years',
            'existingautomotive' => 'YES', 'existingfacility' => 'Current facility', 'spacearound' => 'YES',
            'elaboratelocation' => 'Main road', 'locationimage' => UploadedFile::fake()->image('site.jpg'),
        ]);
        $this->post(route('xclusive.submit'), $application, ['Accept' => 'application/json'])->assertOk()->assertJsonPath('created', true);
        $lead = DB::table('tbl_common_leads')->sole();
        foreach (['type_of_bussiness', 'product_service', 'bussiness_description', 'experience', 'existingfacility', 'elaboratelocation'] as $field) {
            $this->assertSame($application[$field], $lead->$field);
        }
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{32}\.jpg\z/', $lead->image_franchise);
        Storage::disk('fofo_images')->assertExists($lead->image_franchise);
    }

    public function test_failed_insert_removes_image_and_returns_safe_error(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        $this->proof();
        DB::unprepared("CREATE TRIGGER reject_xclusive BEFORE INSERT ON tbl_common_leads BEGIN SELECT RAISE(ABORT, 'test insert failure'); END");
        $application = array_replace($this->application(), [
            'spacearound' => 'YES', 'elaboratelocation' => 'Main road', 'locationimage' => UploadedFile::fake()->image('site.jpg'),
        ]);
        $this->post(route('xclusive.submit'), $application, ['Accept' => 'application/json'])->assertStatus(503)
            ->assertDontSee('test insert failure');
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_separate_emails_use_xclusive_recipients_subjects_and_escaped_templates(): void
    {
        $this->enableSubmission();
        Mail::spy();
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        foreach ([
            'emails.franchise.xclusive-team' => ['team@example.test', 'New Detailing Devils X-Clusive Franchise Application'],
            'emails.franchise.xclusive-applicant' => ['asha@example.test', 'Your Detailing Devils X-Clusive Franchise Application'],
        ] as $view => [$recipient, $subject]) {
            Mail::shouldHaveReceived('send')->withArgs(function (string $sentView, array $data, callable $callback) use ($view, $recipient, $subject): bool {
                if ($sentView !== $view) {
                    return false;
                }
                $this->assertSame(0, DB::transactionLevel());
                $message = new Message(new Email);
                $callback($message);
                $this->assertSame($recipient, $message->getSymfonyMessage()->getTo()[0]->getAddress());
                $this->assertSame($subject, $message->getSymfonyMessage()->getSubject());
                $html = view($view, array_replace($data, ['name' => '<script>example</script>']))->render();
                $this->assertStringContainsString('X-Clusive', $html);
                $this->assertStringContainsString('&lt;script&gt;', $html);
                $this->assertStringNotContainsString('<script>', $html);
                $this->assertStringNotContainsString('FOFO', $html);

                return true;
            });
        }
        Mail::shouldHaveReceived('send')->twice();
        Http::assertNothingSent();
    }

    public function test_team_email_failure_still_attempts_applicant_and_does_not_undo_save(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        $attempts = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view) use (&$attempts): void {
            $attempts[] = $view;
            if ($view === 'emails.franchise.xclusive-team') {
                throw new RuntimeException('Simulated mail failure.');
            }
        });
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        $this->assertSame(['emails.franchise.xclusive-team', 'emails.franchise.xclusive-applicant'], $attempts);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Http::assertSentCount(1);
    }

    public function test_new_xclusive_lead_calls_shared_provider_only_after_the_transaction_commits(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake(function ($request) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('xclusive', DB::table('tbl_common_leads')->sole()->record_type);
            $this->assertSame('GET', $request->method());
            $this->assertSame('https://calls.example.test/callback?cust_mob=09876543210', $request->url());

            return Http::response('ok');
        });
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);
        Http::assertSentCount(1);
        $this->assertFalse(config('services.fofo.call_enabled'));
    }

    public function test_xclusive_call_defaults_to_disabled_and_uses_no_separate_endpoint_setting(): void
    {
        $config = require base_path('config/services.php');
        $this->assertFalse($config['xclusive']['call_enabled']);
        $this->assertArrayNotHasKey('call_url', $config['xclusive']);
        $this->enableSubmission();
        config()->set('services.franchise_call.url', 'https://calls.example.test/callback');
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        Http::assertNothingSent();
    }

    #[DataProvider('emailSkipReasons')]
    public function test_log_mailer_and_missing_recipient_do_not_prevent_enabled_calling(string $reason): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        if ($reason === 'log') {
            config()->set('mail.default', 'log');
        } else {
            config()->set('mail.xclusive_team_address', null);
        }
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        Http::assertSentCount(1);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public static function emailSkipReasons(): array
    {
        return [['log'], ['missing-recipient']];
    }

    public function test_applicant_email_failure_does_not_prevent_enabled_calling(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        $attempts = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view) use (&$attempts): void {
            $attempts[] = $view;
            if ($view === 'emails.franchise.xclusive-applicant') {
                throw new RuntimeException('Simulated mail failure.');
            }
        });
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        $this->assertSame(['emails.franchise.xclusive-team', 'emails.franchise.xclusive-applicant'], $attempts);
        Http::assertSentCount(1);
    }

    #[DataProvider('callFailures')]
    public function test_callback_failures_do_not_affect_saved_lead_or_xclusive_emails(string $failure): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Mail::spy();
        Http::fake(function () use ($failure) {
            if ($failure === 'exception') {
                throw new ConnectionException('Simulated connection failure');
            }

            return Http::response('unavailable', (int) $failure);
        });
        Log::spy();
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Mail::shouldHaveReceived('send')->twice();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'X-Clusive calling callback failed.')->once();
    }

    public static function callFailures(): array
    {
        return [['400'], ['503'], ['exception']];
    }

    public function test_non_indian_lead_is_saved_without_a_call_and_invalid_mobile_is_rejected(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        $application = array_replace($this->application(), ['phone' => '+12025550123']);
        $this->proof();
        $this->postJson(route('xclusive.submit'), $application)->assertOk()->assertJsonPath('created', true);
        $this->assertSame('12025550123', DB::table('tbl_common_leads')->sole()->phone);
        $application['phone'] = '+910000000000';
        $this->postJson(route('xclusive.submit'), $application)->assertUnprocessable()->assertJsonValidationErrors('phone');
        Http::assertNothingSent();
    }

    public function test_disabled_submission_never_schedules_a_call_even_when_calling_is_enabled(): void
    {
        $this->enableCalling();
        Http::fake();
        $this->postJson(route('xclusive.submit'), $this->application())->assertStatus(503);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Http::assertNothingSent();
    }

    public function test_log_mailer_skips_emails_and_fofo_call_configuration_cannot_trigger_xclusive_calls(): void
    {
        $this->enableSubmission();
        config()->set('mail.default', 'log');
        config()->set('services.fofo.call_enabled', true);
        config()->set('services.franchise_call.url', 'https://calls.example.test/never');
        Log::spy();
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application())->assertOk()->assertJsonPath('created', true);
        Mail::assertNothingSent();
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->with('X-Clusive franchise emails skipped: a delivery mailer is not configured.')->once();
    }

    public function test_studio_tour_links_to_xclusive_without_enabling_submission(): void
    {
        $this->get(route('studios.tour'))->assertOk()
            ->assertDontSee('Coming Soon')
            ->assertSee('href="'.route('xclusive.show', [], false).'"', false);
        $this->get(route('xclusive.show'))->assertOk()->assertSee('id="fofo-submit" disabled', false);
        $this->assertFalse(config('services.xclusive.submission_enabled'));
        $this->assertFalse(config('services.xclusive.call_enabled'));
    }

    public function test_applicant_email_hides_the_entire_proposal_paragraph_without_a_pdf(): void
    {
        $this->withProposalDirectory(function (string $file): void {
            $this->assertFileDoesNotExist($file);
            $html = view('emails.franchise.xclusive-applicant', ['name' => 'Preview Applicant'])->render();
            $this->assertStringNotContainsString('For your detailed understanding', $html);
            $this->assertStringNotContainsString('X-Clusive Franchise Proposal', $html);
            $this->assertStringNotContainsString('pdf/xclusive_proposal.pdf', $html);
            $this->assertStringContainsString('X-Clusive franchise model', $html);
            $this->assertStringContainsString('Who We', $html);
            $this->assertStringContainsString('What We', $html);
            $this->assertStringContainsString('Technical &amp; Backend Support', $html);
        });
    }

    public function test_applicant_email_links_only_to_an_existing_xclusive_proposal_and_fixture_is_cleaned_up(): void
    {
        $this->withProposalDirectory(function (string $file): void {
            file_put_contents($file, $this->proposalPdf());
            $this->assertStringStartsWith('%PDF-1.4', file_get_contents($file));
            $html = view('emails.franchise.xclusive-applicant', ['name' => 'Preview Applicant'])->render();
            $this->assertStringContainsString('For your detailed understanding', $html);
            $this->assertStringContainsString('X-Clusive Franchise Proposal', $html);
            $this->assertStringContainsString('href="'.asset('pdf/xclusive_proposal.pdf').'"', $html);
            $this->assertStringNotContainsString('Franchise-Proposal-2024.pdf', $html);
        });
    }

    public function test_applicant_email_keeps_dynamic_brand_contacts_and_excludes_unapproved_commercial_claims(): void
    {
        config()->set('site.brand', 'Example Studios');
        config()->set('site.phone', '+91 1111 222 333');
        config()->set('site.phone_link', '+911111222333');
        config()->set('site.whatsapp_phone', '911111222334');
        config()->set('site.email', 'hello@example.test');
        config()->set('site.address', 'Demonstration office address');
        config()->set('site.numbers.0.value', '200+');
        config()->set('site.social.Facebook', 'https://example.test/facebook');
        config()->set('site.social.Instagram', 'https://example.test/instagram');
        config()->set('site.social.YouTube', 'https://example.test/youtube');

        $html = view('emails.franchise.xclusive-applicant', ['name' => '<script>Preview</script>'])->render();
        foreach (['Example Studios', '+91 1111 222 333', 'hello@example.test', 'Demonstration office address', '200+'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        $this->assertStringContainsString('href="tel:+911111222333"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', $html);
        $this->assertStringContainsString('https://api.whatsapp.com/send?phone=911111222334', $html);
        foreach (['facebook', 'instagram', 'youtube'] as $network) {
            $this->assertStringContainsString('href="https://example.test/'.$network.'"', $html);
        }
        foreach (['FOFO', 'Franchise-Proposal-2024.pdf', '35 lakhs', '35 lacs', '1500', '1800', '30,000', '700 per sq', 'Investment Includes', 'quick return on investment'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_team_email_preserves_all_conditional_answers_and_property_image_information(): void
    {
        $application = array_replace($this->application(), [
            'bussinessowner' => 'YES', 'type_of_bussiness' => 'Studio', 'product_service' => 'Detailing',
            'bussiness_description' => 'Two employees', 'experienceautomotive' => 'YES', 'experience' => 'Five years',
            'existingautomotive' => 'YES', 'existingfacility' => 'Two service bays', 'spacearound' => 'YES',
            'elaboratelocation' => 'Main road location', 'website' => 'https://example.test',
        ]);
        $data = [
            'application' => $application, 'name' => 'Asha Sharma', 'mobile' => '+919876543210',
            'submittedOn' => '2026-10-30', 'imageUrl' => 'https://example.test/uploads/images/dummy.jpg',
            'savedLead' => (object) ['country' => 'India', 'state' => 'Delhi', 'city' => 'New Delhi'],
        ];
        $html = view('emails.franchise.xclusive-team', $data)->render();
        foreach (['X-Clusive', 'Franchise model', 'Two employees', 'Five years', 'Two service bays', 'Main road location', 'View location image', 'https://example.test/uploads/images/dummy.jpg'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        foreach (['bussinessowner', 'experienceautomotive', 'existingautomotive', 'spacearound'] as $key) {
            $data['application'][$key] = 'NO';
        }
        foreach (['type_of_bussiness', 'product_service', 'bussiness_description', 'experience', 'existingfacility', 'elaboratelocation'] as $key) {
            unset($data['application'][$key]);
        }
        $data['imageUrl'] = null;
        $html = view('emails.franchise.xclusive-team', $data)->render();
        $this->assertStringContainsString('Not provided', $html);
        $this->assertStringNotContainsString('View location image', $html);
        $this->assertStringNotContainsString('Two service bays', $html);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_complete_mock_verification_and_public_submission_include_images_emails_and_calling(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        config()->set('services.zerobounce.api_key', 'isolated-provider-key');
        $emails = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view, array $data, callable $callback) use (&$emails): void {
            $this->assertSame(0, DB::transactionLevel());
            $message = new Message(new Email);
            $callback($message);
            $html = view($view, $data)->render();
            $this->assertStringContainsString('X-Clusive', $html);
            $this->assertStringContainsString('Asha Sharma', $html);
            $this->assertStringNotContainsString('FOFO', $html);
            $this->assertStringNotContainsString('Franchise-Proposal-2024.pdf', $html);
            $recipients = array_map(fn ($address): string => $address->getAddress(), $message->getSymfonyMessage()->getTo());
            if ($view === 'emails.franchise.xclusive-team') {
                $this->assertSame(['team@example.test'], $recipients);
                $this->assertSame('New Detailing Devils X-Clusive Franchise Application', $message->getSymfonyMessage()->getSubject());
                $this->assertSame(['India', 'Delhi', 'New Delhi'], [$data['savedLead']->country, $data['savedLead']->state, $data['savedLead']->city]);
                foreach (['Two employees', 'Five years', 'Current facility', 'Main road'] as $answer) {
                    $this->assertStringContainsString($answer, $html);
                }
                $this->assertStringContainsString($data['imageUrl'], $html);
            } else {
                $this->assertSame('emails.franchise.xclusive-applicant', $view);
                $this->assertSame(['asha@example.test'], $recipients);
                $this->assertSame('Your Detailing Devils X-Clusive Franchise Application', $message->getSymfonyMessage()->getSubject());
            }
            $emails[] = $view;
        });
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.zerobounce.net/v2/validate') {
                return Http::response(['status' => 'valid']);
            }
            $this->assertSame('https://calls.example.test/callback?cust_mob=09876543210', $request->url());
            $this->assertSame('GET', $request->method());
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('xclusive', DB::table('tbl_common_leads')->sole()->record_type);

            return Http::response('ok');
        });
        $this->postJson(route('email.validate'), ['email' => 'asha@example.test'])
            ->assertOk()->assertJsonPath('result', 'valid')->assertSessionHas('email_validation.proof');
        $application = array_replace($this->application(), [
            'record_type' => 'FOFO', 'bussinessowner' => 'YES', 'type_of_bussiness' => 'Studio',
            'product_service' => 'Detailing', 'bussiness_description' => 'Two employees',
            'experienceautomotive' => 'YES', 'experience' => 'Five years',
            'existingautomotive' => 'YES', 'existingfacility' => 'Current facility',
            'spacearound' => 'YES', 'elaboratelocation' => 'Main road',
            'locationimage' => UploadedFile::fake()->image('applicant-location.png'),
        ]);
        $this->post(route('xclusive.submit'), $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);
        $lead = DB::table('tbl_common_leads')->sole();
        $this->assertSame('xclusive', $lead->record_type);
        $this->assertSame('919876543210', $lead->phone);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{32}\.png\z/', $lead->image_franchise);
        Storage::disk('fofo_images')->assertExists($lead->image_franchise);
        $this->assertNull(session('email_validation.proof'));
        $this->assertSame(['emails.franchise.xclusive-team', 'emails.franchise.xclusive-applicant'], $emails);
        Http::assertSentCount(2);
    }

    #[DataProvider('mockVerificationFailures')]
    public function test_failed_provider_verification_blocks_public_submission(string $status, int $httpStatus): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        config()->set('services.zerobounce.api_key', 'isolated-provider-key');
        Http::fake(['https://api.zerobounce.net/v2/validate' => Http::response(['status' => $status], $httpStatus)]);
        $this->postJson(route('email.validate'), ['email' => 'asha@example.test'])
            ->assertStatus($httpStatus)->assertJsonPath('success', false)->assertSessionMissing('email_validation.proof');
        $this->postJson(route('xclusive.submit'), $this->application())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Http::assertNotSent(fn ($request): bool => $request->method() === 'GET');
        Mail::assertNothingSent();
    }

    public static function mockVerificationFailures(): array
    {
        return [['invalid', 200], ['unavailable', 503]];
    }

    public function test_a_new_session_cannot_reuse_the_email_proof(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        $this->proof();
        session()->invalidate();
        $this->postJson(route('xclusive.submit'), $this->application() + ['email_valid' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('invalidPropertyImages')]
    public function test_invalid_property_upload_cannot_reach_persistence_or_follow_up(string $kind): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        $this->proof();
        $image = match ($kind) {
            'not-image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'size' => UploadedFile::fake()->image('site.jpg')->size(10001),
            'dimensions' => UploadedFile::fake()->image('site.png', 10241, 2),
        };
        $application = array_replace($this->application(), [
            'spacearound' => 'YES', 'elaboratelocation' => 'Main road', 'locationimage' => $image,
        ]);
        $this->post(route('xclusive.submit'), $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('locationimage');
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public static function invalidPropertyImages(): array
    {
        return [['not-image'], ['size'], ['dimensions']];
    }

    public function test_manual_location_and_international_mobile_are_saved_without_a_legacy_indian_call(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        $this->proof();
        $application = array_replace($this->application(), [
            'country' => 'other', 'phone' => '+44 7123 456789',
            'another_country_name' => 'Exampleland', 'another_state_name' => 'North', 'another_city_name' => 'Capital',
        ]);
        unset($application['state'], $application['city']);
        $this->postJson(route('xclusive.submit'), $application)->assertOk()->assertJsonPath('created', true);
        $lead = DB::table('tbl_common_leads')->sole();
        $this->assertSame(['Exampleland', 'North', 'Capital'], [$lead->country, $lead->state, $lead->city]);
        $this->assertSame('447123456789', $lead->phone);
        Http::assertNothingSent();
    }

    public function test_public_xclusive_route_enforces_csrf_with_the_test_bypass_disabled(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->withSession(['_token' => 'isolated-csrf-token']);
        $this->postJson(route('xclusive.submit'), $this->application())->assertStatus(419);
        $this->postJson(route('xclusive.submit'), $this->application() + ['_token' => 'wrong-token'])->assertStatus(419);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Http::assertNothingSent();
        $this->proof();
        $this->postJson(route('xclusive.submit'), $this->application() + ['_token' => 'isolated-csrf-token'])
            ->assertOk()->assertJsonPath('created', true);
        Http::assertSentCount(1);
    }

    public function test_public_xclusive_submission_throttle_rejects_the_sixth_attempt(): void
    {
        $this->enableSubmission();
        $this->enableCalling();
        Http::fake();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('xclusive.submit'), [])->assertUnprocessable();
        }
        $this->postJson(route('xclusive.submit'), [])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    private function withProposalDirectory(callable $review): void
    {
        $original = public_path();
        $directory = storage_path('framework/testing/xclusive-proposal-'.bin2hex(random_bytes(6)));
        $file = $directory.'/pdf/xclusive_proposal.pdf';
        mkdir($directory.'/pdf', 0777, true);
        $this->app->usePublicPath($directory);

        try {
            $review($file);
        } finally {
            $this->app->usePublicPath($original);
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($directory.'/pdf');
            rmdir($directory);
        }

        $this->assertFileDoesNotExist($file);
        $this->assertDirectoryDoesNotExist($directory);
    }

    private function proposalPdf(): string
    {
        $stream = "BT /F1 12 Tf 20 50 Td (Isolated proposal fixture) Tj ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 100] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function enableCalling(): void
    {
        config()->set('services.xclusive.call_enabled', true);
        config()->set('services.franchise_call.url', 'https://calls.example.test/callback');
    }

    private function enableSubmission(): void
    {
        // This override is limited to the isolated fixture database; never enable deployment config.
        config()->set('services.xclusive.submission_enabled', true);
        config()->set('mail.default', 'array');
        config()->set('mail.xclusive_team_address', 'team@example.test');
        config()->set('mail.fofo_team_address', 'fofo-team@example.test');
    }

    private function proof(string $email = 'asha@example.test', ?int $expiresAt = null): void
    {
        $this->withSession(['email_validation.proof' => [
            'email_hash' => hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key')),
            'expires_at' => $expiresAt ?? now()->addMinutes(10)->timestamp,
        ]]);
    }

    private function application(): array
    {
        return [
            'name' => 'Asha', 'lname' => 'Sharma', 'email' => 'asha@example.test',
            'phone' => '+91 98765 43210', 'dob' => '1990-05-12', 'profession' => 'Entrepreneur',
            'country' => 1, 'state' => 10, 'city' => 100, 'hearabout' => 'Google-Search', 'contacted' => 'Mail',
            'franchiseapply' => 'New Delhi', 'wantstart' => '2 Months', 'newbussiness' => 'Opening a studio',
            'bussinessowner' => 'NO', 'experienceautomotive' => 'NO', 'existingautomotive' => 'NO',
            'spacearound' => 'NO', 'spendfranchise' => 'Full Time', 'civillitigation' => 'no',
        ];
    }
}
