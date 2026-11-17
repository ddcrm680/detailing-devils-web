<?php

namespace App\Console\Commands;

use App\Models\LeadDelivery;
use App\Services\LeadDelivery\LeadSources;
use App\Services\LeadDelivery\WebhookDelivery;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class DeliverLeadsCommand extends Command
{
    private const SOURCE_REASONS = [
        'missing_submission_timestamp',
        'invalid_submission_timestamp',
        'invalid_lead_fields',
        'invalid_lead_encoding',
        'predates_activation',
    ];

    private const DELIVERY_REASONS = [
        'invalid_source_data',
        'retry_exhausted',
        'payload_unavailable',
        'request_failure',
        'authentication_rejected',
        'conflict',
        'validation_rejected',
        'unconfirmed_acknowledgement',
        'connection_failure',
        'temporary_http_failure',
        'retry_limit_reached',
    ];

    protected $signature = 'leads:deliver
        {--activate : Permanently exclude existing records; does not send leads}
        {--force : Explicitly confirm first activation without an interactive prompt}
        {--status : Show checkpoint and delivery counts without sending}
        {--limit=25 : Maximum discoveries per source and delivery attempts per run (1-100)}';

    protected $description = 'Discover new website leads and deliver them to the configured lead webhook';

    public function handle(LeadSources $sources, WebhookDelivery $webhook): int
    {
        $endpoint = config('lead_delivery.endpoint');

        if (
            ! $this->option('activate')
            && ! $this->option('status')
            && ($endpoint === null || (is_string($endpoint) && trim($endpoint) === ''))
        ) {
            $this->info('Webhook not configured; delivery skipped.');

            return self::SUCCESS;
        }

        if (
            ! $this->option('activate')
            && ! $this->option('status')
            && ! config('lead_delivery.enabled')
        ) {
            $this->info('Lead delivery is disabled.');

            return self::SUCCESS;
        }
        $lock = null;
        $mysql = false;
        $lockName = null;

        try {
            if ($this->option('activate') && $this->option('status')) {
                throw new DomainException('Choose activation or status, not both.');
            }
            if (! Schema::hasTable('lead_deliveries')) {
                throw new DomainException('Lead delivery setup is missing. No leads were sent.');
            }
            if ($this->option('status')) {
                $this->showStatus();

                return self::SUCCESS;
            }
            if (! config('lead_delivery.franchise_sources_confirmed')) {
                throw new DomainException('Confirm franchise record-type ownership before activating or delivering.');
            }
            $limit = filter_var(
                $this->option('limit'),
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                        'max_range' => 100,
                    ],
                ]
            );
            if ($limit === false) {
                throw new DomainException('Choose a limit between 1 and 100.');
            }
            $config = $this->option('activate') ? null : $webhook->contract();
            $lockName = 'lead-deliveries:'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 32);
            $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
            if ($mysql) {
                if ((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName], false)->acquired !== 1) {
                    $this->info('Another lead delivery run is active.');

                    return self::SUCCESS;
                }
                $lock = true;
            } else {
                $lock = Cache::lock($lockName, 120);
                if (! $lock->get()) {
                    $lock = null;
                    $this->info('Another lead delivery run is active.');

                    return self::SUCCESS;
                }
            }

            if ($this->option('activate')) {
                return $this->activate($sources);
            }

            $checkpoints = $this->checkpoints();
            $started = microtime(true);
            $discovered = 0;
            foreach ($checkpoints as $checkpoint) {
                foreach ($sources->undiscovered($checkpoint, $limit) as $row) {
                    if (microtime(true) - $started >= $config['run_seconds'] - $config['timeout']) {
                        break 2;
                    }
                    $reference = (string) Str::uuid();
                    $error = null;
                    $payload = null;
                    try {
                        $payload = $sources->payload($checkpoint->source, $row, $reference);
                        if (CarbonImmutable::parse($payload['submitted_at'])->lt($checkpoint->created_at->startOfSecond())) {
                            throw new InvalidArgumentException('predates_activation');
                        }
                    } catch (InvalidArgumentException $exception) {
                        $error = in_array($exception->getMessage(), self::SOURCE_REASONS, true)
                            ? $exception->getMessage() : 'invalid_source_data';
                        $payload = null;
                    }
                    LeadDelivery::query()->create([
                        'source' => $checkpoint->source,
                        'source_record_id' => $row->id,
                        'record_key' => $sources->table($checkpoint->source).':'.$row->id,
                        'reference' => $reference,
                        'status' => $error ? 'review' : 'pending',
                        'payload' => $payload,
                        'last_error' => $error,
                        'next_attempt_at' => $error ? null : now(),
                    ]);
                    $discovered++;
                }
            }

            $attempted = 0;
            $deliveries = LeadDelivery::query()
                ->whereIn('status', ['pending', 'processing'])
                ->where('source_record_id', '>', 0)
                ->where('next_attempt_at', '<=', now())
                ->orderBy('id')
                ->limit($limit)
                ->get();
            foreach ($deliveries as $delivery) {
                if (microtime(true) - $started >= $config['run_seconds'] - $config['timeout']) {
                    break;
                }
                if (
                    $mysql
                    && (int) DB::selectOne('SELECT IS_USED_LOCK(?) = CONNECTION_ID() AS owned', [$lockName], false)->owned !== 1
                ) {
                    throw new DomainException('Lead delivery lock was lost. Remaining deliveries were stopped.');
                }
                $webhook->send($delivery, $config);
                $attempted++;
                if (LeadDelivery::query()->whereKey($delivery->id)->value('last_error') === 'authentication_rejected') {
                    break;
                }
            }
            $this->info("Discovered {$discovered}; processed {$attempted}. Use --status to inspect delivery states.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Never print database exceptions, request URLs, response bodies or personal data.
            $this->error($exception instanceof DomainException
                ? $exception->getMessage() : 'Lead delivery stopped safely. Check configuration and storage availability.');

            return self::FAILURE;
        } finally {
            if ($lock !== null) {
                try {
                    $mysql ? DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName], false) : $lock->release();
                } catch (Throwable) {
                    // A disconnected MySQL session releases its advisory lock automatically.
                }
            }
        }
    }

    private function showStatus(): void
    {
        $this->table(
            ['State', 'Count'],
            LeadDelivery::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => [$row->status, $row->total])
                ->all()
        );

        $attention = LeadDelivery::query()
            ->where('source_record_id', '>', 0)
            ->where(function ($query): void {
                $query->where('status', 'review')
                    ->orWhere(fn ($query) => $query
                        ->whereIn('status', ['pending', 'processing'])
                        ->whereNotNull('last_error'))
                    ->orWhere(fn ($query) => $query
                        ->where('status', 'processing')
                        ->where('next_attempt_at', '<=', now()));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'reference', 'status', 'last_http_status', 'last_error', 'next_attempt_at']);

        if ($attention->isEmpty()) {
            $this->info('No deliveries need attention.');

            return;
        }
        $this->line('Recent deliveries needing attention (up to 10):');
        $this->table(
            ['Reference', 'HTTP', 'Reason', 'Next retry (UTC)'],
            $attention->map(function ($row): array {
                $reason = in_array($row->last_error, [...self::SOURCE_REASONS, ...self::DELIVERY_REASONS], true)
                    ? $row->last_error
                    : ($row->status === 'processing' && $row->last_error === null ? 'processing_expired' : 'needs_review');
                $httpStatus = filter_var($row->last_http_status, FILTER_VALIDATE_INT);

                return [
                    Str::isUuid($row->reference) ? $row->reference : '[invalid reference]',
                    $httpStatus >= 100 && $httpStatus <= 599 ? (string) $httpStatus : '-',
                    $reason,
                    $row->next_attempt_at?->utc()->toIso8601String() ?? '-',
                ];
            })->all()
        );
    }

    private function activate(LeadSources $sources): int
    {
        if (LeadDelivery::query()->exists()) {
            $this->checkpoints();
            $this->info('Lead delivery activation already exists. Checkpoints were not changed.');

            return self::SUCCESS;
        }
        foreach (array_keys(LeadSources::FORMS) as $source) {
            if (! Schema::hasColumns($sources->table($source), $sources->columns($source))) {
                throw new DomainException('A required lead source or column is missing. Activation stopped.');
            }
        }
        if (
            ! $this->option('force')
            && ! $this->confirm(
                'Exclude all existing leads permanently and activate discovery from this point?',
                false
            )
        ) {
            $this->warn('Activation cancelled. No checkpoints were written.');

            return self::FAILURE;
        }
        // The new table is transactional. No old lead rows are selected or modified.
        DB::transaction(function () use ($sources): void {
            $floors = [];
            foreach (array_keys(LeadSources::FORMS) as $source) {
                $table = $sources->table($source);
                $floors[$table] ??= (int) DB::table($table)->max('id');
                LeadDelivery::query()->create([
                    'source' => $source,
                    'source_record_id' => 0,
                    'checkpoint_id' => $floors[$table],
                    'record_key' => 'checkpoint:'.$source,
                    'reference' => (string) Str::uuid(),
                    'status' => 'checkpoint',
                ]);
            }
        });
        $this->info('Four permanent checkpoints created. No leads were sent.');

        return self::SUCCESS;
    }

    private function checkpoints(): iterable
    {
        $rows = LeadDelivery::query()->where('source_record_id', 0)->get()->keyBy('source');
        if ($rows->count() !== count(LeadSources::FORMS)) {
            throw new DomainException('All four original activation checkpoints are required. They will not be recreated automatically.');
        }
        foreach (array_keys(LeadSources::FORMS) as $source) {
            $row = $rows->get($source);
            if (
                ! $row
                || $row->status !== 'checkpoint'
                || $row->checkpoint_id === null
                || ! $row->created_at
            ) {
                throw new DomainException('Lead delivery checkpoints are incomplete. Restore the original checkpoints before delivery.');
            }
        }

        return $rows;
    }
}
