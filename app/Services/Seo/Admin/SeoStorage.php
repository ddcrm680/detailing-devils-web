<?php

namespace App\Services\Seo\Admin;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Uses the unchanged default connection and accesses only SEO-owned tables. */
final class SeoStorage
{
    public function assertConfigured(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('SEO storage requires MySQL or an isolated SQLite test database.');
        }
    }

    public function state(bool $lock = false): object
    {
        $this->assertConfigured();
        $query = DB::table('seo_metadata')->where('id', 1);
        $state = ($lock ? $query->lockForUpdate() : $query)->first();
        if (! $state || $state->origin !== config('seo.origin')) {
            throw new RuntimeException('SEO metadata has not been provisioned for this website.');
        }
        if (
            ! preg_match('/^[a-f0-9]{64}$/D', $state->payload_hash ?? '')
            || ! hash_equals($state->payload_hash, hash('sha256', $state->payload))
        ) {
            throw new RuntimeException('SEO publication integrity check failed.');
        }

        return $state;
    }
}
