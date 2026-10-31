<?php

namespace App\Actions\Franchise;

use App\Services\Franchise\OtpService;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public function saveFofo(array $data, string $normalizedMobile, Session $session): bool
    {
        return $this->saveVerifiedApplication('FOFO', $data, $normalizedMobile, $session, fn (): array => $this->fofoRow($data));
    }

    public function saveXclusive(array $data, string $normalizedMobile, Session $session): bool
    {
        // Provisional identity: production CRM compatibility must be confirmed before activation.
        return $this->saveVerifiedApplication('xclusive', $data, $normalizedMobile, $session, fn (): array => $this->xclusiveRow($data));
    }

    private function saveVerifiedApplication(string $recordType, array $data, string $normalizedMobile, Session $session, Closure $mapRow): bool
    {
        $storedImage = null;

        try {
            $created = $this->saveLead(
                $recordType,
                $normalizedMobile,
                $mapRow(),
                fn (): bool => $this->verifyFofoEmail($data['email'], $session),
                function (array $row) use ($data, &$storedImage): array {
                    if ($data['spacearound'] === 'YES') {
                        $storedImage = $this->storeFofoImage($data['locationimage']);
                        $row['image_franchise'] = $storedImage;
                    }

                    return $row;
                },
                $this->fofoDuplicatePhones($normalizedMobile),
            );
        } catch (Throwable $exception) {
            if ($storedImage !== null) {
                try {
                    if (! Storage::disk('fofo_images')->delete($storedImage)) {
                        Log::warning('A failed franchise lead image could not be removed.');
                    }
                } catch (Throwable) {
                    Log::warning('A failed franchise lead image could not be removed.');
                }
            }

            throw $exception;
        }

        $session->forget('email_validation.proof');

        return $created;
    }

    private function saveLead(string $recordType, string $normalizedMobile, array $row, Closure $verify, ?Closure $prepareInsert = null, ?array $duplicatePhones = null): bool
    {
        $phone = ltrim($normalizedMobile, '+');
        $duplicatePhones ??= [$phone];

        return $this->withLeadLock($recordType, $phone, function () use ($recordType, $row, $phone, $duplicatePhones, $verify, $prepareInsert): bool {
            return DB::transaction(function () use ($recordType, $row, $phone, $duplicatePhones, $verify, $prepareInsert): bool {
                $exists = DB::table('tbl_common_leads')
                    ->where('record_type', $recordType)
                    ->whereIn('phone', $duplicatePhones)
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

                if ($prepareInsert !== null) {
                    $row = $prepareInsert($row);
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

    private function fofoDuplicatePhones(string $normalizedMobile): array
    {
        if (! preg_match('/\A\+91([0-9]{10})\z/', $normalizedMobile, $matches)) {
            return [ltrim($normalizedMobile, '+')];
        }

        $national = $matches[1];

        return [$national, '0'.$national, '91'.$national, '+91'.$national];
    }

    private function verifyFofoEmail(string $email, Session $session): bool
    {
        $proof = $session->get('email_validation.proof');
        $expected = hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));

        if (! is_array($proof)
            || ! is_string($proof['email_hash'] ?? null)
            || ! is_int($proof['expires_at'] ?? null)
            || $proof['expires_at'] <= now()->timestamp
            || ! hash_equals($expected, $proof['email_hash'])) {
            throw ValidationException::withMessages([
                'email' => 'Please validate this email address again before submitting.',
            ]);
        }

        return true;
    }

    private function franchiseContactRow(array $data): array
    {
        if ($data['country'] === 'other') {
            $country = trim($data['another_country_name']);
            $state = trim($data['another_state_name']);
            $city = trim($data['another_city_name']);
        } else {
            $country = DB::table('tbl_country_phone')->where('id', $data['country'])->value('countryname');
            $state = DB::table('tbl_states')->where('id', $data['state'])->where('country_id', $data['country'])->value('name');
            $city = DB::table('tbl_cities')->where('id', $data['city'])->where('state_id', $data['state'])->value('name');

            if ($country === null || $state === null || $city === null) {
                throw ValidationException::withMessages([
                    'country' => 'Please choose your location again before submitting.',
                ]);
            }
        }

        return [
            'name' => trim($data['name'].' '.$data['lname']),
            'email' => strtolower(trim($data['email'])),
            'country' => $country,
            'state' => $state,
            'city' => $city,
            'website' => trim((string) ($data['website'] ?? '')),
            'profession' => trim((string) ($data['profession'] ?? '')),
            'dob' => $data['dob'],
        ];
    }

    private function fofoRow(array $data): array
    {
        return array_merge($this->franchiseContactRow($data), [
            'contacted' => $data['contacted'],
            'hearabout' => $data['hearabout'],
            'newbussiness' => trim($data['newbussiness']),
            'wantstart' => $data['wantstart'],
            'bussinessowner' => $data['bussinessowner'],
            'type_of_bussiness' => trim((string) ($data['type_of_bussiness'] ?? '')),
            'product_service' => trim((string) ($data['product_service'] ?? '')),
            'bussiness_description' => trim((string) ($data['bussiness_description'] ?? '')),
            'experienceautomotive' => $data['experienceautomotive'],
            'experience' => trim((string) ($data['experience'] ?? '')),
            'existingautomotive' => $data['existingautomotive'],
            'existingfacility' => trim((string) ($data['existingfacility'] ?? '')),
            'spacearound' => $data['spacearound'],
            'elaboratelocation' => trim((string) ($data['elaboratelocation'] ?? '')),
            'whichcityfranchise' => trim($data['franchiseapply']),
            'civillitigation' => $data['civillitigation'],
            'spendfranchise' => $data['spendfranchise'],
            'created_date' => now()->toDateString(),
            'closed_leads' => '0',
        ]);
    }

    private function xclusiveRow(array $data): array
    {
        return array_merge($this->franchiseContactRow($data), [
            'contacted' => $data['contacted'],
            'hearabout' => $data['hearabout'],
            'newbussiness' => trim($data['newbussiness']),
            'wantstart' => $data['wantstart'],
            'bussinessowner' => $data['bussinessowner'],
            'type_of_bussiness' => trim((string) ($data['type_of_bussiness'] ?? '')),
            'product_service' => trim((string) ($data['product_service'] ?? '')),
            'bussiness_description' => trim((string) ($data['bussiness_description'] ?? '')),
            'experienceautomotive' => $data['experienceautomotive'],
            'experience' => trim((string) ($data['experience'] ?? '')),
            'existingautomotive' => $data['existingautomotive'],
            'existingfacility' => trim((string) ($data['existingfacility'] ?? '')),
            'spacearound' => $data['spacearound'],
            'elaboratelocation' => trim((string) ($data['elaboratelocation'] ?? '')),
            'whichcityfranchise' => trim($data['franchiseapply']),
            'civillitigation' => $data['civillitigation'],
            'spendfranchise' => $data['spendfranchise'],
            'created_date' => now()->toDateString(),
            'closed_leads' => '0',
        ]);
    }

    private function storeFofoImage(UploadedFile $image): string
    {
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('The location image has an unsupported format.'),
        };
        $disk = Storage::disk('fofo_images');
        File::ensureDirectoryExists($disk->path(''));
        $source = fopen($image->getRealPath(), 'rb');

        if ($source === false) {
            throw new RuntimeException('The location image could not be read.');
        }

        try {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $filename = Str::random(32).'.'.$extension;
                $destination = @fopen($disk->path($filename), 'x+b');

                if ($destination === false) {
                    continue;
                }

                try {
                    $written = stream_copy_to_stream($source, $destination);
                    if ($written === false || $written !== $image->getSize()) {
                        throw new RuntimeException('The location image could not be saved.');
                    }
                } catch (Throwable $exception) {
                    fclose($destination);
                    $disk->delete($filename);
                    throw $exception;
                }

                fclose($destination);

                return $filename;
            }
        } finally {
            fclose($source);
        }

        throw new RuntimeException('The location image could not be saved.');
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
