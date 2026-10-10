<?php

namespace Tests\Feature\Franchise;

use App\Http\Requests\Franchise\StorePrimeRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorePrimeRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exercise FormRequest resolution and Laravel's JSON 422 response without
        // connecting to a database or changing the placeholder Prime controller.
        Route::post('/_test/prime-validation', function (StorePrimeRequest $request) {
            return response()->json([
                'validated' => $request->validated(),
                'normalized_phones' => $request->normalizedPhoneNumbers(),
            ]);
        });
    }

    public function test_a_valid_application_passes_validation(): void
    {
        $this->postJson('/_test/prime-validation', $this->validApplication())
            ->assertOk()
            ->assertJsonPath('validated.age_group', '25+')
            ->assertJsonPath('normalized_phones.mobile_e164', '+919876543210')
            ->assertJsonPath('normalized_phones.whatsapp_e164', '+919876543210');
    }

    public function test_an_international_mobile_uses_its_own_country_context(): void
    {
        $application = $this->validApplication();
        $application['mobile_number'] = '7123 456789';
        $application['mobile_e164'] = '+447123456789';
        $application['whatsapp_e164'] = '+447123456789';

        $this->postJson('/_test/prime-validation', $application)
            ->assertOk()
            ->assertJsonPath('normalized_phones.mobile_e164', '+447123456789');
    }

    public function test_rejects_a_number_that_has_e164_syntax_but_is_not_valid(): void
    {
        $application = $this->validApplication();
        $application['mobile_e164'] = '+91987654321';
        $application['whatsapp_e164'] = '+91987654321';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_e164']);
    }

    public function test_rejects_a_visible_mobile_number_that_disagrees_with_e164(): void
    {
        $application = $this->validApplication();
        $application['mobile_number'] = '9123456789';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_number']);
    }

    public function test_a_separate_whatsapp_number_is_normalized_and_must_match_its_visible_input(): void
    {
        $application = $this->validApplication();
        unset($application['same_as_mobile']);
        $application['whatsapp_number'] = '7123 456789';
        $application['whatsapp_e164'] = '+447123456789';

        $this->postJson('/_test/prime-validation', $application)
            ->assertOk()
            ->assertJsonPath('normalized_phones.whatsapp_e164', '+447123456789');

        $application['whatsapp_number'] = '7123 456780';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_number']);
    }

    public function test_a_separate_whatsapp_number_must_be_a_valid_mobile_number(): void
    {
        $application = $this->validApplication();
        unset($application['same_as_mobile']);
        $application['whatsapp_number'] = '7123 456789';
        $application['whatsapp_e164'] = '+44712345678';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_e164']);
    }

    public function test_allowed_choices_follow_the_shared_configuration(): void
    {
        config()->set('form_options.prime.occupations', [
            ['value' => 'Test Occupation', 'label' => 'Test Occupation'],
        ]);

        $application = $this->validApplication();
        $application['occupation'] = 'Test Occupation';
        $this->postJson('/_test/prime-validation', $application)->assertOk();

        $application['occupation'] = 'Entrepreneur';
        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['occupation']);
    }

    public function test_business_details_are_required_only_for_experienced_applicants(): void
    {
        $application = $this->validApplication();
        $application['business_experience'] = 'yes';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['business_name', 'industry']);

        $application['business_name'] = 'Detailing Studio';
        $application['industry'] = 'Automotive';

        $this->postJson('/_test/prime-validation', $application)->assertOk();
    }

    public function test_property_details_are_required_only_when_a_property_is_owned(): void
    {
        $application = $this->validApplication();
        $application['property_status'] = 'yes';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['property_location', 'carpet_area', 'road_facing']);

        $application['property_location'] = 'Sector 18, Noida';
        $application['carpet_area'] = 1800;
        $application['road_facing'] = 'yes';

        $this->postJson('/_test/prime-validation', $application)->assertOk();
    }

    public function test_a_separate_whatsapp_number_is_required_when_checkbox_is_unchecked(): void
    {
        $application = $this->validApplication();
        unset($application['same_as_mobile']);
        $application['whatsapp_e164'] = '';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_number', 'whatsapp_e164']);

        $application['whatsapp_number'] = '7123 456789';
        $application['whatsapp_e164'] = '+447123456789';

        $this->postJson('/_test/prime-validation', $application)->assertOk();
    }

    public function test_same_as_mobile_requires_matching_international_values(): void
    {
        $application = $this->validApplication();
        $application['whatsapp_e164'] = '+447123456789';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['whatsapp_e164']);
    }

    public function test_rejects_unlisted_choices_invalid_pin_and_missing_consent(): void
    {
        $application = $this->validApplication();
        $application['age_group'] = 'under-18';
        $application['occupation'] = 'Other';
        $application['state'] = 'Not a state';
        $application['pin_code'] = '000000';
        $application['start_timeline'] = 'Someday';
        $application['decision_maker'] = 'maybe';
        $application['contact_methods'] = ['SMS'];
        unset($application['consent']);

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'age_group', 'occupation', 'state', 'pin_code', 'start_timeline',
                'decision_maker', 'contact_methods.0', 'consent',
            ]);
    }

    public function test_rejects_malformed_phone_fields_and_a_filled_honeypot(): void
    {
        $application = $this->validApplication();
        $application['mobile_number'] = 'call me';
        $application['mobile_e164'] = '919876543210';
        $application['website'] = 'https://spam.example';

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_number', 'mobile_e164', 'website']);
    }

    public function test_validation_uses_laravels_standard_422_json_structure(): void
    {
        $application = $this->validApplication();
        unset($application['first_name']);

        $this->postJson('/_test/prime-validation', $application)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['first_name']]);
    }

    private function validApplication(): array
    {
        return [
            'first_name' => 'Asha',
            'last_name' => 'Sharma',
            'mobile_number' => '9876543210',
            'mobile_e164' => '+919876543210',
            'same_as_mobile' => '1',
            'whatsapp_number' => '',
            'whatsapp_e164' => '+919876543210',
            'email' => 'asha@example.test',
            'age_group' => '25+',
            'occupation' => 'Entrepreneur',
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'pin_code' => '201301',
            'business_experience' => 'no',
            'property_status' => 'looking_to_rent',
            'preferred_city' => 'Noida',
            'start_timeline' => 'Within 1-3 months',
            'decision_maker' => 'yes',
            'contact_methods' => ['Phone Call', 'WhatsApp'],
            'consent' => '1',
            'website' => '',
        ];
    }
}
