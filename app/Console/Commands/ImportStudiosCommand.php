<?php

namespace App\Console\Commands;

use App\Models\Studio;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ImportStudiosCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'studios:import {--force : Explicitly confirm the import in production}';

    protected $description = 'Import the approved locator JSON once into an empty Studio CMS; never overwrite existing records';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        try {
            $counts = DB::transaction(function () {
                if (Studio::query()->exists()) {
                    throw new RuntimeException('Studio import refused: the studios table already contains records.');
                }

                $records = File::json(resource_path('data/studios.json'));
                Validator::make(['studios' => $records], [
                    'studios' => ['required', 'array', 'list', 'min:1'],
                    'studios.*' => ['required', 'array:name,address,state,status,phones,map_url'],
                    'studios.*.name' => ['required', 'string', 'max:255'],
                    'studios.*.address' => ['required', 'string', 'max:4000'],
                    'studios.*.state' => ['required', 'string', 'max:100'],
                    'studios.*.status' => ['required', Rule::in(Studio::STATUSES)],
                    'studios.*.phones' => ['required', 'array', 'list', 'min:1', 'max:10'],
                    'studios.*.phones.*' => [
                        'required', 'string', 'max:40',
                        'regex:/^(?:\+|\(\+?)?[0-9][0-9 ()-]*[0-9]$/',
                        function ($attribute, $value, $fail) {
                            $digits = preg_replace('/[^0-9]/', '', $value);
                            if (strlen($digits) < 7 || strlen($digits) > 15) {
                                $fail('Each phone must contain 7–15 digits, including the country code.');
                            }
                        },
                    ],
                    'studios.*.map_url' => [
                        'nullable', 'string', 'max:2048', 'url:https',
                        function ($attribute, $value, $fail) {
                            $parts = parse_url($value);
                            $host = strtolower($parts['host'] ?? '');
                            $path = $parts['path'] ?? '';
                            $googleMaps = in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
                                || (in_array($host, ['google.com', 'www.google.com'], true)
                                    && preg_match('~^/maps(?:/|$)~', $path))
                                || ($host === 'goo.gl' && str_starts_with($path, '/maps/'));
                            if (! $googleMaps || isset($parts['user']) || isset($parts['pass'])
                                || (isset($parts['port']) && $parts['port'] !== 443)
                                || str_contains($value, '\\')) {
                                $fail('Use an HTTPS Google Maps sharing or location link.');
                            }
                        },
                    ],
                ])->validate();

                $identities = [];
                $slugs = [];
                foreach ($records as $position => $record) {
                    $identity = mb_strtolower(trim($record['name'])."\0".trim($record['address']));
                    if (isset($identities[$identity])) {
                        throw new RuntimeException('Duplicate identity in the import source.');
                    }
                    $identities[$identity] = true;
                    $base = substr(Str::slug($record['name']), 0, 180) ?: 'studio';
                    $slug = $base;
                    $suffix = 2;
                    while (isset($slugs[$slug])) {
                        $slug = $base.'-'.$suffix++;
                    }
                    $slugs[$slug] = true;
                    Studio::create([
                        'name' => $record['name'],
                        'slug' => $slug,
                        'address' => $record['address'],
                        'state' => $record['state'],
                        'phones' => $record['phones'],
                        'map_url' => $record['map_url'] ?? null,
                        'status' => $record['status'],
                        'sort_order' => $position + 1,
                    ]);
                }

                return [count($records), array_sum(array_map(fn ($studio) => count($studio['phones']), $records))];
            });
            $this->info("Imported {$counts[0]} studios with {$counts[1]} phone numbers. Existing records were not overwritten.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Studio import stopped. The transaction was rolled back; existing records were preserved.');
            if ($exception instanceof RuntimeException && ! $exception instanceof \PDOException) {
                $this->line($exception->getMessage());
            }

            return self::FAILURE;
        }
    }
}
