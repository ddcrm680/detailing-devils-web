<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EnquirySubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never create the fixture unless the default connection is isolated SQLite.
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new RuntimeException('Enquiry tests require isolated in-memory SQLite.');
        }

        config()->set('services.enquiry.submission_enabled', false);
        config()->set('services.enquiry.email_enabled', false);
        config()->set('mail.default', 'array');
        Http::preventStrayRequests();
        Mail::fake();

        Schema::create('tbl_SaveContactForm7_8', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('first_name', 50);
            $table->string('last_name', 50)->nullable();
            $table->string('your_phone', 20);
            $table->string('city', 50)->nullable();
            $table->text('car_model')->nullable();
            $table->text('interested_in')->nullable();
            $table->text('TypeMessage')->nullable();
            $table->text('source')->nullable();
            $table->string('query_type', 100)->nullable();
            $table->timestamp('created_on')->useCurrent();
        });
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'name' => 'Asha Kumar',
            'phone' => '+919876543210',
            'city' => 'Delhi',
            'car' => 'Hyundai Creta',
            'interest' => 'Ceramic Coating',
        ], $changes);
    }

    public function test_disabled_flag_returns_503_without_insert_or_email(): void
    {
        Mail::partialMock()->shouldNotReceive('raw');

        $this->postJson(route('enquiry.store'), $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Enquiry submissions are temporarily unavailable. Please try again later.');

        $this->assertSame(0, DB::table('tbl_SaveContactForm7_8')->count());
        $this->assertFalse(Schema::hasTable('enquiries'));
    }

    public function test_enabled_submission_with_email_disabled_saves_without_notification(): void
    {
        config()->set('services.enquiry.submission_enabled', true);
        Mail::partialMock()->shouldNotReceive('raw');

        $this->postJson(route('enquiry.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::table('tbl_SaveContactForm7_8')->count());
    }

    public function test_enabled_submission_and_email_inserts_the_complete_crm_mapping_and_attempts_email(): void
    {
        config()->set('services.enquiry.submission_enabled', true);
        config()->set('services.enquiry.email_enabled', true);

        Mail::partialMock()->shouldReceive('raw')->once()->withArgs(function (string $body, callable $configure): bool {
            $message = new Message(new Email);
            $configure($message);
            $email = $message->getSymfonyMessage();

            return str_contains($body, 'Name: Asha Kumar')
                && str_contains($body, 'Phone: +919876543210')
                && $email->getTo()[0]->getAddress() === config('site.email')
                && $email->getSubject() === 'New enquiry: Ceramic Coating — Asha Kumar';
        });

        $this->postJson(route('enquiry.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::table('tbl_SaveContactForm7_8')->count());
        $row = DB::table('tbl_SaveContactForm7_8')->first();
        $this->assertSame('Asha Kumar', $row->first_name);
        $this->assertNull($row->last_name);
        $this->assertSame('+919876543210', $row->your_phone);
        $this->assertSame('Delhi', $row->city);
        $this->assertSame('Hyundai Creta', $row->car_model);
        $this->assertSame('Ceramic Coating', $row->interested_in);
        $this->assertSame("Vehicle: Hyundai Creta\nInterested in: Ceramic Coating", $row->TypeMessage);
        $this->assertSame('website-home', $row->source);
        $this->assertSame('service-enquiry', $row->query_type);
        $this->assertLessThanOrEqual(5, abs(now('UTC')->timestamp - CarbonImmutable::parse($row->created_on, 'UTC')->timestamp));
        $this->assertFalse(Schema::hasTable('enquiries'));

    }

    public function test_optional_details_are_not_invented(): void
    {
        config()->set('services.enquiry.submission_enabled', true);

        $this->postJson(route('enquiry.store'), $this->payload([
            'city' => null, 'car' => null, 'interest' => null,
        ]))->assertOk();

        $row = DB::table('tbl_SaveContactForm7_8')->first();
        $this->assertNull($row->city);
        $this->assertNull($row->car_model);
        $this->assertNull($row->interested_in);
        $this->assertNull($row->TypeMessage);
    }

    public function test_values_at_crm_column_limits_are_accepted_without_truncation(): void
    {
        config()->set('services.enquiry.submission_enabled', true);
        $payload = $this->payload([
            'name' => str_repeat('N', 50),
            'phone' => str_repeat('9', 20),
            'city' => str_repeat('C', 50),
        ]);

        $this->postJson(route('enquiry.store'), $payload)->assertOk();

        $row = DB::table('tbl_SaveContactForm7_8')->first();
        $this->assertSame($payload['name'], $row->first_name);
        $this->assertSame($payload['phone'], $row->your_phone);
        $this->assertSame($payload['city'], $row->city);
    }

    public function test_crm_column_limits_and_required_fields_return_422_without_insert(): void
    {
        config()->set('services.enquiry.submission_enabled', true);

        foreach (['name' => 51, 'phone' => 21, 'city' => 51, 'car' => 121, 'interest' => 121] as $field => $length) {
            $this->postJson(route('enquiry.store'), $this->payload([$field => str_repeat('A', $length)]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->postJson(route('enquiry.store'), $this->payload(['name' => '', 'phone' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);

        $this->assertSame(0, DB::table('tbl_SaveContactForm7_8')->count());
        Mail::assertNothingSent();
    }

    public function test_database_failure_returns_503_without_email_or_fallback(): void
    {
        config()->set('services.enquiry.submission_enabled', true);
        Schema::drop('tbl_SaveContactForm7_8');
        Log::spy();
        Mail::partialMock()->shouldNotReceive('raw');

        $this->postJson(route('enquiry.store'), $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertFalse(Schema::hasTable('enquiries'));
        Log::shouldHaveReceived('error')->once();
    }

    public function test_mail_failure_does_not_reverse_a_saved_enquiry(): void
    {
        config()->set('services.enquiry.submission_enabled', true);
        config()->set('services.enquiry.email_enabled', true);
        Mail::partialMock()->shouldReceive('raw')->once()->andThrow(new RuntimeException('SMTP failure'));
        Log::spy();

        $this->postJson(route('enquiry.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, DB::table('tbl_SaveContactForm7_8')->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_existing_throttle_still_limits_enquiry_requests(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('enquiry.store'), $this->payload())->assertStatus(503);
        }

        $this->postJson(route('enquiry.store'), $this->payload())->assertStatus(429);
        $this->assertSame(0, DB::table('tbl_SaveContactForm7_8')->count());
    }

    public function test_homepage_keeps_csrf_and_loads_the_single_enquiry_handler(): void
    {
        $this->assertContains('web', Route::getRoutes()->getByName('enquiry.store')->gatherMiddleware());
        $this->get(route('home'))->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('assets/js/modules/enquiry.js', false);
        $this->assertNull(Route::getRoutes()->getByName('enquiry.index'));
        $this->get('/admin/enquiries')->assertNotFound();
    }
}
