<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class StudioMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_12_000000_create_studio_cms_tables.php';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
    }

    public function test_scoped_migration_adds_only_two_empty_tables_and_ledger_preserving_legacy_data(): void
    {
        foreach (['tbl_studio', 'tbl_studio_detail', 'tbl_location', 'tbl_common_leads'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('marker');
            });
            DB::table($name)->insert(['marker' => 'Isolated legacy fixture']);
        }
        $this->artisan('migrate', ['--path' => [self::MIGRATION], '--force' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('studios')->count());
        $this->assertSame(0, DB::table('studio_images')->count());
        $this->assertSame([
            'id', 'name', 'slug', 'address', 'city', 'state', 'country_code', 'phones',
            'map_url', 'latitude', 'longitude', 'status', 'sort_order', 'created_at', 'updated_at',
        ], Schema::getColumnListing('studios'));
        $this->assertFalse(Schema::hasTable('users'));
        foreach (['tbl_studio', 'tbl_studio_detail', 'tbl_location', 'tbl_common_leads'] as $name) {
            $this->assertSame('Isolated legacy fixture', DB::table($name)->sole()->marker);
        }
        $ledger = DB::table('migrations')->get()->toJson();
        $this->artisan('migrate', ['--path' => [self::MIGRATION], '--force' => true])->assertSuccessful();
        $this->assertSame($ledger, DB::table('migrations')->get()->toJson());
    }

    public function test_collision_is_rejected_before_creating_either_table(): void
    {
        Schema::create('studio_images', fn (Blueprint $table) => $table->id());
        try {
            (require base_path(self::MIGRATION))->up();
            $this->fail('An existing target must stop installation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('target table already exists', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('studios'));
        $this->assertTrue(Schema::hasTable('studio_images'));
    }

    public function test_automatic_rollback_cannot_delete_studio_records_or_media(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->up();
        try {
            $migration->down();
            $this->fail('Rollback must require an explicit recovery plan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('preserve studio records', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('studios'));
        $this->assertTrue(Schema::hasTable('studio_images'));
    }
}
