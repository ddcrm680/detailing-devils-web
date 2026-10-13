<?php

namespace Tests\Feature\Franchise;

use App\Actions\Franchise\SaveFranchiseLeadAction;
use App\Http\Requests\Franchise\StoreFofoRequest;
use Illuminate\Database\Schema\Blueprint;
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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use Throwable;

class FofoLeadSavingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('FOFO lead tests require isolated in-memory SQLite.');
        }

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('services.sms_alert.enabled', false);
        config()->set('services.fofo.submission_enabled', false);
        config()->set('services.fofo.call_enabled', false);
        config()->set('services.fofo.call_url', null);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
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

        // This test-only route does not register a public FOFO submission endpoint.
        Route::middleware('web')->post('/_test/fofo-save', function (StoreFofoRequest $request, SaveFranchiseLeadAction $save) {
            try {
                $created = $save->saveFofo($request->validated(), $request->normalizedMobile(), $request->session());
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable) {
                return response()->json(['success' => false], 503);
            }

            return response()->json(['success' => true, 'created' => $created]);
        });
    }

    public function test_new_fofo_lead_maps_all_active_fields_and_stores_only_an_image_filename(): void
    {
        $application = $this->validApplication();
        $application['bussinessowner'] = 'YES';
        $application['type_of_bussiness'] = 'Studio';
        $application['product_service'] = 'Detailing';
        $application['bussiness_description'] = 'Two employees';
        $application['experienceautomotive'] = 'YES';
        $application['experience'] = 'Five years';
        $application['existingautomotive'] = 'YES';
        $application['existingfacility'] = 'Current detailing facility';
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road location';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $lead = DB::table('tbl_common_leads')->first();
        $expected = [
            'name' => 'Asha Sharma', 'email' => 'asha@example.test',
            'country' => 'India', 'state' => 'Delhi', 'city' => 'New Delhi',
            'phone' => '919876543210', 'website' => 'https://example.com',
            'profession' => 'Entrepreneur', 'dob' => '1990-05-12',
            'contacted' => 'Mail', 'hearabout' => 'Google-Search',
            'newbussiness' => 'Opening a studio', 'wantstart' => '2 Months',
            'bussinessowner' => 'YES', 'type_of_bussiness' => 'Studio',
            'product_service' => 'Detailing', 'bussiness_description' => 'Two employees',
            'experienceautomotive' => 'YES', 'experience' => 'Five years',
            'existingautomotive' => 'YES', 'existingfacility' => 'Current detailing facility',
            'spacearound' => 'YES', 'elaboratelocation' => 'Main road location',
            'whichcityfranchise' => 'New Delhi', 'civillitigation' => 'no',
            'spendfranchise' => 'Full Time', 'record_type' => 'FOFO',
            'closed_leads' => '0', 'created_date' => now()->toDateString(),
        ];
        $this->assertSame(
            collect($expected)->sortKeys()->all(),
            collect($lead)->except(['id', 'image_franchise'])->sortKeys()->all()
        );
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{32}\.jpg\z/', $lead->image_franchise);
        Storage::disk('fofo_images')->assertExists($lead->image_franchise);
        $this->assertNull(session('email_validation.proof'));
    }

    #[DataProvider('additionalStoredImageFormats')]
    public function test_supported_property_image_is_stored_with_a_safe_server_chosen_extension(string $extension, int $imageType): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('client-image.'.$extension);
        $this->verifiedEmail($application['email']);

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('created', true);

        $savedFilename = DB::table('tbl_common_leads')->value('image_franchise');
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{32}\.'.preg_quote($extension, '/').'\z/', $savedFilename);
        $this->assertNotSame('client-image.'.$extension, $savedFilename);
        $this->assertSame($imageType, getimagesize(Storage::disk('fofo_images')->path($savedFilename))[2]);
        $this->assertSame([$savedFilename], Storage::disk('fofo_images')->allFiles());
    }

    public static function additionalStoredImageFormats(): array
    {
        return ['PNG' => ['png', IMAGETYPE_PNG], 'WebP' => ['webp', IMAGETYPE_WEBP]];
    }

    public function test_manual_location_names_are_saved_without_an_image_when_property_is_no(): void
    {
        $application = $this->validApplication();
        $application['country'] = 'other';
        unset($application['state'], $application['city']);
        $application['another_country_name'] = 'Exampleland';
        $application['another_state_name'] = 'North';
        $application['another_city_name'] = 'Capital';
        $this->verifiedEmail($application['email']);

        $this->postJson('/_test/fofo-save', $application)
            ->assertOk()->assertJsonPath('created', true);

        $lead = DB::table('tbl_common_leads')->first();
        $this->assertSame(['Exampleland', 'North', 'Capital'], [$lead->country, $lead->state, $lead->city]);
        $this->assertNull($lead->image_franchise);
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());

        $this->postJson('/_test/fofo-save', $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public function test_proof_matches_the_normalized_submitted_email(): void
    {
        $application = $this->validApplication();
        $application['email'] = '  Asha@Example.TEST  ';
        $this->verifiedEmail('asha@example.test');

        $this->postJson('/_test/fofo-save', $application)
            ->assertOk()->assertJsonPath('created', true);
        $this->assertSame('asha@example.test', DB::table('tbl_common_leads')->value('email'));
    }

    public function test_missing_mismatched_expired_and_other_session_proofs_cannot_save(): void
    {
        $application = $this->validApplication();
        $application['email_valid'] = true;

        $this->postJson('/_test/fofo-save', $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->verifiedEmail('other@example.test');
        $this->postJson('/_test/fofo-save', $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->verifiedEmail($application['email'], now()->subSecond()->timestamp);
        $this->postJson('/_test/fofo-save', $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->withSession(['email_validation.proof' => [
            'email_hash' => hash_hmac('sha256', $application['email'], (string) config('app.key')),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]]);
        session()->invalidate();
        $this->postJson('/_test/fofo-save', $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_expired_email_proof_does_not_store_a_property_image(): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email'], now()->subSecond()->timestamp);

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
    }

    public function test_duplicate_requires_fresh_proof_and_never_creates_an_image(): void
    {
        DB::table('tbl_common_leads')->insert([
            'phone' => '919876543210', 'record_type' => 'FOFO', 'name' => 'Existing',
        ]);
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->verifiedEmail($application['email']);
        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('created', false);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        $this->assertNull(session('email_validation.proof'));
    }

    #[DataProvider('historicalIndianPhones')]
    public function test_historical_indian_phone_is_a_duplicate_without_upload_or_follow_up(string $existingPhone): void
    {
        $this->enableSubmission();
        Mail::spy();
        Http::fake();
        DB::table('tbl_common_leads')->insert([
            'phone' => $existingPhone, 'record_type' => 'FOFO',
            'email' => 'previous@example.test', 'name' => 'Existing',
        ]);
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');

        $this->post(route('fofo.submit'), $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->verifiedEmail($application['email']);
        $this->post(route('fofo.submit'), $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', false);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        $this->assertNull(session('email_validation.proof'));
        Mail::shouldNotHaveReceived('send');
        Http::assertNothingSent();
    }

    public static function historicalIndianPhones(): array
    {
        return [
            'national' => ['9876543210'],
            'zero prefixed' => ['09876543210'],
            'country code' => ['919876543210'],
            'plus country code' => ['+919876543210'],
        ];
    }

    public function test_different_indian_mobile_still_creates_a_normalized_lead(): void
    {
        foreach (['9876543211', '09876543211', '919876543211', '+919876543211'] as $phone) {
            DB::table('tbl_common_leads')->insert([
                'phone' => $phone, 'record_type' => 'FOFO', 'name' => 'Other lead',
            ]);
        }
        $this->verifiedEmail('asha@example.test');

        $this->postJson('/_test/fofo-save', $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);

        $this->assertSame(5, DB::table('tbl_common_leads')->count());
        $this->assertSame(1, DB::table('tbl_common_leads')
            ->where('record_type', 'FOFO')->where('phone', '919876543210')->count());
    }

    public function test_historical_indian_phone_in_another_record_type_does_not_block_fofo(): void
    {
        DB::table('tbl_common_leads')->insert([
            'phone' => '9876543210', 'record_type' => 'prime-franchise', 'name' => 'Other lead',
        ]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson('/_test/fofo-save', $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);

        $this->assertSame(2, DB::table('tbl_common_leads')->count());
    }

    public function test_non_indian_mobile_uses_only_its_exact_international_digits(): void
    {
        DB::table('tbl_common_leads')->insert([
            'phone' => '2025550123', 'record_type' => 'FOFO', 'name' => 'Other lead',
        ]);
        $application = $this->validApplication();
        $application['phone'] = '+1 202 555 0123';
        $this->verifiedEmail($application['email']);

        $this->postJson('/_test/fofo-save', $application)
            ->assertOk()->assertJsonPath('created', true);
        $this->assertSame(1, DB::table('tbl_common_leads')->where('phone', '12025550123')->count());

        $this->verifiedEmail($application['email']);
        $this->postJson('/_test/fofo-save', $application)
            ->assertOk()->assertJsonPath('created', false);
        $this->assertSame(2, DB::table('tbl_common_leads')->count());
    }

    public function test_same_phone_in_another_record_type_does_not_block_fofo(): void
    {
        DB::table('tbl_common_leads')->insert([
            'phone' => '919876543210', 'record_type' => 'prime-franchise', 'name' => 'Existing',
        ]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson('/_test/fofo-save', $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);
        $this->assertSame(2, DB::table('tbl_common_leads')->count());
    }

    public function test_failed_database_insert_removes_the_uploaded_image_and_keeps_the_proof(): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);
        Schema::table('tbl_common_leads', fn (Blueprint $table) => $table->dropColumn('name'));

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertStatus(503)->assertJsonPath('success', false);
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        $this->assertNotNull(session('email_validation.proof'));
    }

    public function test_failed_database_insert_preserves_an_existing_image(): void
    {
        $existingFilename = str_repeat('a', 32).'.jpg';
        Storage::disk('fofo_images')->put($existingFilename, 'previous applicant image');
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);
        Schema::table('tbl_common_leads', fn (Blueprint $table) => $table->dropColumn('name'));

        $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
            ->assertStatus(503);

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        $this->assertSame('previous applicant image', Storage::disk('fofo_images')->get($existingFilename));
        $this->assertSame([$existingFilename], Storage::disk('fofo_images')->allFiles());
    }

    public function test_existing_filename_is_never_overwritten(): void
    {
        $filename = str_repeat('a', 32).'.jpg';
        Storage::disk('fofo_images')->put($filename, 'existing image');
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);
        Str::createRandomStringsUsing(static fn () => str_repeat('a', 32));

        try {
            $this->post('/_test/fofo-save', $application, ['Accept' => 'application/json'])
                ->assertStatus(503);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('existing image', Storage::disk('fofo_images')->get($filename));
        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_public_submission_is_disabled_by_default(): void
    {
        $this->get(route('fofo.show'))->assertOk()
            ->assertSee('id="fofo-submit" disabled', false);
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertStatus(503)->assertJsonPath('success', false);

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
    }

    public function test_new_public_submission_sends_two_emails_and_one_call_after_save(): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.call_url', 'https://calls.detailingdevils.com/callback/playback.php');
        $this->get(route('fofo.show'))->assertOk()
            ->assertSee('id="fofo-submit"', false)
            ->assertDontSee('Submit Application — Coming Soon');
        Mail::spy();
        Http::fake(['https://calls.detailingdevils.com/callback/playback.php*' => Http::response('ok')]);
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);

        $this->post(route('fofo.submit'), $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Storage::disk('fofo_images')->assertExists(DB::table('tbl_common_leads')->value('image_franchise'));
        $this->assertFofoMail('emails.franchise.fofo-team', ['team@example.test', 'other@example.test']);
        $this->assertFofoMail('emails.franchise.fofo-applicant', ['asha@example.test']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://calls.detailingdevils.com/callback/playback.php?cust_mob=09876543210');
    }

    public function test_team_email_uses_saved_location_names_and_generated_image_filename(): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.call_enabled', false);
        Mail::spy();
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('client-site.jpg');
        $application['experienceautomotive'] = 'YES';
        $application['experience'] = 'Detailing work';
        $application['existingautomotive'] = 'YES';
        $application['existingfacility'] = 'Workshop';
        $this->verifiedEmail($application['email']);

        $this->post(route('fofo.submit'), $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('created', true);

        $savedFilename = DB::table('tbl_common_leads')->value('image_franchise');
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{32}\.(?:jpg|png|webp)\z/', $savedFilename);
        Storage::disk('fofo_images')->assertExists($savedFilename);
        Mail::shouldHaveReceived('send')->withArgs(function (string $view, array $data) use ($savedFilename): bool {
            if ($view !== 'emails.franchise.fofo-team') {
                return false;
            }

            $html = view($view, $data)->render();
            foreach (['India', 'Delhi', 'New Delhi', 'Detailing work', 'Workshop'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
            $this->assertStringContainsString('href="'.asset('uploads/images/'.$savedFilename).'"', $html);
            $this->assertStringNotContainsString('client-site.jpg', $html);
            $this->assertStringNotContainsString('Planning to Invest', $html);

            return true;
        })->once();
    }

    public function test_team_email_uses_manual_location_names_without_an_image_link(): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.call_enabled', false);
        Mail::spy();
        $application = $this->validApplication();
        $application['country'] = 'other';
        unset($application['state'], $application['city']);
        $application['another_country_name'] = 'Exampleland';
        $application['another_state_name'] = 'North';
        $application['another_city_name'] = 'Capital';
        $this->verifiedEmail($application['email']);

        $this->postJson(route('fofo.submit'), $application)
            ->assertOk()->assertJsonPath('created', true);

        $this->assertNull(DB::table('tbl_common_leads')->value('image_franchise'));
        Mail::shouldHaveReceived('send')->withArgs(function (string $view, array $data): bool {
            if ($view !== 'emails.franchise.fofo-team') {
                return false;
            }

            $html = view($view, $data)->render();
            foreach (['Exampleland', 'North', 'Capital', 'Not provided'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
            $this->assertStringNotContainsString('View location image', $html);
            $this->assertStringNotContainsString('uploads/images/', $html);

            return true;
        })->once();
    }

    public function test_duplicate_public_submission_has_no_follow_up(): void
    {
        $this->enableSubmission();
        Mail::spy();
        Http::fake();
        DB::table('tbl_common_leads')->insert([
            'phone' => '919876543210', 'record_type' => 'FOFO', 'name' => 'Existing',
        ]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', false);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Mail::shouldNotHaveReceived('send');
        Http::assertNothingSent();
    }

    public function test_invalid_or_expired_email_proof_rejects_public_submission(): void
    {
        $this->enableSubmission();
        Mail::spy();
        Http::fake();
        $application = $this->validApplication();

        $this->postJson(route('fofo.submit'), $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->verifiedEmail('other@example.test');
        $this->postJson(route('fofo.submit'), $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->verifiedEmail($application['email'], now()->subSecond()->timestamp);
        $this->postJson(route('fofo.submit'), $application)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, DB::table('tbl_common_leads')->count());
        Mail::shouldNotHaveReceived('send');
        Http::assertNothingSent();
    }

    public function test_database_failure_does_not_schedule_follow_up_and_removes_image(): void
    {
        $this->enableSubmission();
        Mail::spy();
        Http::fake();
        Schema::table('tbl_common_leads', fn (Blueprint $table) => $table->dropColumn('name'));
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg');
        $this->verifiedEmail($application['email']);

        $this->post(route('fofo.submit'), $application, ['Accept' => 'application/json'])
            ->assertStatus(503)->assertJsonPath('success', false);

        $this->assertSame([], Storage::disk('fofo_images')->allFiles());
        Mail::shouldNotHaveReceived('send');
        Http::assertNothingSent();
    }

    public function test_independent_email_and_call_failures_do_not_change_saved_response(): void
    {
        $this->enableSubmission();
        $attempts = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view) use (&$attempts): void {
            $attempts[] = $view;
            if ($view === 'emails.franchise.fofo-team') {
                throw new RuntimeException('Simulated mail failure.');
            }
        });
        Http::fake(['https://calls.example.test/callback/playback.php*' => Http::response('unavailable', 503)]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame(['emails.franchise.fofo-team', 'emails.franchise.fofo-applicant'], $attempts);
        Http::assertSentCount(1);
        $this->assertSame(1, DB::table('tbl_common_leads')->count());
    }

    public function test_applicant_email_failure_does_not_prevent_team_email_or_call(): void
    {
        $this->enableSubmission();
        $attempts = [];
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (string $view) use (&$attempts): void {
            $attempts[] = $view;
            if ($view === 'emails.franchise.fofo-applicant') {
                throw new RuntimeException('Simulated mail failure.');
            }
        });
        Http::fake(['https://calls.example.test/callback/playback.php*' => Http::response('ok')]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);

        $this->assertSame(['emails.franchise.fofo-team', 'emails.franchise.fofo-applicant'], $attempts);
        Http::assertSentCount(1);
    }

    public function test_calling_service_is_disabled_independently_of_submission(): void
    {
        $this->enableSubmission();
        config()->set('services.fofo.call_enabled', false);
        config()->set('site.brand', 'Example Studios');
        Mail::spy();
        Http::fake();
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('created', true);

        $this->assertFofoMail('emails.franchise.fofo-team', ['team@example.test', 'other@example.test']);
        $this->assertFofoMail('emails.franchise.fofo-applicant', ['asha@example.test']);
        Http::assertNothingSent();
    }

    #[DataProvider('callbackFailureStatuses')]
    public function test_calling_http_error_does_not_change_saved_response(int $status): void
    {
        $this->enableSubmission();
        Mail::spy();
        Log::spy();
        Http::fake(['https://calls.example.test/callback/playback.php*' => Http::response('unavailable', $status)]);
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->with('FOFO calling callback failed.', ['status' => $status])->once();
    }

    public static function callbackFailureStatuses(): array
    {
        return ['HTTP 400' => [400], 'HTTP 503' => [503]];
    }

    #[DataProvider('callbackConnectionFailures')]
    public function test_calling_connection_failure_does_not_change_saved_response(string $error): void
    {
        $this->enableSubmission();
        Mail::spy();
        Log::spy();
        $attempts = 0;
        Http::fake(function () use (&$attempts, $error): never {
            $attempts++;
            throw new ConnectionException($error);
        });
        $this->verifiedEmail('asha@example.test');

        $this->postJson(route('fofo.submit'), $this->validApplication())
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('created', true);

        $this->assertSame(1, DB::table('tbl_common_leads')->count());
        $this->assertSame(1, $attempts);
        Log::shouldHaveReceived('warning')->with('FOFO calling callback failed.', [
            'exception' => ConnectionException::class,
        ])->once();
    }

    public static function callbackConnectionFailures(): array
    {
        return ['timeout' => ['Simulated timeout'], 'connection failure' => ['Simulated connection failure']];
    }

    public function test_non_indian_mobile_skips_call_without_logging_the_number(): void
    {
        $this->enableSubmission();
        Mail::spy();
        Log::spy();
        Http::fake();
        $application = $this->validApplication();
        $application['phone'] = '+1 202 555 0123';
        $this->verifiedEmail($application['email']);

        $this->postJson(route('fofo.submit'), $application)
            ->assertOk()->assertJsonPath('created', true);

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->with('FOFO calling callback skipped: the mobile number is outside the legacy format.')
            ->once();
    }

    public function test_applicant_email_uses_fofo_public_https_proposal(): void
    {
        URL::forceRootUrl('https://www.detailingdevils.com');
        URL::forceScheme('https');

        $html = view('emails.franchise.fofo-applicant', ['name' => 'Test'])->render();

        $this->assertStringContainsString('src="https://www.detailingdevils.com/img/dd-logo-top.png"', $html);
        $this->assertStringContainsString('href="https://www.detailingdevils.com/pdf/Franchise-Proposal-2024.pdf"', $html);
        $this->assertStringNotContainsString('Prime-Franchise-Proposal.pdf', $html);
        $this->assertFileExists(public_path('pdf/Franchise-Proposal-2024.pdf'));
    }

    public function test_applicant_email_uses_hosted_legacy_social_icons_and_preserves_the_map_icon(): void
    {
        URL::forceRootUrl('https://www.detailingdevils.com');
        URL::forceScheme('https');

        $html = view('emails.franchise.fofo-applicant', ['name' => 'Test'])->render();

        foreach (['loc2' => 'Location map icon', 'p1' => 'Facebook', 'p2' => 'Instagram', 'p3' => 'YouTube'] as $icon => $alt) {
            $path = public_path('img/email/'.$icon.'.png');
            $this->assertFileExists($path);
            $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
            if ($icon === 'loc2') {
                $this->assertStringNotContainsString('/img/email/loc2.png', $html);
            } else {
                $this->assertStringContainsString('src="https://www.detailingdevils.com/img/email/'.$icon.'.png"', $html);
                $this->assertStringContainsString('alt="'.$alt.'"', $html);
            }
        }

        $this->assertStringNotContainsString('https://goo.gl/maps/gtAdnfUQX3PnzZw7A', $html);
    }

    public function test_applicant_email_preserves_legacy_sections_and_escapes_the_name(): void
    {
        $this->assertSame('B-24, Pushpanjali Enclave, Pitampura, Delhi 110034', config('site.address'));
        $this->assertSame('145+', config('site.numbers.0.value'));
        $this->assertSame('https://www.instagram.com/detailingdevils/', config('site.social.Instagram'));
        $this->assertSame('https://www.facebook.com/DetailingDevils/', config('site.social.Facebook'));
        $this->assertSame('https://www.youtube.com/channel/UCixCVs6Aucy83FJsbMysKYg', config('site.social.YouTube'));

        $html = view('emails.franchise.fofo-applicant', [
            'name' => 'Asha <script>alert(1)</script>',
        ])->render();

        $this->assertStringContainsString('Hi Asha &lt;script&gt;alert(1)&lt;/script&gt;,', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        foreach ([
            'Greetings from Detailing', 'Who We', 'What We', 'Nano Ceramic Coating:',
            'DNA Graphene:', 'Benefits of Detailing Devils Franchise',
            config('site.numbers.0.value'), 'Our Franchise', 'Basic Cost:', '35 lacs',
            '1500 - 1800 square feet', 'Rs 30,000/- Monthly',
            'Investment Includes', '₹ 700 per sq ft', 'Our <span style="color:red;">Address</span>',
            config('site.address'), config('site.phone'), '+91 8076 767 898',
        ] as $legacyDetail) {
            $this->assertStringContainsString($legacyDetail, $html);
        }
        $this->assertStringContainsString(config('site.social.Facebook'), $html);
        $this->assertStringContainsString(config('site.social.Instagram'), $html);
        $this->assertStringContainsString(config('site.social.YouTube'), $html);
        $this->assertStringContainsString('href="tel:'.config('site.phone_link').'"', $html);
        $this->assertStringContainsString('href="mailto:'.config('site.email').'"', $html);
        $this->assertStringContainsString('>'.config('site.email').'</a>', $html);
        $this->assertStringNotContainsString('D-50, Sector 2', $html);
        $this->assertStringNotContainsString('Detailing Devils Head Office Noida', $html);
        $this->assertStringNotContainsString('goo.gl/maps/gtAdnfUQX3PnzZw7A', $html);
        $this->assertStringNotContainsString('140+', $html);
        $this->assertStringNotContainsString('we have enclosed', $html);
        $this->assertStringNotContainsString('/assets/mail/', $html);
    }

    public function test_applicant_email_reads_company_details_and_social_destinations_from_site_config(): void
    {
        config()->set('site.brand', 'Example Studios');
        config()->set('site.phone', '+91 1111 222 333');
        config()->set('site.phone_link', '+911111222333');
        config()->set('site.email', 'hello@example.test');
        config()->set('site.address', 'New Delhi office address');
        config()->set('site.numbers.0.value', '200+');
        config()->set('site.social.Facebook', 'https://example.test/facebook');
        config()->set('site.social.Instagram', 'https://example.test/instagram');
        config()->set('site.social.YouTube', 'https://example.test/youtube');

        $html = view('emails.franchise.fofo-applicant', ['name' => 'Test'])->render();

        foreach (['Example Studios', 'New Delhi office address', '200+', '+91 1111 222 333', 'hello@example.test'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        $this->assertStringContainsString('href="tel:+911111222333"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', $html);
        foreach (['facebook', 'instagram', 'youtube'] as $network) {
            $this->assertStringContainsString('href="https://example.test/'.$network.'"', $html);
        }
    }

    private function enableSubmission(): void
    {
        config()->set('services.fofo.submission_enabled', true);
        config()->set('services.fofo.call_enabled', true);
        config()->set('services.fofo.call_url', 'https://calls.example.test/callback/playback.php');
        config()->set('mail.default', 'array');
        config()->set('mail.fofo_team_address', 'team@example.test, invalid, other@example.test');
    }

    private function assertFofoMail(string $view, array $recipients): void
    {
        Mail::shouldHaveReceived('send')->withArgs(function (string $sentView, array $data, callable $callback) use ($view, $recipients): bool {
            if ($sentView !== $view) {
                return false;
            }

            $this->assertSame(0, DB::transactionLevel());
            $message = new Message(new Email);
            $callback($message);
            $this->assertSame($recipients, array_map(
                static fn ($address): string => $address->getAddress(), $message->getSymfonyMessage()->getTo()
            ));
            $this->assertSame('Franchise Owned Franchise Operated Model', $message->getSymfonyMessage()->getSubject());
            $html = view($view, $data)->render();
            $this->assertStringContainsString('Asha Sharma', $html);
            $this->assertStringContainsString(config('site.brand'), $html);
            $this->assertStringNotContainsString('Prime-Franchise-Proposal.pdf', $html);

            if ($view === 'emails.franchise.fofo-applicant') {
                $this->assertStringContainsString(asset('pdf/Franchise-Proposal-2024.pdf'), $html);
            }

            return true;
        })->once();
    }

    private function verifiedEmail(string $email, ?int $expiresAt = null): void
    {
        $this->withSession(['email_validation.proof' => [
            'email_hash' => hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key')),
            'expires_at' => $expiresAt ?? now()->addMinutes(10)->timestamp,
        ]]);
    }

    private function validApplication(): array
    {
        return [
            'name' => 'Asha', 'lname' => 'Sharma', 'email' => 'asha@example.test',
            'phone' => '+91 98765 43210', 'dob' => '1990-05-12',
            'profession' => 'Entrepreneur', 'country' => 1, 'state' => 10, 'city' => 100,
            'hearabout' => 'Google-Search', 'contacted' => 'Mail',
            'franchiseapply' => 'New Delhi', 'wantstart' => '2 Months',
            'newbussiness' => 'Opening a studio', 'bussinessowner' => 'NO',
            'experienceautomotive' => 'NO', 'existingautomotive' => 'NO',
            'spacearound' => 'NO', 'spendfranchise' => 'Full Time',
            'civillitigation' => 'no', 'website' => 'https://example.com',
        ];
    }
}
