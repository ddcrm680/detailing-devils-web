<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Location tests require isolated in-memory SQLite.');
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

        DB::table('tbl_country_phone')->insert([
            ['id' => 1, 'countryname' => 'India'],
            ['id' => 2, 'countryname' => 'United States'],
            ['id' => 3, 'countryname' => 'Empty Country'],
        ]);
        DB::table('tbl_states')->insert([
            ['id' => 10, 'name' => 'Maharashtra', 'country_id' => 1],
            ['id' => 11, 'name' => 'Empty State', 'country_id' => 1],
            ['id' => 20, 'name' => 'California', 'country_id' => 2],
        ]);
        DB::table('tbl_cities')->insert([
            ['id' => 100, 'name' => 'Mumbai', 'state_id' => 10],
            ['id' => 200, 'name' => 'San Francisco', 'state_id' => 20],
        ]);
    }

    public function test_fofo_page_renders_without_an_active_submission_endpoint(): void
    {
        $this->get(route('fofo.show'))
            ->assertOk()
            ->assertSee('Application preview')
            ->assertSee('Final submission is not active yet.')
            ->assertSee('name="website"', false)
            ->assertSeeInOrder(['assets/js/common/location.js', 'assets/js/modules/fofo.js'])
            ->assertSee('data-countries-url="'.route('locations.countries').'"', false)
            ->assertSee('data-states-url="'.route('locations.states').'"', false)
            ->assertSee('data-cities-url="'.route('locations.cities').'"', false)
            ->assertSee('data-email-validation-url="'.route('email.validate').'"', false)
            ->assertSee('id="fofo-submit" disabled', false)
            ->assertSee('Submit Application — Coming Soon');

        $this->get(route('fofo.show'))
            ->assertSee('data-submit-url="'.route('fofo.submit').'"', false);
    }

    public function test_countries_are_loaded_from_the_location_table(): void
    {
        $this->getJson(route('locations.countries'))
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 3, 'name' => 'Empty Country'],
                ['id' => 1, 'name' => 'India'],
                ['id' => 2, 'name' => 'United States'],
            ]]);
    }

    public function test_states_are_filtered_by_country_and_empty_results_are_valid(): void
    {
        $this->getJson(route('locations.states', ['country_id' => 1]))
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 11, 'name' => 'Empty State'],
                ['id' => 10, 'name' => 'Maharashtra'],
            ]]);
        $this->getJson(route('locations.states', ['country_id' => 3]))
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_cities_are_filtered_by_state_and_empty_results_are_valid(): void
    {
        $this->getJson(route('locations.cities', ['state_id' => 10]))
            ->assertOk()->assertExactJson(['data' => [['id' => 100, 'name' => 'Mumbai']]]);
        $this->getJson(route('locations.cities', ['state_id' => 11]))
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_unknown_or_non_numeric_parent_ids_are_rejected(): void
    {
        $this->getJson(route('locations.states', ['country_id' => 999]))->assertNotFound();
        $this->getJson(route('locations.cities', ['state_id' => 999]))->assertNotFound();
        $this->getJson(route('locations.states', ['country_id' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('locations.cities', ['state_id' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('locations.states'))->assertUnprocessable();
        $this->getJson(route('locations.cities'))->assertUnprocessable();
    }

    public function test_location_database_failure_returns_a_safe_message(): void
    {
        Schema::drop('tbl_cities');

        $this->getJson(route('locations.cities', ['state_id' => 10]))
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Locations are temporarily unavailable.']);
    }
}
