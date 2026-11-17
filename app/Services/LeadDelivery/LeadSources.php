<?php

namespace App\Services\LeadDelivery;

use App\Models\LeadDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;
use stdClass;

class LeadSources
{
    public const FORMS = [
        'homepage' => ['Enquiry', 'service-enquiry'],
        'prime' => ['Prime Franchise Application', 'prime-franchise'],
        'fofo' => ['FOFO Franchise Application', 'FOFO'],
        'xclusive' => ['X-Clusive Franchise Application', 'xclusive'],
    ];

    private const CONTACT = ['name', 'phone', 'email', 'city', 'state', 'country'];

    private const PRIME = [
        'profession' => 'occupation',
        'experience' => 'business_experience',
        'newbussiness' => 'business_name',
        'type_of_bussiness' => 'industry',
        'existingfacility' => 'has_property',
        'spacearound' => 'carpet_area',
        'territory_city' => 'preferred_city',
        'territory' => 'preferred_area',
        'wantstart' => 'start_timeline',
        'bussinessowner' => 'decision_maker',
        'contacted' => 'contact_methods',
        'bussiness_description' => 'partnership_reason',
    ];

    private const FRANCHISE = [
        'profession' => 'profession',
        'hearabout' => 'heard_about_us',
        'whichcityfranchise' => 'preferred_city',
        'wantstart' => 'start_timeline',
        'newbussiness' => 'business_plan',
        'bussinessowner' => 'owns_business',
        'type_of_bussiness' => 'business_type',
        'product_service' => 'products_or_services',
        'bussiness_description' => 'business_description',
        'experienceautomotive' => 'has_automotive_experience',
        'experience' => 'automotive_experience',
        'existingautomotive' => 'has_automotive_facility',
        'existingfacility' => 'automotive_facility',
        'spacearound' => 'has_property',
        'contacted' => 'previous_contact',
    ];

    public function table(string $source): string
    {
        if (! isset(self::FORMS[$source])) {
            throw new InvalidArgumentException('Unknown lead delivery source.');
        }

        return $source === 'homepage' ? 'tbl_SaveContactForm7_8' : 'tbl_common_leads';
    }

    public function columns(string $source): array
    {
        return $source === 'homepage'
            ? [
                'id',
                'first_name',
                'last_name',
                'your_phone',
                'your_email',
                'city',
                'car_model',
                'interested_in',
                'source',
                'query_type',
                'created_on',
            ]
            : array_values(array_unique([
                'id',
                ...self::CONTACT,
                'record_type',
                'add_franchise_date',
                ...array_keys($source === 'prime' ? self::PRIME : self::FRANCHISE),
            ]));
    }

    public function records(string $source): Builder
    {
        $query = DB::table($this->table($source).' as leads');
        $filters = $source === 'homepage'
            ? ['source' => 'website-home', 'query_type' => 'service-enquiry']
            : ['record_type' => self::FORMS[$source][1]];

        foreach ($filters as $field => $value) {
            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $query->whereRaw("BINARY leads.{$field} = ?", [$value]);
            } else {
                $query->where('leads.'.$field, $value);
            }
        }

        return $query;
    }

    public function undiscovered(LeadDelivery $checkpoint, int $limit): iterable
    {
        $source = $checkpoint->source;
        $query = $this->records($source)
            ->where('leads.id', '>', $checkpoint->checkpoint_id)
            ->whereNotExists(function (Builder $query) use ($source): void {
                $query->selectRaw('1')
                    ->from('lead_deliveries')
                    ->whereIn('source', $source === 'homepage' ? ['homepage'] : ['prime', 'fofo', 'xclusive'])
                    ->whereColumn('source_record_id', 'leads.id');
            })
            ->orderBy('leads.id')
            ->limit($limit)
            ->select(array_map(fn ($column) => 'leads.'.$column, $this->columns($source)));

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $timestamp = $source === 'homepage' ? 'created_on' : 'add_franchise_date';
            // MySQL TIMESTAMP is interpreted by its actual connection time zone.
            $query->selectRaw("UNIX_TIMESTAMP(leads.{$timestamp}) as submitted_unix");
        }

        return $query->get();
    }

    public function payload(string $source, stdClass $row, string $reference): array
    {
        $home = $source === 'homepage';
        $timestamp = $home ? ($row->created_on ?? null) : ($row->add_franchise_date ?? null);
        if (empty($timestamp) || str_starts_with($timestamp, '0000-')) {
            throw new InvalidArgumentException('missing_submission_timestamp');
        }
        $submitted = property_exists($row, 'submitted_unix')
            ? CarbonImmutable::createFromTimestampUTC((int) $row->submitted_unix)
            : CarbonImmutable::parse($timestamp, config('app.timezone'));
        if ($submitted->year < 2000 || $submitted->isFuture()) {
            throw new InvalidArgumentException('invalid_submission_timestamp');
        }

        $customer = [
            'name' => $home ? trim(($row->first_name ?? '').' '.($row->last_name ?? '')) : trim($row->name ?? ''),
            // Preserve the saved number; never guess a country prefix.
            'mobile' => trim(($home ? $row->your_phone : $row->phone) ?? ''),
            'email' => $this->text(($home ? $row->your_email : $row->email) ?? null),
            'city' => $this->text($row->city ?? null),
        ];
        if (Validator::make($customer, [
            'name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:32', 'regex:/\A\+?[0-9][0-9().\s-]{5,30}\z/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'city' => ['nullable', 'string', 'max:255'],
        ])->fails()) {
            throw new InvalidArgumentException('invalid_lead_fields');
        }

        $details = [];
        if ($home) {
            $details['vehicle'] = $this->text($row->car_model ?? null);
        } else {
            foreach ($source === 'prime' ? self::PRIME : self::FRANCHISE as $column => $field) {
                $details[$field] = $this->text($row->{$column} ?? null);
            }
            $customer['state'] = $this->text($row->state ?? null);
            $customer['country'] = $this->text($row->country ?? null);
        }

        $payload = [
            'schema_version' => '1.0',
            'lead_reference' => $reference,
            'website_source' => 'detailing-devils-website',
            'form' => ['name' => self::FORMS[$source][0], 'type' => self::FORMS[$source][1]],
            'submitted_at' => $submitted->utc()->toIso8601String(),
            'customer' => $customer,
            'enquiry' => [
                'kind' => $home ? 'service' : 'franchise',
                'service_interest' => $home ? $this->text($row->interested_in ?? null) : null,
                'franchise_model' => $home ? null : [
                    'prime' => 'Prime',
                    'fofo' => 'FOFO',
                    'xclusive' => 'X-Clusive',
                ][$source],
                'details' => array_filter($details, fn ($value) => $value !== null),
            ],
        ];
        try {
            json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('invalid_lead_encoding');
        }

        return $payload;
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
