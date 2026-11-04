<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class SeoProvisionCommand extends Command
{
    protected $signature = 'seo:provision {email : Owner-approved administrator address} {--role=seo_admin : Explicit super_admin, admin or seo_admin role}';

    protected $description = 'Provision one authorized Laravel user and SEO metadata after the scoped migration; never runs migrations';

    public function handle(SeoStorage $storage, SeoPublicationCodec $codec, SeoSitemap $sitemap): int
    {
        try {
            $storage->assertConfigured();
            $email = mb_strtolower(trim($this->argument('email')));
            $role = $this->option('role');
            if (! in_array($role, User::SEO_ROLES, true)) {
                $this->error('Choose super_admin, admin or seo_admin explicitly.');

                return self::FAILURE;
            }
            if (Validator::make(['email' => $email], ['email' => 'required|email:rfc|max:254'])->fails()) {
                $this->error('Enter a valid administrator email.');

                return self::FAILURE;
            }
            if (
                User::query()
                    ->whereIn('role', User::SEO_ROLES)
                    ->exists()
                || User::query()
                    ->where('email', $email)
                    ->exists()
                || DB::table('seo_metadata')->exists()
            ) {
                $this->error(
                    'An administrator, matching user or SEO publication already exists. No account was changed.'
                );

                return self::FAILURE;
            }
            $minimumLength = app()->environment(['local', 'testing']) ? 8 : 14;
            $password = $this->secret("New administrator password (at least {$minimumLength} characters)");
            if (
                ! is_string($password) || strlen($password) < $minimumLength || strlen($password) > 128
                || $this->secret('Confirm password') !== $password
            ) {
                $this->error("Passwords must match and contain {$minimumLength}–128 characters.");

                return self::FAILURE;
            }
            $xml = $sitemap->xml();
            $sitemap->urls($xml);
            DB::transaction(function () use (
                $email,
                $password,
                $xml,
                $codec,
                $role
            ) {
                $actor = new User([
                    'name' => 'SEO Administrator',
                    'email' => $email,
                    'password' => $password,
                ]);
                $actor->forceFill(['role' => $role])->save();
                $payload = $codec->encode(new SeoSnapshot);
                DB::table('seo_metadata')->insert([
                    'id' => 1,
                    'origin' => config('seo.origin'),
                    'version' => 0,
                    'payload' => $payload,
                    'payload_hash' => hash('sha256', $payload),
                    'sitemap_xml' => $xml,
                    'sitemap_hash' => hash('sha256', $xml),
                    'updated_at' => now(),
                ]);
            });
            $this->info('Standard Laravel user and SEO metadata provisioned. Access follows the assigned role.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error(
                'Provisioning stopped. Review the scoped migration, existing tables and sitemap. No credentials are displayed.'
            );

            return self::FAILURE;
        }
    }
}
