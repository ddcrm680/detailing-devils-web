<?php

namespace App\Actions\Franchise;

use App\Services\Franchise\OtpService;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveFranchiseLeadAction
{
    public function __construct(private readonly OtpService $otp) {}

    /** @param array{mobile_e164: string, whatsapp_e164: string} $phones */
    public function savePrime(array $data, array $phones): bool
    {
        $mobile = $phones['mobile_e164'];

        return $this->saveLead('prime-franchise', $mobile, $this->primeRow($data, $phones), function () use ($mobile): bool {
            if (! $this->otp->consumeVerification('prime', $mobile)) {
                throw ValidationException::withMessages([
                    'mobile_number' => 'Please verify your mobile number again before submitting.',
                ]);
            }

            return true;
        });
    }

    private function saveLead(string $recordType, string $normalizedMobile, array $row, Closure $verify): bool
    {
        $phone = ltrim($normalizedMobile, '+');

        return $this->withLeadLock($recordType, $phone, function () use ($recordType, $row, $phone, $verify): bool {
            return DB::transaction(function () use ($recordType, $row, $phone, $verify): bool {
                $exists = DB::table('tbl_common_leads')
                    ->where('phone', $phone)
                    ->where('record_type', $recordType)
                    ->exists();

                // Each form defines its own verification, including for duplicate leads.
                if ($verify() !== true) {
                    throw ValidationException::withMessages([
                        'verification' => 'Please complete verification before submitting.',
                    ]);
                }

                if ($exists) {
                    return false;
                }

                if (! DB::table('tbl_common_leads')->insert(array_replace($row, [
                    'phone' => $phone,
                    'record_type' => $recordType,
                ]))) {
                    throw new RuntimeException('The franchise lead could not be inserted.');
                }

                return true;
            });
        });
    }

    private function withLeadLock(string $recordType, string $phone, Closure $operation): bool
    {
        $key = 'franchise:'.substr(hash('sha256', "{$recordType}|{$phone}"), 0, 48);

        if (DB::connection()->getDriverName() !== 'mysql') {
            return Cache::lock($key, 30)->block(5, $operation);
        }

        $acquired = DB::connection()->selectOne('SELECT GET_LOCK(?, 5) AS acquired', [$key], false);

        if ((int) ($acquired->acquired ?? 0) !== 1) {
            throw new RuntimeException('The franchise lead is busy.');
        }

        try {
            return $operation();
        } finally {
            try {
                DB::connection()->selectOne('SELECT RELEASE_LOCK(?) AS released', [$key], false);
            } catch (Throwable) {
                Log::warning('A franchise lead lock could not be released.');
            }
        }
    }

    /** @param array{mobile_e164: string, whatsapp_e164: string} $phones */
    private function primeRow(array $data, array $phones): array
    {
        $name = trim(implode(' ', [
            $data['first_name'], $data['middle_name'] ?? '', $data['last_name'],
        ]));
        $propertyLocation = trim((string) ($data['property_location'] ?? ''));
        $roadFacing = $data['road_facing'] ?? null;
        $now = now();

        return [
            'name' => $name,
            'whatsapp_number' => ltrim($phones['whatsapp_e164'], '+'),
            'email' => Str::lower(trim($data['email'])),
            'country' => 'India',
            'state' => $data['state'],
            'city' => trim($data['city']),
            'profession' => $data['occupation'],
            'age' => $data['age_group'],
            'experience' => $data['business_experience'],
            'newbussiness' => trim((string) ($data['business_name'] ?? '')),
            'type_of_bussiness' => trim((string) ($data['industry'] ?? '')),
            'existingfacility' => $data['property_status'],
            'elaboratelocation' => implode(' | ', array_filter([
                $propertyLocation,
                $roadFacing ? 'Road Facing: '.$roadFacing : null,
            ])),
            'spacearound' => $data['carpet_area'] ?? null,
            'territory_city' => trim((string) ($data['preferred_city'] ?? '')),
            'territory' => trim((string) ($data['preferred_area'] ?? '')),
            'wantstart' => $data['start_timeline'],
            'bussinessowner' => $data['decision_maker'],
            'contacted' => implode(', ', $data['contact_methods']),
            'bussiness_description' => trim((string) ($data['partnership_reason'] ?? '')),
            'current_address' => trim(implode(', ', array_filter([
                trim($data['city']), $data['state'], trim((string) ($data['pin_code'] ?? '')),
            ]))),
            'created_date' => $now->toDateString(),
            'add_franchise_date' => $now->toDateTimeString(),
            'consent' => (int) $data['consent'],
            'closed_leads' => '0',
        ];
    }
}
