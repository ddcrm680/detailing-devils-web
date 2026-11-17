<?php

namespace Tests\Feature;

use App\Models\LeadDelivery;
use App\Services\LeadDelivery\LeadSources;
use App\Services\LeadDelivery\WebhookDelivery;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LeadWebhookDeliveryTest extends TestCase
{
    private ?Closure $response = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')
        ) {
            throw new RuntimeException('Lead delivery tests require isolated in-memory SQLite.');
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', 'Asia/Kolkata'));
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
        config()->set('cache.default', 'array');
        config()->set('lead_delivery', array_replace(config('lead_delivery'), [
            'enabled' => false,
            'contract_confirmed' => true,
            'franchise_sources_confirmed' => true,
            'endpoint' => 'https://receiver.example.test/webhook',
            'token' => 'synthetic-test-token',
            'ack_path' => 'stored',
            'ack_value' => true,
            'ack_reference_path' => 'reference',
        ]));
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->response
            ? ($this->response)($request)
            : Http::response(['stored' => true, 'reference' => $request['lead_reference']], 201));
        (require database_path('migrations/2026_11_17_000000_create_lead_deliveries_table.php'))->up();

        $sources = app(LeadSources::class);
        Schema::create('tbl_SaveContactForm7_8', function (Blueprint $table) use ($sources): void {
            $table->increments('id');
            foreach (array_diff($sources->columns('homepage'), ['id', 'created_on']) as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamp('created_on')->nullable();
            $table->text('dob')->nullable();
        });
        Schema::create('tbl_common_leads', function (Blueprint $table) use ($sources): void {
            $table->id();
            $columns = array_unique([
                ...$sources->columns('prime'),
                ...$sources->columns('fofo'),
                ...$sources->columns('xclusive'),
            ]);
            foreach (array_diff($columns, ['id', 'add_franchise_date']) as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamp('add_franchise_date')->nullable();
            foreach (['dob', 'image_franchise', 'civillitigation', 'net_worth', 'consent', 'otp_token'] as $column) {
                $table->text($column)->nullable();
            }
        });
    }

    private function lead(string $source = 'homepage', array $overrides = []): int
    {
        $data = $source === 'homepage' ? [
            'first_name' => 'Synthetic',
            'last_name' => 'Customer',
            'your_phone' => '+919876543210',
            'city' => 'Jaipur',
            'car_model' => 'Test Vehicle',
            'interested_in' => 'Ceramic Coating',
            'source' => 'website-home',
            'query_type' => 'service-enquiry',
            'created_on' => now()->toDateTimeString(),
        ] : [
            'name' => 'Synthetic Applicant',
            'phone' => '919876543210',
            'email' => 'applicant@example.test',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'country' => 'India',
            'record_type' => LeadSources::FORMS[$source][1],
            'add_franchise_date' => now()->toDateTimeString(),
        ];

        return DB::table(app(LeadSources::class)->table($source))->insertGetId(array_replace($data, $overrides));
    }

    private function activate(): void
    {
        $this->artisan('leads:deliver', ['--activate' => true, '--force' => true])->assertSuccessful();
    }

    private function runDelivery(int $limit = 25): void
    {
        config()->set('lead_delivery.enabled', true);
        $this->artisan('leads:deliver', ['--limit' => $limit])->assertSuccessful();
    }

    private function delivery(): LeadDelivery
    {
        return LeadDelivery::query()->where('source_record_id', '>', 0)->firstOrFail();
    }

    public function test_disabled_command_does_not_even_require_delivery_storage(): void
    {
        Schema::drop('lead_deliveries');
        $this->artisan('leads:deliver')->expectsOutput('Lead delivery is disabled.')->assertSuccessful();
        Http::assertNothingSent();
    }

    #[DataProvider('missingWebhookUrls')]
    public function test_missing_url_is_a_successful_skip_without_any_database_access(?string $url, bool $enabled): void
    {
        Schema::drop('lead_deliveries');
        config()->set('lead_delivery.endpoint', $url);
        config()->set('lead_delivery.enabled', $enabled);
        config()->set('lead_delivery.contract_confirmed', false);
        config()->set('lead_delivery.franchise_sources_confirmed', false);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->artisan('leads:deliver')->expectsOutput('Webhook not configured; delivery skipped.')->assertSuccessful();
        $this->assertSame([], DB::getQueryLog());
        Http::assertNothingSent();
    }

    public static function missingWebhookUrls(): array
    {
        return [[null, true], ['', true], [" \t ", true], [null, false], ['', false], [" \t ", false]];
    }

    public function test_missing_url_does_not_discover_or_retry_and_preserves_all_records(): void
    {
        $this->activate();
        $this->lead();
        $this->response = fn () => Http::response([], 503);
        $this->runDelivery();
        $this->lead();
        $this->travel(2)->minutes();
        $before = DB::table('lead_deliveries')->get()->toJson();
        config()->set('lead_delivery.endpoint', null);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->artisan('leads:deliver')->expectsOutput('Webhook not configured; delivery skipped.')->assertSuccessful();
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($before, DB::table('lead_deliveries')->get()->toJson());
        Http::assertSentCount(1);
    }

    public function test_activation_and_status_work_without_a_url_or_webhook_credentials(): void
    {
        config()->set('lead_delivery.endpoint', null);
        config()->set('lead_delivery.token', null);
        config()->set('lead_delivery.contract_confirmed', false);
        $this->activate();
        $before = DB::table('lead_deliveries')->get()->toJson();
        config()->set('lead_delivery.franchise_sources_confirmed', false);
        $this->artisan('leads:deliver', ['--status' => true])
            ->expectsOutput('No deliveries need attention.')->assertSuccessful();
        $this->assertSame($before, DB::table('lead_deliveries')->get()->toJson());
        Http::assertNothingSent();
    }

    #[DataProvider('configurationErrorMessages')]
    public function test_invalid_configured_webhooks_still_report_clear_errors(string $key, mixed $value, string $message): void
    {
        $this->activate();
        $this->lead();
        config()->set('lead_delivery.enabled', true);
        config()->set('lead_delivery.'.$key, $value);
        $this->artisan('leads:deliver')->expectsOutput($message)->assertFailed();
        $this->assertSame(4, LeadDelivery::count());
        Http::assertNothingSent();
    }

    public static function configurationErrorMessages(): array
    {
        return [
            [
                'endpoint',
                'invalid-url',
                'Configure and confirm the HTTPS webhook, acknowledgement and idempotency contract first.',
            ],
            ['token', null, 'Configure valid webhook authentication and reference headers.'],
            ['ack_path', '', 'Configure valid webhook acknowledgement, timeout and retry settings.'],
        ];
    }

    public function test_activation_excludes_all_existing_ids_and_never_sends_or_resets(): void
    {
        $this->lead('homepage', ['id' => 100000]);
        $this->lead('prime', ['id' => 100000]);
        $this->activate();
        $before = LeadDelivery::all()->toArray();
        $this->assertSame([100000, 100000, 100000, 100000], LeadDelivery::query()->pluck('checkpoint_id')->all());
        $this->lead('fofo');
        $this->activate();
        $this->assertSame($before, LeadDelivery::all()->toArray());
        Http::assertNothingSent();
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->assertSame('fofo', $this->delivery()->source);
    }

    public function test_initial_activation_requires_explicit_confirmation(): void
    {
        $this->artisan('leads:deliver', ['--activate' => true])
            ->expectsConfirmation('Exclude all existing leads permanently and activate discovery from this point?', 'no')
            ->assertFailed();
        $this->assertSame(0, LeadDelivery::count());
        Http::assertNothingSent();
    }

    public function test_missing_or_partial_checkpoints_are_never_recreated_by_cron(): void
    {
        config()->set('lead_delivery.enabled', true);
        $this->artisan('leads:deliver')->assertFailed();
        $this->assertSame(0, LeadDelivery::count());
        $this->activate();
        DB::table('lead_deliveries')->where('source', 'prime')->delete();
        $this->artisan('leads:deliver')->assertFailed();
        $this->artisan('leads:deliver', ['--activate' => true, '--force' => true])->assertFailed();
        $this->assertSame(3, LeadDelivery::count());
        Http::assertNothingSent();
    }

    public function test_checkpoint_changes_and_deletion_are_rejected(): void
    {
        $this->activate();
        $checkpoint = LeadDelivery::firstOrFail();
        try {
            $checkpoint->update(['checkpoint_id' => 1]);
            $this->fail('Checkpoint update was allowed.');
        } catch (LogicException) {
            $this->assertSame(0, $checkpoint->fresh()->checkpoint_id);
        }
        $this->expectException(LogicException::class);
        $checkpoint->delete();
    }

    public function test_activation_failure_rolls_back_all_four_checkpoints(): void
    {
        DB::unprepared(
            "CREATE TRIGGER reject_checkpoint BEFORE INSERT ON lead_deliveries WHEN NEW.source = 'prime' BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END;"
        );
        $this->artisan('leads:deliver', ['--activate' => true, '--force' => true])->assertFailed();
        $this->assertSame(0, LeadDelivery::count());
        Http::assertNothingSent();
    }

    public function test_all_four_forms_have_distinct_safe_mappings_and_do_not_change_source_rows(): void
    {
        $this->activate();
        $this->lead('homepage', ['your_email' => 'customer@example.test']);
        $this->lead('prime', [
            'newbussiness' => 'Existing Company',
            'experience' => 'yes',
            'bussinessowner' => 'self',
            'territory_city' => 'Ajmer',
            'territory' => 'North',
            'spacearound' => '2500',
            'existingfacility' => 'yes',
            'bussiness_description' => 'Build a detailing business',
            'profession' => 'Entrepreneur',
            'dob' => '1990-01-01',
            'image_franchise' => 'private.jpg',
            'otp_token' => 'private-token',
        ]);
        $this->lead('fofo', [
            'newbussiness' => 'Open a studio',
            'bussinessowner' => 'YES',
            'whichcityfranchise' => 'Ajmer',
        ]);
        $this->lead('xclusive', ['newbussiness' => 'Premium studio', 'spacearound' => 'NO']);
        $before = [
            DB::table('tbl_SaveContactForm7_8')->get()->toJson(),
            DB::table('tbl_common_leads')->get()->toJson(),
        ];
        $this->runDelivery();
        Http::assertSentCount(4);
        $this->assertSame(4, LeadDelivery::query()->where('status', 'delivered')->count());
        $enquiry = LeadDelivery::query()
            ->where('source', 'homepage')
            ->where('source_record_id', '>', 0)
            ->firstOrFail();
        $this->assertSame('Enquiry', $enquiry->payload['form']['name']);
        $this->assertSame('service-enquiry', $enquiry->payload['form']['type']);
        Http::assertSent(fn (Request $request) => $request['form']['type'] === 'prime-franchise'
            && $request['enquiry']['details']['decision_maker'] === 'self'
            && $request['enquiry']['details']['business_name'] === 'Existing Company'
            && $request['enquiry']['details']['carpet_area'] === '2500'
            && $request['customer']['city'] === 'Jaipur'
            && $request['enquiry']['details']['preferred_city'] === 'Ajmer');
        Http::assertSent(fn (Request $request) => $request['form']['type'] === 'FOFO'
            && $request['enquiry']['details']['business_plan'] === 'Open a studio'
            && $request['enquiry']['details']['owns_business'] === 'YES');
        Http::assertSent(fn (Request $request) => $request['form']['type'] === 'xclusive'
            && $request['enquiry']['franchise_model'] === 'X-Clusive');
        foreach (LeadDelivery::query()->where('status', 'delivered')->get() as $delivery) {
            $json = json_encode($delivery->payload);
            foreach ([
                'dob',
                'image_franchise',
                'otp_token',
                'private.jpg',
                'private-token',
                'civillitigation',
                'net_worth',
            ] as $excluded) {
                $this->assertStringNotContainsString($excluded, $json);
            }
        }
        $this->assertSame($before, [
            DB::table('tbl_SaveContactForm7_8')->get()->toJson(),
            DB::table('tbl_common_leads')->get()->toJson(),
        ]);
    }

    public function test_real_timestamp_is_used_and_missing_optional_fields_are_not_invented(): void
    {
        $this->activate();
        $this->travel(10)->seconds();
        $this->lead('homepage', ['created_on' => '2026-11-17 12:00:05', 'city' => null]);
        $this->runDelivery();
        $payload = $this->delivery()->payload;
        $this->assertSame('2026-11-17T06:30:05+00:00', $payload['submitted_at']);
        $this->assertNull($payload['customer']['email']);
        $this->assertNull($payload['customer']['city']);
        $this->assertArrayNotHasKey('studio', $payload);
    }

    public function test_nonwebsite_markers_and_unknown_franchise_types_are_excluded(): void
    {
        $this->activate();
        $this->lead('homepage', ['source' => 'other-site']);
        $this->lead('homepage', ['query_type' => 'warranty']);
        $this->lead('fofo', ['record_type' => 'legacy']);
        $this->lead('prime', ['record_type' => 'PRIME-FRANCHISE']);
        $this->runDelivery();
        $this->assertSame(4, LeadDelivery::count());
        Http::assertNothingSent();
    }

    public function test_repeated_discovery_never_duplicates_deliveries_or_resends_success(): void
    {
        $this->activate();
        $this->lead();
        $this->runDelivery();
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->assertSame(1, LeadDelivery::query()->where('source_record_id', '>', 0)->count());
    }

    public function test_reclassifying_a_franchise_row_does_not_create_another_delivery(): void
    {
        $this->activate();
        $id = $this->lead('prime');
        $this->runDelivery();
        DB::table('tbl_common_leads')->where('id', $id)->update(['record_type' => 'FOFO']);
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->assertSame(1, LeadDelivery::query()->where('source_record_id', '>', 0)->count());
        $this->assertSame('prime', $this->delivery()->source);
    }

    public function test_late_committing_lower_ids_above_the_fixed_checkpoint_are_still_discovered(): void
    {
        $this->lead('homepage', ['id' => 100]);
        $this->activate();
        $this->lead('homepage', ['id' => 103]);
        $this->runDelivery();
        $this->lead('homepage', ['id' => 102]);
        $this->runDelivery();
        Http::assertSentCount(2);
        $this->assertSame(100, LeadDelivery::query()->where('source', 'homepage')->where('source_record_id', 0)->value('checkpoint_id'));
    }

    #[DataProvider('invalidLeadData')]
    public function test_invalid_or_historical_imported_rows_are_retained_without_sending(array $changes, string $reason): void
    {
        $this->activate();
        $this->lead('homepage', $changes);
        $this->runDelivery();
        $this->assertSame('review', $this->delivery()->status);
        $this->assertSame($reason, $this->delivery()->last_error);
        $this->assertNull($this->delivery()->payload);
        Artisan::call('leads:deliver', ['--status' => true]);
        $this->assertStringContainsString($reason, Artisan::output());
        $this->assertStringNotContainsString('private@example.test', Artisan::output());
        Http::assertNothingSent();
    }

    public static function invalidLeadData(): array
    {
        return [
            'missing mobile' => [['your_phone' => null], 'invalid_lead_fields'],
            'invalid email' => [['your_email' => 'invalid'], 'invalid_lead_fields'],
            'missing time' => [['created_on' => null], 'missing_submission_timestamp'],
            'future time' => [['created_on' => '2027-01-01 00:00:00'], 'invalid_submission_timestamp'],
            'imported historical time' => [['created_on' => '2020-01-01 00:00:00'], 'predates_activation'],
            'invalid encoding' => [['car_model' => "\xC3\x28"], 'invalid_lead_encoding'],
            'untrusted parser message' => [
                ['created_on' => 'invalid time private@example.test'],
                'invalid_source_data',
            ],
        ];
    }

    public function test_payload_is_encrypted_and_hidden_from_model_serialization(): void
    {
        $this->activate();
        $this->lead();
        $this->runDelivery();
        $raw = DB::table('lead_deliveries')->where('source_record_id', '>', 0)->value('payload');
        $this->assertStringNotContainsString('Synthetic Customer', $raw);
        $this->assertStringNotContainsString('9876543210', $raw);
        $this->assertArrayNotHasKey('payload', $this->delivery()->toArray());
    }

    #[DataProvider('reviewResponses')]
    public function test_unconfirmed_and_permanent_responses_require_review(int $status, mixed $body, string $error): void
    {
        $this->activate();
        $this->lead();
        $this->response = fn () => Http::response($body, $status);
        $this->runDelivery();
        $this->assertSame('review', $this->delivery()->status);
        $this->assertSame($error, $this->delivery()->last_error);
        $this->runDelivery();
        Http::assertSentCount(1);
    }

    public static function reviewResponses(): array
    {
        return [
            'unauthorized' => [401, ['error' => 'private response'], 'authentication_rejected'],
            'forbidden' => [403, [], 'authentication_rejected'],
            'duplicate' => [409, ['duplicate' => true], 'conflict'],
            'invalid' => [422, ['errors' => ['phone' => 'private number']], 'validation_rejected'],
            'empty 200' => [200, [], 'unconfirmed_acknowledgement'],
            'HTML 200' => [200, '<html>Login</html>', 'unconfirmed_acknowledgement'],
            'malformed JSON' => [200, '{broken', 'unconfirmed_acknowledgement'],
            'false ack' => [200, ['stored' => false], 'unconfirmed_acknowledgement'],
            'wrong reference' => [
                200,
                [
                    'stored' => true,
                    'reference' => 'another-lead',
                ],
                'unconfirmed_acknowledgement',
            ],
            'redirect' => [302, [], 'unconfirmed_acknowledgement'],
            '202 without agreed contract' => [202, ['stored' => true], 'unconfirmed_acknowledgement'],
        ];
    }

    public function test_safe_retry_keeps_the_same_reference_payload_and_custom_authentication(): void
    {
        $this->activate();
        $id = $this->lead();
        config()->set('lead_delivery.auth_header', 'X-Webhook-Token');
        config()->set('lead_delivery.auth_scheme', '');
        $attempt = 0;
        $this->response = function (Request $request) use (&$attempt) {
            return ++$attempt === 1 ? Http::response([], 503)
                : Http::response(['stored' => true, 'reference' => $request['lead_reference']], 201);
        };
        $this->runDelivery();
        $first = $this->delivery();
        $this->assertSame('pending', $first->status);
        $this->assertGreaterThanOrEqual(now()->addSeconds(60)->timestamp, $first->next_attempt_at->timestamp);
        DB::table('tbl_SaveContactForm7_8')->where('id', $id)->update(['first_name' => 'Changed after discovery']);
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->travel(2)->minutes();
        $this->runDelivery();
        $this->assertSame('delivered', $first->fresh()->status);
        $this->assertSame(2, $first->fresh()->attempts);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame($first->payload, $request->data());
            $this->assertTrue($request->hasHeader('Idempotency-Key', $first->reference));
            $this->assertTrue($request->hasHeader('X-Webhook-Token', 'synthetic-test-token'));
            $this->assertFalse($request->hasHeader('Authorization'));
        }
    }

    public function test_connection_timeout_is_retryable_without_logging_personal_data(): void
    {
        $this->activate();
        $this->lead();
        $this->response = Http::failedConnection();
        $this->runDelivery();
        $this->assertSame('pending', $this->delivery()->status);
        $this->assertSame('connection_failure', $this->delivery()->last_error);
    }

    public function test_retry_after_is_respected_and_exhaustion_is_retained_for_review(): void
    {
        $this->activate();
        $this->lead();
        config()->set('lead_delivery.max_attempts', 2);
        $this->response = fn () => Http::response([], 429, ['Retry-After' => '7200']);
        $this->runDelivery();
        $this->assertSame(now()->addHours(2)->timestamp, $this->delivery()->next_attempt_at->timestamp);
        $this->travel(3)->hours();
        $this->runDelivery();
        $this->assertSame('review', $this->delivery()->status);
        $this->assertSame(2, $this->delivery()->attempts);
        $this->assertNull($this->delivery()->next_attempt_at);
    }

    public function test_retry_after_http_date_and_overlong_wait_are_handled_safely(): void
    {
        $this->activate();
        $this->lead();
        $this->response = fn () => Http::response([], 429, ['Retry-After' => now()->addHours(4)->toRfc7231String()]);
        $this->runDelivery();
        $this->assertSame(now()->addHours(4)->timestamp, $this->delivery()->next_attempt_at->timestamp);
        $this->travel(5)->hours();
        $this->response = fn () => Http::response([], 429, ['Retry-After' => '999999999']);
        $this->runDelivery();
        $this->assertSame('review', $this->delivery()->status);
        $this->assertNull($this->delivery()->next_attempt_at);
    }

    public function test_authentication_failure_stops_the_current_batch(): void
    {
        $this->activate();
        $this->lead();
        $this->lead();
        $this->response = fn () => Http::response([], 401);
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->assertSame(1, LeadDelivery::query()->where('status', 'pending')->count());
    }

    #[DataProvider('acknowledgementTypes')]
    public function test_documented_typed_acknowledgements_are_configurable(string $type, mixed $value, mixed $response): void
    {
        $this->activate();
        $this->lead();
        config()->set('lead_delivery.ack_path', 'result.status');
        config()->set('lead_delivery.ack_type', $type);
        config()->set('lead_delivery.ack_value', $value);
        $this->response = fn (Request $request) => Http::response([
            'result' => ['status' => $response],
            'reference' => $request['lead_reference'],
        ], 200);
        $this->runDelivery();
        $this->assertSame('delivered', $this->delivery()->status);
    }

    public static function acknowledgementTypes(): array
    {
        return [['boolean', true, true], ['string', 'stored', 'stored'], ['integer', '0', 0]];
    }

    public function test_stale_processing_token_cannot_overwrite_a_newer_claim(): void
    {
        $this->activate();
        $this->lead();
        $this->response = function (Request $request) {
            DB::table('lead_deliveries')->where('source_record_id', '>', 0)->update(['processing_token' => 'newer-owner']);

            return Http::response(['stored' => true, 'reference' => $request['lead_reference']], 201);
        };
        $this->runDelivery();
        $this->assertSame('processing', $this->delivery()->status);
        $this->assertSame('newer-owner', $this->delivery()->processing_token);
        $this->assertNull($this->delivery()->delivered_at);
    }

    public function test_stale_attempt_snapshot_cannot_claim_a_newer_delivery(): void
    {
        $this->activate();
        $this->lead();
        $this->response = fn () => Http::response([], 503);
        $this->runDelivery();
        $stale = $this->delivery();
        DB::table('lead_deliveries')->where('source_record_id', '>', 0)->update([
            'attempts' => 2,
            'next_attempt_at' => now(),
        ]);
        $webhook = app(WebhookDelivery::class);
        $webhook->send($stale, $webhook->contract());
        Http::assertSentCount(1);
        $this->assertSame(2, $stale->fresh()->attempts);
    }

    public function test_stale_snapshot_cannot_move_a_delivered_record_to_review(): void
    {
        $this->activate();
        $this->lead();
        $this->runDelivery();
        $snapshot = $this->delivery();
        $webhook = app(WebhookDelivery::class);
        $snapshot->attempts = config('lead_delivery.max_attempts');
        $webhook->send($snapshot, $webhook->contract());
        $this->assertSame('delivered', $snapshot->fresh()->status);
        $snapshot->attempts = 1;
        $snapshot->payload = null;
        $webhook->send($snapshot, $webhook->contract());
        $this->assertSame('delivered', $snapshot->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_interrupted_processing_is_recoverable_only_after_its_lease(): void
    {
        $this->activate();
        $this->lead();
        $this->response = fn () => Http::response([], 503);
        $this->runDelivery();
        $delivery = $this->delivery();
        $delivery->update([
            'status' => 'processing',
            'processing_token' => 'old-process',
            'next_attempt_at' => now()->addMinutes(2),
        ]);
        $this->runDelivery();
        Http::assertSentCount(1);
        $this->travel(3)->minutes();
        $this->runDelivery();
        Http::assertSentCount(2);
        $this->assertSame($delivery->reference, $delivery->fresh()->reference);
    }

    public function test_existing_lock_prevents_activation_and_delivery_overlap(): void
    {
        $name = 'lead-deliveries:'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 32);
        $lock = Cache::lock($name, 120);
        $this->assertTrue($lock->get());
        $this->artisan('leads:deliver', ['--activate' => true, '--force' => true])
            ->expectsOutput('Another lead delivery run is active.')->assertSuccessful();
        $this->assertSame(0, LeadDelivery::count());
        $lock->release();
        $this->activate();
        $this->lead();
        $lock = Cache::lock($name, 120);
        $lock->get();
        $this->runDelivery();
        Http::assertNothingSent();
        $lock->release();
    }

    public function test_source_reference_constraint_rejects_duplicate_rows(): void
    {
        $this->activate();
        $this->lead();
        $this->runDelivery();
        $row = $this->delivery();
        $this->expectException(UniqueConstraintViolationException::class);
        LeadDelivery::create([
            'source' => 'fofo',
            'source_record_id' => $row->source_record_id,
            'record_key' => $row->record_key,
            'reference' => 'another-reference',
            'status' => 'pending',
        ]);
    }

    public function test_logs_and_command_output_do_not_disclose_payloads_tokens_or_response_bodies(): void
    {
        $this->activate();
        $this->lead();
        Log::spy();
        $this->response = fn () => Http::response(['error' => 'private provider body'], 422);
        config()->set('lead_delivery.enabled', true);
        Artisan::call('leads:deliver');
        $output = Artisan::output();
        foreach (['Synthetic', '9876543210', 'synthetic-test-token', 'private provider body'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        Log::shouldHaveReceived('warning')->once()->with('Lead delivery needs review.', [
            'reference' => $this->delivery()->reference,
            'reason' => 'validation_rejected',
        ]);
    }

    #[DataProvider('unsafeConfiguration')]
    public function test_unsafe_or_unconfirmed_configuration_never_sends(string $key, mixed $value): void
    {
        $this->activate();
        $this->lead();
        config()->set('lead_delivery.enabled', true);
        config()->set('lead_delivery.'.$key, $value);
        $this->artisan('leads:deliver')->assertFailed();
        Http::assertNothingSent();
        $this->assertSame(4, LeadDelivery::count());
    }

    public static function unsafeConfiguration(): array
    {
        return [
            ['endpoint', 'http://receiver.example.test/webhook'],
            ['endpoint', 'https://user:secret@receiver.example.test/webhook'],
            ['contract_confirmed', false],
            ['franchise_sources_confirmed', false],
            ['token', null],
            ['auth_header', "X-Test\r\nInjected"],
            ['auth_header', 'Host'],
            ['reference_header', 'Authorization'],
            ['ack_value', null],
            ['ack_path', ''],
            ['ack_statuses', [200, 409]],
            ['timeout', 60],
            ['max_attempts', 0],
            ['retry_delays', [0]],
        ];
    }

    public function test_run_limit_bounds_delivery_requests_and_status_is_read_only(): void
    {
        $this->activate();
        $this->lead();
        $this->lead();
        $this->runDelivery(1);
        Http::assertSentCount(1);
        $before = DB::table('lead_deliveries')->get()->toJson();
        $this->artisan('leads:deliver', ['--status' => true])->assertSuccessful();
        $this->assertSame($before, DB::table('lead_deliveries')->get()->toJson());
    }

    private function statusFixture(int $id, string $status, array $changes = []): LeadDelivery
    {
        return LeadDelivery::create(array_replace([
            'source' => 'homepage',
            'source_record_id' => $id,
            'record_key' => 'tbl_SaveContactForm7_8:'.$id,
            'reference' => (string) Str::uuid(),
            'status' => $status,
            'payload' => ['customer' => ['name' => 'Private Customer', 'email' => 'private@example.test']],
        ], $changes));
    }

    public function test_status_lists_review_retries_and_expired_processing_without_payloads_or_writes(): void
    {
        $this->activate();
        $review = $this->statusFixture(1, 'review', ['last_http_status' => 422, 'last_error' => 'validation_rejected']);
        $retry = $this->statusFixture(2, 'pending', [
            'last_http_status' => 503,
            'last_error' => 'temporary_http_failure',
            'next_attempt_at' => now()->addMinutes(15),
        ]);
        $expired = $this->statusFixture(3, 'processing', ['next_attempt_at' => now()->subMinute()]);
        $delivered = $this->statusFixture(4, 'delivered');
        $cleanPending = $this->statusFixture(5, 'pending', ['next_attempt_at' => now()]);
        $activeProcessing = $this->statusFixture(6, 'processing', ['next_attempt_at' => now()->addMinutes(2)]);
        // Even unreadable ciphertext must not affect the metadata-only status command.
        DB::table('lead_deliveries')->where('source_record_id', '>', 0)->update(['payload' => 'not-decryptable']);
        $before = DB::table('lead_deliveries')->get()->toJson();
        config()->set('lead_delivery.endpoint', null);
        config()->set('lead_delivery.token', null);
        Artisan::call('leads:deliver', ['--status' => true]);
        $output = Artisan::output();
        foreach ([
            'State',
            'Count',
            'checkpoint',
            'delivered',
            'review',
            'pending',
            'processing',
            'Reference',
            'HTTP',
            'Reason',
            'Next retry (UTC)',
            $review->reference,
            $retry->reference,
            $expired->reference,
            'validation_rejected',
            'temporary_http_failure',
            'processing_expired',
            '422',
            '503',
            '2026-11-17T06:45:00+00:00',
        ] as $expected) {
            $this->assertStringContainsString($expected, $output);
        }
        foreach ([
            $delivered->reference,
            $cleanPending->reference,
            $activeProcessing->reference,
            'Private Customer',
            'private@example.test',
            'not-decryptable',
            'synthetic-test-token',
        ] as $excluded) {
            $this->assertStringNotContainsString($excluded, $output);
        }
        $this->assertSame($before, DB::table('lead_deliveries')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_status_limits_attention_to_ten_most_recent_entries(): void
    {
        $this->activate();
        $rows = [];
        for ($id = 1; $id <= 12; $id++) {
            $rows[] = $this->statusFixture($id, 'review', ['last_error' => 'invalid_lead_fields']);
        }
        Artisan::call('leads:deliver', ['--status' => true]);
        $output = Artisan::output();
        $this->assertStringNotContainsString($rows[0]->reference, $output);
        $this->assertStringNotContainsString($rows[1]->reference, $output);
        foreach (array_slice($rows, 2) as $row) {
            $this->assertStringContainsString($row->reference, $output);
        }
        $this->assertLessThan(strpos($output, $rows[2]->reference), strpos($output, $rows[11]->reference));
        Http::assertNothingSent();
    }

    public function test_status_redacts_unrecognized_error_text_and_invalid_references(): void
    {
        $this->activate();
        $row = $this->statusFixture(1, 'review', [
            'reference' => 'private@example.test',
            'last_error' => 'Private Customer synthetic-test-token',
        ]);
        DB::table('lead_deliveries')->where('id', $row->id)->update(['last_http_status' => '400 private@example.test']);
        Artisan::call('leads:deliver', ['--status' => true]);
        $output = Artisan::output();
        $this->assertStringContainsString('[invalid reference]', $output);
        $this->assertStringContainsString('needs_review', $output);
        foreach (['Private Customer', 'private@example.test', 'synthetic-test-token'] as $private) {
            $this->assertStringNotContainsString($private, $output);
        }
        Http::assertNothingSent();
    }

    public function test_guide_prioritizes_operations_and_preserves_four_valid_payload_examples(): void
    {
        $guide = file_get_contents(base_path('docs/LEAD_WEBHOOK_DELIVERY.md'));
        $this->assertStringStartsWith('# Lead Webhook Delivery', $guide);
        $this->assertStringContainsString('config/lead_delivery.php', $guide);
        $this->assertStringContainsString('LEAD_DELIVERY_ENABLED=false', $guide);
        $previous = -1;
        foreach ([
            '## Setup',
            '### Required configuration',
            '### Optional settings',
            '## Activation',
            '## Cron',
            '## Manual commands',
            '## Troubleshooting',
            '## Reference: source identities',
        ] as $heading) {
            $position = strpos($guide, $heading);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position);
            $previous = $position;
        }
        foreach ([
            'php artisan leads:deliver --activate',
            'php artisan leads:deliver --status',
            '/APP_ROOT/artisan leads:deliver --limit=25',
            'Webhook not configured; delivery skipped.',
            'permanently exclude all existing IDs',
            'No connected migration',
        ] as $instruction) {
            $this->assertStringContainsString($instruction, $guide);
        }
        preg_match_all('/```json\s*(.*?)```/s', $guide, $examples);
        $this->assertCount(4, $examples[1]);
        $types = [];
        $names = [];
        foreach ($examples[1] as $example) {
            $payload = json_decode($example, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('1.0', $payload['schema_version']);
            $this->assertTrue(Str::isUuid($payload['lead_reference']));
            $this->assertArrayHasKey('customer', $payload);
            $this->assertArrayHasKey('enquiry', $payload);
            $types[] = $payload['form']['type'];
            $names[] = $payload['form']['name'];
        }
        $this->assertSame(['service-enquiry', 'prime-franchise', 'FOFO', 'xclusive'], $types);
        $this->assertSame(['Enquiry', 'Prime Franchise Application', 'FOFO Franchise Application', 'X-Clusive Franchise Application'], $names);
    }

    public function test_checkpoint_and_delivery_history_are_not_dropped_by_rollback(): void
    {
        $this->expectException(RuntimeException::class);
        (require database_path('migrations/2026_11_17_000000_create_lead_deliveries_table.php'))->down();
    }
}
