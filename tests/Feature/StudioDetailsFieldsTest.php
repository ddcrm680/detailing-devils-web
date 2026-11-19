<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\User;
use App\Support\StudioServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudioDetailsFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]);
        config()->set('session.driver', 'array');
        config()->set('cache.default', 'array');
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_02_000000_add_role_to_users_table.php',
            '2026_11_12_000000_create_studio_cms_tables.php',
            '2026_11_16_000000_create_content_urls.php',
            '2026_11_19_000000_add_services_and_opening_hours_to_studios.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    private function actor(string $role = 'admin'): User
    {
        $user = new User(['name' => 'Isolated Admin', 'email' => $role.'@example.test', 'password' => Hash::make('Synthetic-password-123')]);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function fields(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Isolated Studio', 'address' => 'Sample address', 'state' => 'Delhi',
            'phones' => ['+91 98765 43210'], 'status' => 'draft', 'sort_order' => 0,
        ], $overrides);
    }

    private function week(): array
    {
        return array_fill_keys(array_keys(Studio::WEEKDAYS), ['closed' => '0', 'opens' => '10:00', 'closes' => '19:00']);
    }

    public function test_create_edit_and_clear_selected_services_and_hours(): void
    {
        $this->actingAs($this->actor());
        $week = $this->week();
        $week['sunday'] = ['closed' => '1'];
        $this->post(route('admin.studios.store'), $this->fields([
            'studio_details_present' => '1', 'services' => ['ceramic', 'ppf'], 'opening_hours' => $week,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $studio = Studio::firstOrFail();
        $this->assertSame(['ceramic', 'ppf'], $studio->services);
        $this->assertSame(['closed' => true, 'opens' => null, 'closes' => null], $studio->opening_hours['sunday']);
        $this->get(route('admin.studios.edit', $studio))->assertOk()->assertSee('Services Offered')->assertSee('Opening Hours')->assertSee('19:00');
        $this->put(route('admin.studios.update', $studio), $this->fields([
            'services' => ['interior'], 'opening_hours' => $this->week(),
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['interior'], $studio->fresh()->services);
        // An older client omitting optional fields preserves saved selections.
        $this->put(route('admin.studios.update', $studio), $this->fields())->assertSessionHasNoErrors();
        $this->assertSame(['interior'], $studio->fresh()->services);
        $empty = array_fill_keys(array_keys(Studio::WEEKDAYS), ['closed' => '0', 'opens' => null, 'closes' => null]);
        $this->put(route('admin.studios.update', $studio), $this->fields([
            'studio_details_present' => '1', 'opening_hours' => $empty,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($studio->fresh()->services);
        $this->assertNull($studio->fresh()->opening_hours);
    }

    public function test_invalid_services_and_weekly_times_are_rejected_without_writes(): void
    {
        $this->actingAs($this->actor());
        foreach ([['unknown'], ['ceramic', 'ceramic'], 'ceramic', ['nested' => 'ceramic']] as $services) {
            $this->post(route('admin.studios.store'), $this->fields(['services' => $services]))->assertSessionHasErrors();
        }
        foreach ([
            ['opens' => '25:00', 'closes' => '19:00'],
            ['opens' => '19:00', 'closes' => '10:00'],
            ['opens' => '10:00', 'closes' => '10:00'],
            ['opens' => '10:00', 'closes' => null],
            ['closed' => 'maybe'],
            ['extra' => 'unexpected'],
        ] as $invalid) {
            $week = $this->week();
            $week['monday'] = array_replace($week['monday'], $invalid);
            $this->post(route('admin.studios.store'), $this->fields(['opening_hours' => $week]))->assertSessionHasErrors();
        }
        $this->post(route('admin.studios.store'), $this->fields(['opening_hours' => ['holiday' => ['closed' => true]]]))->assertSessionHasErrors();
        $this->assertSame(0, Studio::count());
    }

    public function test_empty_legacy_values_and_existing_permissions_are_preserved(): void
    {
        $studio = Studio::create($this->fields());
        $this->assertNull($studio->services);
        $this->assertNull($studio->opening_hours);
        $this->get(route('admin.studios.create'))->assertRedirect(route('admin.login'));
        $this->actingAs($this->actor('seo_admin'))->post(route('admin.studios.store'), $this->fields())->assertForbidden();
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->actor($role))->get(route('admin.studios.create'))->assertOk()->assertSee('Ceramic Coating');
            $this->get(route('admin.studios.edit', $studio))->assertOk();
        }
        $this->assertSame('isolated-studio', $studio->fresh()->slug);
    }

    public function test_failed_validation_preserves_selected_services_and_entered_hours(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $week = $this->week();
        $week['monday']['closes'] = '09:00';
        $page = $this->from(route('admin.studios.edit', $studio))->followingRedirects()
            ->put(route('admin.studios.update', $studio), $this->fields([
                'studio_details_present' => '1', 'services' => ['ppf'], 'opening_hours' => $week,
            ]));
        $page->assertOk();
        $this->assertTrue(str_contains($page->getContent(), 'Closing time must be after opening time.'), 'Inline closing-time error is missing.');
        $this->assertTrue(str_contains($page->getContent(), 'value="09:00"'), 'Entered closing time was lost.');
        $this->assertNull($studio->fresh()->services);
        $this->assertNull($studio->fresh()->opening_hours);
    }

    public function test_editor_defaults_apply_only_to_new_studios_and_respect_failed_input(): void
    {
        $this->actingAs($this->actor());
        $page = $this->get(route('admin.studios.create'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(6, $xpath->query('//input[@name="services[]"][@checked]')->length);
        $this->assertSame(7, $xpath->query('//input[@type="time"][@value="10:00"]')->length);
        $this->assertSame(7, $xpath->query('//input[@type="time"][@value="19:00"]')->length);
        $this->assertSame(0, Studio::count());
        $studio = Studio::create($this->fields());
        $page = $this->get(route('admin.studios.edit', $studio))->assertOk();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="services[]"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@type="time"][@value!=""]')->length);
        $this->assertNull($studio->fresh()->opening_hours);
        $page = $this->from(route('admin.studios.create'))->followingRedirects()->post(route('admin.studios.store'), $this->fields([
            'name' => '', 'studio_details_present' => '1', 'opening_hours' => [],
        ]))->assertOk();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="services[]"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@type="time"][@value!=""]')->length);
    }

    public function test_catalogue_reuses_the_existing_shared_services_and_real_destination(): void
    {
        $catalogue = StudioServices::all();
        $this->assertCount(6, $catalogue);
        foreach ($catalogue as $service) {
            $this->assertContains($service['name'], array_column(config('site.services'), 'title'));
            $this->assertFileExists(public_path($service['image']));
            $this->assertSame(route('home').'#services', $service['url']);
            $this->assertNotEmpty($service['description']);
        }
    }

    public function test_additive_migration_keeps_existing_studio_values_unchanged(): void
    {
        $migration = require database_path('migrations/2026_11_19_000000_add_services_and_opening_hours_to_studios.php');
        $migration->down();
        $studio = Studio::create($this->fields());
        $before = (array) DB::table('studios')->find($studio->id);
        $migration->up();
        $after = (array) DB::table('studios')->find($studio->id);
        $this->assertSame($before, array_diff_key($after, array_flip(['services', 'opening_hours'])));
        $this->assertNull($after['services']);
        $this->assertNull($after['opening_hours']);
    }
}
