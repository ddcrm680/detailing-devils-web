<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SeoMigrationSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
    }

    public function test_scoped_install_creates_only_required_tables_and_laravel_ledger(): void
    {
        foreach (['tbl_common_leads', 'tbl_SaveContactForm7_8', 'tbl_states'] as $table) {
            Schema::create(
                $table,
                function (Blueprint $schema) {
                    $schema->id();
                    $schema->string('marker');
                }
            );
            DB::table($table)->insert(['marker' => 'Synthetic preserved business fixture']);
        }
        $before = $this->businessSnapshot();
        $this->assertFalse(Schema::hasTable('migrations'));
        $this->artisan('migrate', ['--path' => $this->paths(), '--force' => true])->assertSuccessful();
        $tables = Schema::getTableListing(schemaQualified: false);
        sort($tables);
        $expected = [
            'migrations',
            'users',
            'password_reset_tokens',
            'sessions',
            'seo_metadata',
            'seo_revisions',
            'tbl_common_leads',
            'tbl_SaveContactForm7_8',
            'tbl_states',
        ];
        sort($expected);
        $this->assertSame($expected, $tables);
        $this->assertSame($before, $this->businessSnapshot());
        $this->assertSame(3, DB::table('migrations')->count());
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumns(
            'sessions',
            ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']
        ));
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));
        $this->assertFalse(Schema::hasTable('studios'));
        $this->artisan('migrate', ['--path' => $this->paths(), '--force' => true])->assertSuccessful();
        $this->assertSame(3, DB::table('migrations')->count());

        // Inspect Laravel's normal discovery/ledger selection; never execute blanket migrations.
        $normal = app('migrator')->getMigrationFiles([database_path('migrations')]);
        $pending = array_diff(array_keys($normal), app('migration.repository')->getRan());
        $this->assertArrayHasKey('0001_01_01_000000_create_users_table', $normal);
        $this->assertNotContains('0001_01_01_000000_create_users_table', $pending);
        foreach (array_slice($this->paths(), 1) as $path) {
            $filename = basename($path);
            $oldName = app('migrator')->getMigrationName(database_path('migrations/seo/'.$filename));
            $newName = app('migrator')->getMigrationName(base_path($path));
            $this->assertSame($oldName, $newName);
            $this->assertArrayHasKey($newName, $normal);
            $this->assertContains($oldName, app('migration.repository')->getRan());
            $this->assertNotContains($newName, $pending);
        }
        $this->assertSame($before, $this->businessSnapshot());
    }

    public function test_normal_migration_applies_only_missing_seo_migrations_to_an_existing_laravel_install(): void
    {
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/0001_01_01_000001_create_cache_table.php',
                'database/migrations/0001_01_01_000002_create_jobs_table.php',
            ],
            '--force' => true,
        ])->assertSuccessful();
        $frameworkLedger = DB::table('migrations')->orderBy('id')->get()->toJson();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Synthetic Unassigned User',
            'email' => 'unassigned@example.test',
            'password' => 'synthetic-hash',
        ]);
        DB::table('sessions')->insert([
            'id' => 'isolated-preserved-session',
            'user_id' => $userId,
            'payload' => 'Synthetic session fixture',
            'last_activity' => 1,
        ]);
        $userBefore = (array) DB::table('users')->where('id', $userId)->first();
        $sessionBefore = DB::table('sessions')->get()->toJson();
        $files = app('migrator')->getMigrationFiles([database_path('migrations')]);
        $pending = array_values(array_diff(array_keys($files), app('migration.repository')->getRan()));
        $this->assertSame([
            '2026_11_02_000000_add_role_to_users_table',
            '2026_11_02_000001_create_website_seo_store',
        ], $pending);

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame(5, DB::table('migrations')->count());
        $this->assertSame(
            $frameworkLedger,
            DB::table('migrations')->where('batch', 1)->orderBy('id')->get()->toJson()
        );
        $userAfter = (array) DB::table('users')->where('id', $userId)->first();
        $this->assertNull($userAfter['role']);
        unset($userAfter['role']);
        $this->assertSame($userBefore, $userAfter);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame($sessionBefore, DB::table('sessions')->get()->toJson());
        $this->assertTrue(Schema::hasTable('seo_metadata'));
        $this->assertTrue(Schema::hasTable('seo_revisions'));

        $ledgerBefore = DB::table('migrations')->orderBy('id')->get()->toJson();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($ledgerBefore, DB::table('migrations')->orderBy('id')->get()->toJson());
        $this->assertSame($sessionBefore, DB::table('sessions')->get()->toJson());
    }

    public function test_existing_snapshot_code_initializes_the_sitemap_without_creating_an_administrator(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->get('/sitemap.xml')->assertStatus(503);
        $sitemap = app(SeoSitemap::class);
        $xml = $sitemap->xml();
        $this->assertCount(13, $sitemap->urls($xml));
        $this->assertSame(
            $sitemap->urls(file_get_contents(base_path('tests/Fixtures/seo/sitemap.xml'))),
            $sitemap->urls($xml)
        );
        $payload = app(SeoPublicationCodec::class)->encode(new SeoSnapshot);
        DB::transaction(function () use ($payload, $xml) {
            if (DB::table('seo_metadata')->exists() || DB::table('seo_revisions')->exists()) {
                throw new RuntimeException('Initialization requires a fresh SEO store.');
            }
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
        $this->app->forgetInstance(SeoResolver::class);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertSame($xml, $response->getContent());
        $this->assertSame(1, DB::table('seo_metadata')->count());
        $this->assertSame(0, DB::table('seo_revisions')->count());
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->artisan('seo:sitemap', ['--check' => true])->assertSuccessful();
    }

    public function test_collision_with_any_target_stops_before_creating_or_altering_tables(): void
    {
        $this->createUsers();
        foreach (['seo_metadata', 'seo_revisions'] as $collision) {
            Schema::create($collision, fn (Blueprint $table) => $table->string('legacy_marker'));
            DB::table($collision)->insert(['legacy_marker' => 'Synthetic existing table']);
            $before = Schema::getTableListing();
            try {
                $this->migration()->up();
                $this->fail('An existing table was adopted or overwritten.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('target table already exists', $exception->getMessage());
            }
            $this->assertSame($before, Schema::getTableListing());
            $this->assertSame('Synthetic existing table', DB::table($collision)->value('legacy_marker'));
            Schema::drop($collision); // Only the explicitly created in-memory fixture.
        }
    }

    public function test_rollback_refuses_to_discard_an_account_or_editorial_history(): void
    {
        $this->createUsers();
        $this->migration()->up();
        DB::table('users')->insert([
            'name' => 'Synthetic User',
            'email' => 'qa@example.test',
            'password' => 'synthetic-hash',
        ]);
        try {
            $this->migration()->down();
            $this->fail('A populated SEO installation was dropped.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rollback stopped', $exception->getMessage());
        }
        $this->assertSame(1, DB::table('users')->count());
        $this->assertTrue(Schema::hasTable('seo_metadata'));
        $this->assertTrue(Schema::hasTable('seo_revisions'));
    }

    public function test_automatic_rollback_never_drops_even_empty_tables(): void
    {
        $this->createUsers();
        $this->migration()->up();
        $before = Schema::getTableListing();
        try {
            $this->migration()->down();
            $this->fail('An automatic rollback dropped an empty installation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rollback stopped', $exception->getMessage());
        }
        $this->assertSame($before, Schema::getTableListing());
    }

    public function test_canonical_users_migration_refuses_an_untracked_existing_table(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->string('legacy_marker'));
        DB::table('users')->insert(['legacy_marker' => 'Synthetic pre-existing identity table']);
        try {
            (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
            $this->fail('An existing users table was adopted or recreated.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }
        $this->assertSame('Synthetic pre-existing identity table', DB::table('users')->value('legacy_marker'));
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('sessions'));
    }

    public function test_standard_users_migration_creates_and_rolls_back_its_three_framework_tables(): void
    {
        $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migration->up();
        foreach (['users', 'password_reset_tokens', 'sessions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $migration->down();
        foreach (['users', 'password_reset_tokens', 'sessions'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_additive_role_preserves_accounts_and_grants_nothing_by_default(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        $id = DB::table('users')->insertGetId([
            'name' => 'Synthetic Existing User',
            'email' => 'existing@example.test',
            'password' => 'synthetic-hash',
        ]);
        $before = (array) DB::table('users')
            ->where('id', $id)
            ->first();
        (require database_path('migrations/2026_11_02_000000_add_role_to_users_table.php'))->up();
        $after = (array) DB::table('users')
            ->where('id', $id)
            ->first();
        $this->assertNull($after['role']);
        unset($after['role']);
        $this->assertSame($before, $after);
        $this->assertFalse(User::query()
            ->find($id)
            ->canManageSeo());
        try {
            (require database_path('migrations/2026_11_02_000000_add_role_to_users_table.php'))->up();
            $this->fail('An existing role column was overwritten.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Authorization migration stopped', $exception->getMessage());
        }
    }

    public function test_seo_cannot_create_users_or_run_before_authorization_is_ready(): void
    {
        try {
            $this->migration()->up();
            $this->fail('SEO silently created users.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('canonical users', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('seo_metadata'));
        $this->assertFalse(Schema::hasTable('seo_revisions'));
    }

    public function test_there_is_exactly_one_users_creator_in_all_current_migration_files(): void
    {
        $creators = [];
        foreach (File::allFiles(database_path('migrations')) as $file) {
            if (preg_match('/Schema::create\([\'"]users[\'"]/', $file->getContents())) {
                $creators[] = $file->getFilename();
            }
        }
        $this->assertSame(['0001_01_01_000000_create_users_table.php'], $creators);
    }

    private function paths(): array
    {
        return [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_11_02_000000_add_role_to_users_table.php',
            'database/migrations/2026_11_02_000001_create_website_seo_store.php',
        ];
    }

    private function createUsers(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_11_02_000000_add_role_to_users_table.php'))->up();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_02_000001_create_website_seo_store.php');
    }

    private function businessSnapshot(): array
    {
        $snapshot = [];
        foreach (['tbl_common_leads', 'tbl_SaveContactForm7_8', 'tbl_states'] as $table) {
            $snapshot[$table] = [
                DB::selectOne('SELECT sql FROM sqlite_master WHERE name = ?', [$table])->sql,
                DB::table($table)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all(),
            ];
        }

        return $snapshot;
    }
}
