<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\StudioImage;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioCmsTest extends TestCase
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
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_02_000000_add_role_to_users_table.php',
            '2026_11_12_000000_create_studio_cms_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Storage::fake('studio_images');
    }

    private function actor(string $role = 'admin'): User
    {
        $user = new User([
            'name' => 'Isolated CMS fixture', 'email' => uniqid().'@example.test',
            'password' => Hash::make('Synthetic-test-password'),
        ]);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function fields(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Synthetic Studio', 'slug' => 'synthetic-studio',
            'address' => 'Synthetic business address', 'city' => null, 'state' => 'Delhi',
            'country_code' => null, 'phones' => ['+91 98765 43210', '+91 99887 76655'],
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=studio',
            'latitude' => null, 'longitude' => null, 'status' => 'draft', 'sort_order' => 5,
        ], $overrides);
    }

    public function test_only_admin_and_super_admin_can_manage_every_studio_endpoint(): void
    {
        $studio = Studio::create($this->fields());
        $image = new StudioImage;
        $image->forceFill(['path' => 'studios/fixture.jpg', 'width' => 400, 'height' => 300]);
        $studio->images()->save($image);
        $this->get('/admin/studios')->assertRedirect(route('admin.login'));
        foreach (['seo_admin', 'unassigned'] as $role) {
            $this->actingAs($this->actor($role));
            $this->get('/admin/studios')->assertForbidden();
            $this->get('/admin/studios/create')->assertForbidden();
            $this->get('/admin/studios/'.$studio->id)->assertForbidden();
            $this->get('/admin/studios/'.$studio->id.'/edit')->assertForbidden();
            $this->post('/admin/studios', $this->fields())->assertForbidden();
            $this->put('/admin/studios/'.$studio->id, $this->fields())->assertForbidden();
            $this->post('/admin/studios/'.$studio->id.'/images')->assertForbidden();
            $this->patch('/admin/studios/'.$studio->id.'/images/'.$image->id)->assertForbidden();
            $this->delete('/admin/studios/'.$studio->id.'/images/'.$image->id)->assertForbidden();
        }
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->actor($role));
            $this->get('/admin/studios')->assertOk()->assertSee('Add New Studio');
            $this->get('/admin/studios/'.$studio->id.'/edit')->assertOk()
                ->assertSee('/assets/js/admin/studios.js', false)
                ->assertSee('/assets/css/admin-studios.css', false);
            $this->get('/admin/studios/'.$studio->id)->assertOk();
        }
        $this->assertSame(1, Studio::count());
        $this->assertFalse(Schema::hasTable('seo_metadata'));
    }

    public function test_existing_login_logout_and_security_headers_work_without_seo_storage(): void
    {
        $actor = $this->actor();
        $this->get('/admin/login')->assertOk();
        $this->post('/admin/login', [
            'email' => $actor->email, 'password' => 'Synthetic-test-password',
        ])->assertRedirect(route('admin.seo.index'));
        $this->assertAuthenticatedAs($actor);
        $this->get('/admin/studios')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_create_edit_ordered_phones_status_and_private_preview_persist(): void
    {
        $this->actingAs($this->actor());
        $this->get('/admin/studios/create')->assertOk()->assertSee('Create studio');
        $this->post('/admin/studios', $this->fields())->assertSessionHasNoErrors();
        $studio = Studio::sole();
        $this->assertSame($this->fields()['phones'], $studio->phones);
        $this->assertNull($studio->city);
        $this->assertNull($studio->latitude);
        $updated = $this->fields([
            'phones' => ['+91 99887 76655', '+91 98765 43210', '+91 91234 56789'],
            'status' => 'active', 'sort_order' => 2, 'country_code' => 'IN',
        ]);
        $this->put('/admin/studios/'.$studio->id, $updated)->assertSessionHasNoErrors();
        $studio->refresh();
        $this->assertSame($updated['phones'], $studio->phones);
        $this->assertSame('active', $studio->status);
        $this->assertSame(2, $studio->sort_order);
        $this->get('/admin/studios/'.$studio->id)->assertOk()->assertSee('Private CMS preview');
        $this->put('/admin/studios/'.$studio->id, $this->fields(['status' => 'inactive']))
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $studio->fresh()->status);
    }

    public function test_validation_protects_slug_maps_coordinates_and_contacts(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $this->post('/admin/studios', $this->fields())
            ->assertSessionHasErrors('slug');
        $this->put('/admin/studios/'.$studio->id, $this->fields([
            'slug' => '../bad', 'phones' => ['abc'], 'map_url' => 'https://evil.example/maps',
            'latitude' => 91, 'longitude' => null, 'country_code' => 'India', 'status' => 'public',
        ]))->assertSessionHasErrors(['slug', 'phones.0', 'map_url', 'latitude', 'longitude', 'country_code', 'status']);
        $this->put('/admin/studios/'.$studio->id, $this->fields([
            'latitude' => 28.61, 'longitude' => 77.20,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('28.6100000', $studio->fresh()->latitude);
        $this->assertSame('77.2000000', $studio->fresh()->longitude);
        foreach (['javascript:alert(1)', 'https://user@maps.google.com/', 'https://google.com:444/maps', 'https://google.com/search'] as $url) {
            $this->put('/admin/studios/'.$studio->id, $this->fields(['map_url' => $url]))
                ->assertSessionHasErrors('map_url');
        }
    }

    public function test_search_state_status_pagination_and_display_order(): void
    {
        $this->actingAs($this->actor());
        for ($i = 1; $i <= 17; $i++) {
            Studio::create($this->fields([
                'name' => 'Fixture '.$i, 'slug' => 'fixture-'.$i, 'sort_order' => $i,
                'state' => $i === 1 ? 'Punjab' : 'Delhi',
                'status' => $i === 1 ? 'inactive' : 'active',
            ]));
        }
        $this->get('/admin/studios')->assertOk()->assertViewHas('studios', function ($studios) {
            return $studios->count() === 15 && $studios->total() === 17
                && $studios->first()->sort_order === 1;
        });
        $this->get('/admin/studios?page=2')->assertOk()
            ->assertViewHas('studios', fn ($studios) => $studios->count() === 2);
        $this->get('/admin/studios?state=Punjab&status=inactive&q=Fixture')->assertOk()
            ->assertViewHas('studios', fn ($studios) => $studios->total() === 1);
        $this->get('/admin/studios?q=99887')->assertOk()
            ->assertViewHas('studios', fn ($studios) => $studios->total() === 17);
    }

    public function test_multiple_contact_formats_can_be_saved_without_changing_values(): void
    {
        $studio = Studio::create($this->fields([
            'phones' => ['(+91) 98765 43210', '+91-99887-76655', '919123456789'],
        ]));
        $this->actingAs($this->actor());
        $this->put('/admin/studios/'.$studio->id, $studio->only([
            'name', 'slug', 'address', 'city', 'state', 'country_code', 'phones',
            'map_url', 'latitude', 'longitude', 'status', 'sort_order',
        ]))->assertSessionHasNoErrors();
        $this->assertSame($studio->phones, $studio->fresh()->phones);
    }

    public function test_gallery_upload_cover_order_alt_and_removal_are_scoped_and_files_retained(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $other = Studio::create($this->fields(['slug' => 'other']));
        $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [
            UploadedFile::fake()->image('first.png', 400, 300),
            UploadedFile::fake()->image('second.jpg', 400, 300),
        ]])->assertSessionHasNoErrors();
        $images = $studio->images()->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_cover);
        foreach ($images as $image) {
            Storage::disk('studio_images')->assertExists($image->path);
            $this->assertStringStartsWith($studio->id.'/', $image->path);
            $this->assertSame('image/jpeg', getimagesizefromstring(Storage::disk('studio_images')->get($image->path))['mime']);
        }
        $target = '/admin/studios/'.$studio->id.'/images/'.$images[1]->id;
        $this->patch($target, ['alt_text' => 'Studio reception', 'sort_order' => 0, 'is_cover' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue($images[1]->fresh()->is_cover);
        $this->assertFalse($images[0]->fresh()->is_cover);
        $this->assertSame('Studio reception', $images[1]->fresh()->alt_text);
        $this->assertSame($images[1]->id, $studio->images()->first()->id);
        $this->patch('/admin/studios/'.$other->id.'/images/'.$images[1]->id, ['sort_order' => 0])->assertNotFound();
        $this->delete('/admin/studios/'.$other->id.'/images/'.$images[1]->id)->assertNotFound();
        $this->delete($target)->assertRedirect();
        $this->assertSame(1, $studio->images()->count());
        $this->assertTrue($images[0]->fresh()->is_cover);
        Storage::disk('studio_images')->assertExists($images[1]->path);
    }

    public function test_invalid_gallery_uploads_and_orders_leave_storage_and_records_unchanged(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        foreach ([
            UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->image('tiny.jpg', 20, 20),
            UploadedFile::fake()->image('wide.jpg', 2500, 200),
            UploadedFile::fake()->image('large.jpg', 300, 300)->size(6000),
        ] as $file) {
            $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [$file]])
                ->assertSessionHasErrors('images.0');
        }
        $this->assertSame([], Storage::disk('studio_images')->allFiles());
        $this->assertSame(0, $studio->images()->count());
    }

    public function test_studio_forms_remain_csrf_protected(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }

            protected function hasValidOrigin($request)
            {
                return false;
            }
        });
        $this->actingAs($this->actor());
        $this->post('/admin/studios', $this->fields())->assertStatus(419);
        $this->assertSame(0, Studio::count());
    }

    public function test_gallery_storage_failure_returns_validation_errors_without_partial_entries(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        Storage::disk('studio_images');
        Storage::shouldReceive('disk')->with('studio_images')->andReturn(new class
        {
            public function put($path, $bytes): bool
            {
                return false;
            }

            public function delete($path): bool
            {
                return true;
            }
        });
        $this->post('/admin/studios/'.$studio->id.'/images', [
            'images' => [UploadedFile::fake()->image('failure.jpg', 400, 300)],
        ])->assertSessionHasErrors('images');
        $this->assertSame(0, $studio->images()->count());
    }

    public function test_public_and_private_studio_content_is_escaped(): void
    {
        $studio = Studio::create($this->fields(['name' => '<script>CMS-only</script>', 'status' => 'active']));
        $this->get('/locate-a-store')->assertOk()
            ->assertSee('&lt;script&gt;CMS-only&lt;/script&gt;', false)
            ->assertDontSee('<script>CMS-only</script>', false);
        $this->actingAs($this->actor());
        $this->get('/admin/studios/'.$studio->id)->assertOk()
            ->assertSee('&lt;script&gt;CMS-only&lt;/script&gt;', false)
            ->assertDontSee('<script>CMS-only</script>', false);
    }

    public function test_admin_edits_status_and_order_are_reflected_in_the_public_locator(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        Studio::create($this->fields([
            'name' => 'Other public studio', 'slug' => 'other-public-studio',
            'status' => 'active', 'sort_order' => 2,
        ]));
        $this->get('/locate-a-store')->assertOk()->assertDontSee('Synthetic Studio');
        $edited = $this->fields([
            'name' => 'Updated public studio', 'address' => 'Updated Noida address',
            'state' => 'Uttar Pradesh', 'status' => 'active',
            'phones' => ['(+91) 99887 76655', '(+91) 98765 43210'],
            'map_url' => 'https://maps.app.goo.gl/ApprovedLocation', 'sort_order' => 1,
        ]);
        $this->put('/admin/studios/'.$studio->id, $edited)->assertSessionHasNoErrors();
        $this->get('/locate-a-store')->assertOk()->assertSee('Updated public studio')
            ->assertSee('Updated Noida address')->assertSee('Uttar Pradesh')
            ->assertSee('https://maps.app.goo.gl/ApprovedLocation', false)
            ->assertViewHas('studios', fn ($rows) => $rows[0]['phones'] === $edited['phones']);
        $this->put('/admin/studios/'.$studio->id, array_replace($edited, ['sort_order' => 3]))
            ->assertSessionHasNoErrors();
        $this->get('/locate-a-store')->assertOk()->assertViewHas(
            'studios',
            fn ($rows) => $rows->pluck('name')->all() === ['Other public studio', 'Updated public studio']
        );
        $this->put('/admin/studios/'.$studio->id, array_replace($edited, ['status' => 'inactive']))
            ->assertSessionHasNoErrors();
        $this->get('/locate-a-store')->assertOk()->assertDontSee('Updated public studio');
    }
}
