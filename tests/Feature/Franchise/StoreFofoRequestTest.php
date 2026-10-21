<?php

namespace Tests\Feature\Franchise;

use App\Http\Requests\Franchise\StoreFofoRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StoreFofoRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('FOFO validation tests require isolated in-memory SQLite.');
        }

        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        config()->set('services.sms_alert.enabled', false);
        Mail::fake();
        Http::preventStrayRequests();

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

        DB::table('tbl_country_phone')->insert([['id' => 1, 'countryname' => 'India'], ['id' => 2, 'countryname' => 'United Kingdom']]);
        DB::table('tbl_states')->insert([['id' => 10, 'name' => 'Delhi', 'country_id' => 1], ['id' => 20, 'name' => 'England', 'country_id' => 2]]);
        DB::table('tbl_cities')->insert([['id' => 100, 'name' => 'New Delhi', 'state_id' => 10], ['id' => 200, 'name' => 'London', 'state_id' => 20]]);

        Route::post('/_test/fofo-validation', function (StoreFofoRequest $request) {
            return response()->json([
                'validated' => collect($request->validated())->except('locationimage')->all(),
                'has_location_image' => $request->hasFile('locationimage'),
                'normalized_mobile' => $request->normalizedMobile(),
            ]);
        });
    }

    public function test_valid_application_and_international_phone_are_normalized(): void
    {
        $this->postJson('/_test/fofo-validation', $this->validApplication())
            ->assertOk()
            ->assertJsonPath('normalized_mobile', '+919876543210')
            ->assertJsonPath('validated.website', 'https://example.com');

        $application = $this->validApplication();
        $application['phone'] = '+44 7123 456789';
        $this->postJson('/_test/fofo-validation', $application)
            ->assertOk()->assertJsonPath('normalized_mobile', '+447123456789');
    }

    public function test_preview_limits_match_crm_validation_and_submission_remains_disabled(): void
    {
        $this->get(route('fofo.show'))
            ->assertOk()
            ->assertSee('name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="32"', false)
            ->assertSee('intl-tel-input@29.1.2/dist/js/intlTelInput.min.js', false)
            ->assertDontSee('India (+91) is selected by default. Choose your country and enter your mobile number.')
            ->assertSee('id="newbussiness" name="newbussiness" rows="3" maxlength="255"', false)
            ->assertSee('id="type_of_bussiness" name="type_of_bussiness" maxlength="255"', false)
            ->assertSee('id="product_service" name="product_service" maxlength="255"', false)
            ->assertSee('id="bussiness_description" name="bussiness_description" rows="3" maxlength="255"', false)
            ->assertSee('id="experience" name="experience" rows="3" maxlength="255"', false)
            ->assertSee('id="existingfacility" name="existingfacility" rows="3" maxlength="255"', false)
            ->assertSee('id="elaboratelocation" name="elaboratelocation" rows="3" maxlength="255"', false)
            ->assertSee('Up to 255 characters.')
            ->assertSee('up to 10,000 KB.')
            ->assertSee('id="fofo-submit" disabled', false);
    }

    public function test_timeline_labels_submit_their_matching_values(): void
    {
        $this->get(route('fofo.show'))
            ->assertOk()
            ->assertSeeInOrder([
                '<option value="Immediatly">Immediately</option>',
                '<option value="2 Months">2 Months</option>',
                '<option value="3 Months">3 Months</option>',
            ], false)
            ->assertDontSee('<option value="3 Months">2 Months</option>', false);

        foreach (['Immediatly', '2 Months', '3 Months'] as $timeline) {
            $application = $this->validApplication();
            $application['wantstart'] = $timeline;
            $this->postJson('/_test/fofo-validation', $application)
                ->assertOk()
                ->assertJsonPath('validated.wantstart', $timeline);
        }

        $application['wantstart'] = '4 Months';
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('wantstart');
    }

    public function test_location_ids_must_exist_and_follow_the_country_state_city_relationship(): void
    {
        $application = $this->validApplication();
        $application['country'] = 2;
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['state']);

        $application = $this->validApplication();
        $application['city'] = 200;
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['city']);

        $application['country'] = 999;
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['country']);
    }

    public function test_other_country_requires_manual_location_and_excludes_database_ids(): void
    {
        $application = $this->validApplication();
        $application['country'] = 'other';
        unset($application['state'], $application['city']);
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['another_country_name', 'another_state_name', 'another_city_name']);

        $application['another_country_name'] = 'Exampleland';
        $application['another_state_name'] = 'North';
        $application['another_city_name'] = 'Capital';
        $this->postJson('/_test/fofo-validation', $application)
            ->assertOk()->assertJsonMissingPath('validated.state');

        $application['state'] = 10;
        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors(['state']);
    }

    public function test_each_conditional_section_requires_its_details_only_when_selected(): void
    {
        $application = $this->validApplication();
        $application['bussinessowner'] = 'YES';
        $application['experienceautomotive'] = 'YES';
        $application['existingautomotive'] = 'YES';
        $application['spacearound'] = 'YES';

        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()->assertJsonValidationErrors([
                'type_of_bussiness', 'product_service', 'bussiness_description',
                'experience', 'existingfacility', 'elaboratelocation', 'locationimage',
            ]);

        $application += [
            'type_of_bussiness' => 'Services',
            'product_service' => 'Vehicle detailing',
            'bussiness_description' => 'Two employees',
            'experience' => 'Five years of detailing',
            'existingfacility' => 'A small studio',
            'elaboratelocation' => 'Main road, 1500 square feet',
            'locationimage' => UploadedFile::fake()->image('site.jpg'),
        ];
        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_invalid_phone_dates_choices_website_and_oversized_crm_fields_are_rejected(): void
    {
        $application = $this->validApplication();
        $application['phone'] = '+91987654321';
        $application['dob'] = 'tomorrow';
        $application['wantstart'] = 'Someday';
        $application['contacted'] = 'SMS';
        $application['website'] = 'javascript:alert(1)';
        $application['newbussiness'] = str_repeat('a', 256);

        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'dob', 'wantstart', 'contacted', 'website', 'newbussiness']);
    }

    public function test_non_image_location_upload_is_rejected_without_storing_it(): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');

        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['locationimage']);
    }

    #[DataProvider('supportedLocationImages')]
    public function test_property_accepts_supported_image_formats(string $extension): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.'.$extension);

        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('has_location_image', true);
    }

    public static function supportedLocationImages(): array
    {
        return ['JPEG' => ['jpg'], 'PNG' => ['png'], 'WebP' => ['webp']];
    }

    public function test_property_rejects_gif_even_though_it_is_an_image(): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.gif');

        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('locationimage');
    }

    public function test_property_rejects_an_image_over_the_size_limit(): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.jpg')->size(10001);

        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('locationimage');
    }

    #[DataProvider('oversizedImageDimensions')]
    public function test_property_rejects_images_over_the_dimension_limits(int $width, int $height): void
    {
        $application = $this->validApplication();
        $application['spacearound'] = 'YES';
        $application['elaboratelocation'] = 'Main road';
        $application['locationimage'] = UploadedFile::fake()->image('site.png', $width, $height);

        $this->post('/_test/fofo-validation', $application, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('locationimage');
    }

    public static function oversizedImageDimensions(): array
    {
        return ['width' => [10241, 2], 'height' => [2, 7681]];
    }

    public function test_browser_email_valid_flag_is_not_validated_data(): void
    {
        $application = $this->validApplication();
        $application['email_valid'] = true;

        $this->postJson('/_test/fofo-validation', $application)
            ->assertOk()->assertJsonMissingPath('validated.email_valid');
    }

    public function test_inactive_conditional_values_are_excluded_from_validated_data(): void
    {
        $application = $this->validApplication();
        $application['type_of_bussiness'] = 'Stale business value';
        $application['experience'] = 'Stale experience value';
        $application['elaboratelocation'] = 'Stale property value';
        $application['another_country_name'] = 'Stale country value';

        $this->postJson('/_test/fofo-validation', $application)
            ->assertOk()
            ->assertJsonMissingPath('validated.type_of_bussiness')
            ->assertJsonMissingPath('validated.experience')
            ->assertJsonMissingPath('validated.elaboratelocation')
            ->assertJsonMissingPath('validated.another_country_name');
    }

    public function test_combined_name_must_fit_the_crm_column_and_errors_use_standard_422_json(): void
    {
        $application = $this->validApplication();
        $application['name'] = str_repeat('A', 50);
        $application['lname'] = str_repeat('B', 50);

        $this->postJson('/_test/fofo-validation', $application)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['lname']]);
    }

    private function validApplication(): array
    {
        return [
            'name' => 'Asha', 'lname' => 'Sharma', 'email' => 'asha@example.test',
            'phone' => '+91 98765 43210', 'dob' => '1990-05-12', 'profession' => 'Entrepreneur',
            'country' => 1, 'state' => 10, 'city' => 100,
            'hearabout' => 'Google-Search', 'contacted' => 'Mail',
            'franchiseapply' => 'New Delhi', 'wantstart' => 'Immediatly',
            'newbussiness' => 'Looking to expand into automotive services.',
            'bussinessowner' => 'NO', 'experienceautomotive' => 'NO',
            'existingautomotive' => 'NO', 'spacearound' => 'NO',
            'spendfranchise' => 'Full Time', 'civillitigation' => 'no',
            'website' => 'https://example.com',
        ];
    }
}
