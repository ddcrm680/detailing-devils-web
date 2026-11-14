<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\StudioImage;
use App\Models\User;
use DOMDocument;
use DOMXPath;
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
        $this->get('/admin/studios/create')->assertOk()->assertSee('Create Studio');
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
        $this->get('/admin/studios/'.$studio->id)->assertOk()->assertSee('Location and contact');
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
            'slug_mode' => 'manual', 'slug' => '../bad', 'phones' => ['abc'], 'map_url' => 'https://evil.example/maps',
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

    public function test_gallery_upload_cover_order_alt_and_removal_are_scoped_and_files_deleted(): void
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
        Storage::disk('studio_images')->assertMissing($images[1]->path);
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

    public function test_editor_groups_fields_keeps_one_save_action_and_defaults_new_studios_to_draft(): void
    {
        $this->actingAs($this->actor());
        $response = $this->get('/admin/studios/create')->assertOk()
            ->assertSee('Studio Details')->assertSee('Location')->assertSee('Contact Information')
            ->assertSee('Visibility &amp; Display Order', false)
            ->assertDontSee('Include the country code. The first number is the Primary contact.')
            ->assertDontSee('Active studios appear on the public Studio Locator.')
            ->assertDontSee('Lower display-order numbers appear first.')
            ->assertDontSee('Save the studio to add photos.')
            ->assertDontSee('Phase 1');
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-studio-form]//button[@type="submit"]')->length);
        $this->assertSame('draft', $xpath->evaluate('string(//select[@name="status"]/option[@selected]/@value)'));
        $this->assertSame(1, $xpath->query('//form[@data-studio-form]//a[text()="Cancel"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-save-state][@role="status"]')->length);
        $this->assertSame(0, Studio::count());
    }

    public function test_country_dropdown_defaults_to_india_and_preserves_saved_and_old_selections(): void
    {
        $this->actingAs($this->actor());
        $selectedCountry = function ($response): string {
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);
            $this->assertSame(1, $xpath->query('//details[@class="studio-coordinates"]//select[@name="country_code"]')->length);
            $this->assertSame(0, $xpath->query('//input[@name="country_code"]')->length);
            $this->assertSame('India', $xpath->evaluate('string(//select[@name="country_code"]/option[@value="IN"])'));
            $this->assertSame('United States', $xpath->evaluate('string(//select[@name="country_code"]/option[@value="US"])'));
            $this->assertSame(1, $xpath->query('//select[@name="country_code"]/option[@selected]')->length);

            return $xpath->evaluate('string(//select[@name="country_code"]/option[@selected]/@value)');
        };

        $this->assertSame('IN', $selectedCountry($this->get('/admin/studios/create')->assertOk()));
        $studio = Studio::create($this->fields());
        foreach ([null, 'IN', 'FR', 'XX'] as $country) {
            $studio->update(['country_code' => $country]);
            $this->assertSame($country ?? '', $selectedCountry($this->get('/admin/studios/'.$studio->id.'/edit')->assertOk()));
            $this->assertSame($country, $studio->fresh()->country_code);
        }
        foreach (['US', '', null] as $country) {
            $this->withSession(['_old_input' => ['country_code' => $country]]);
            $this->assertSame($country ?? '', $selectedCountry($this->get('/admin/studios/create')->assertOk()));
        }
    }

    public function test_country_selection_keeps_existing_validation_and_nullable_iso_storage(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields(['country_code' => 'FR']));
        $response = $this->from('/admin/studios/'.$studio->id.'/edit')->followingRedirects()
            ->put('/admin/studios/'.$studio->id, $this->fields(['country_code' => 'India']))
            ->assertOk()->assertSee('country_code-error', false);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame('India', $xpath->evaluate('string(//select[@name="country_code"]/option[@selected]/@value)'));
        $this->assertSame(1, $xpath->query('//select[@name="country_code"][@aria-invalid="true"]')->length);
        $this->assertSame(1, $xpath->query('//details[@class="studio-coordinates"][@open]')->length);
        $this->assertSame('FR', $studio->fresh()->country_code);
        foreach (['US', null] as $country) {
            $this->put('/admin/studios/'.$studio->id, $this->fields(['country_code' => $country]))
                ->assertSessionHasNoErrors();
            $this->assertSame($country, $studio->fresh()->country_code);
        }
    }

    public function test_rejected_studio_details_show_inline_errors_and_preserve_values_without_saving(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $response = $this->from('/admin/studios/'.$studio->id.'/edit')->followingRedirects()
            ->put('/admin/studios/'.$studio->id, $this->fields([
                'phones' => ['abc'], 'map_url' => 'https://untrusted.example/maps',
                'latitude' => 91, 'longitude' => 181, 'status' => 'unknown', 'sort_order' => -1,
            ]))->assertOk()->assertSee('data-validation-errors', false)
            ->assertSee('data-unsaved-input', false);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        foreach (['studio-phone-0', 'map_url', 'latitude', 'longitude', 'studio-status', 'sort_order'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"][@aria-invalid="true"]')->length, $id);
        }
        foreach (['phone-error-0', 'map-error', 'latitude-error', 'longitude-error', 'status-error', 'order-error'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
        }
        $this->assertSame('abc', $xpath->evaluate('string(//input[@name="phones[0]"]/@value)'));
        $this->assertSame(1, $xpath->query('//details[@class="studio-coordinates"][@open]')->length);
        $this->assertSame($this->fields()['phones'], $studio->fresh()->phones);
        $this->assertSame('draft', $studio->fresh()->status);
    }

    public function test_gallery_errors_are_inline_and_do_not_leak_into_studio_order_or_other_images(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $images = [];
        foreach ([true, false] as $cover) {
            $image = new StudioImage;
            $image->forceFill([
                'path' => 'fixture-'.count($images).'.jpg', 'width' => 400, 'height' => 300,
                'is_cover' => $cover, 'sort_order' => count($images) + 1, 'alt_text' => 'Original image text',
            ]);
            $studio->images()->save($image);
            $images[] = $image;
        }
        $this->from('/admin/studios/'.$studio->id.'/gallery')->followingRedirects()
            ->post('/admin/studios/'.$studio->id.'/images', [
                'images' => [UploadedFile::fake()->image('tiny.jpg', 20, 20)],
            ])->assertOk()->assertSee('id="upload-errors"', false)
            ->assertSee('aria-describedby="upload-help upload-errors"', false);
        $response = $this->from('/admin/studios/'.$studio->id.'/gallery')->followingRedirects()
            ->patch('/admin/studios/'.$studio->id.'/images/'.$images[1]->id, [
                '_image' => $images[1]->id, 'alt_text' => 'Unsaved image text', 'sort_order' => -1, 'is_cover' => 1,
            ])->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@id="sort_order"]')->length);
        $this->assertSame('-1', $xpath->evaluate('string(//input[@id="order-'.$images[1]->id.'"]/@value)'));
        $this->assertSame('Unsaved image text', $xpath->evaluate('string(//input[@id="alt-'.$images[1]->id.'"]/@value)'));
        $this->assertSame('Original image text', $xpath->evaluate('string(//input[@id="alt-'.$images[0]->id.'"]/@value)'));
        $this->assertSame(1, $xpath->query('//*[@id="image-order-error-'.$images[1]->id.'"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="order-error"]')->length);
        $this->assertTrue($images[0]->fresh()->is_cover);
        $this->assertSame(2, $studio->images()->count());
    }

    public function test_dedicated_gallery_navigation_counts_filters_and_permissions(): void
    {
        $studio = Studio::create($this->fields());
        $other = Studio::create(array_replace($this->fields(), ['name' => 'Other studio', 'slug' => 'other-studio', 'state' => 'Punjab']));
        $image = new StudioImage;
        $image->forceFill(['path' => 'fixture.jpg', 'width' => 400, 'height' => 300, 'is_cover' => true, 'sort_order' => 1]);
        $studio->images()->save($image);
        $this->get('/admin/studio-gallery')->assertRedirect('/admin/login');
        $this->actingAs($this->actor('seo_admin'));
        $this->get('/admin/studio-gallery')->assertForbidden();
        $this->get('/admin/studios/'.$studio->id.'/gallery')->assertForbidden();
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->actor($role));
            $this->get('/admin/studio-gallery?state=Punjab')->assertOk()
                ->assertViewHas('studios', fn ($studios) => $studios->pluck('id')->all() === [$other->id]);
            $this->get('/admin/studio-gallery?q='.$studio->slug)->assertOk()
                ->assertViewHas('studios', fn ($studios) => $studios->count() === 1 && $studios->first()->images_count === 1);
            $this->get('/admin/studios/'.$studio->id.'/edit')->assertOk()
                ->assertSee(route('admin.studios.gallery.show', $studio), false)
                ->assertDontSee('data-gallery-files', false);
            $this->get('/admin/studios/'.$studio->id.'/gallery')->assertOk()
                ->assertSee('Upload Photos')->assertSee('Cover photo')->assertSee('data-gallery-dropzone', false);
            $this->get('/admin/studios/'.$other->id.'/gallery')->assertOk()->assertSee('No photos added yet.');
        }
    }

    public function test_batch_metadata_order_and_cover_are_saved_together(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $upload = fn ($data) => $this->postJson('/admin/studios/'.$studio->id.'/images', $data);
        $upload([
            'images' => [UploadedFile::fake()->image('first.jpg', 400, 300)],
            'descriptions' => ['First photo'],
        ])->assertOk()->assertJsonPath('redirect', route('admin.studios.gallery.show', $studio));
        $first = $studio->images()->first();
        $this->assertTrue($first->is_cover);
        $upload([
            'images' => [UploadedFile::fake()->image('third.jpg', 400, 300), UploadedFile::fake()->image('second.jpg', 400, 300)],
            'descriptions' => ['Third moved before second', 'Second photo'],
        ])->assertOk();
        $this->assertSame(['First photo', 'Third moved before second', 'Second photo'], $studio->images()->pluck('alt_text')->all());
        $this->assertSame([1, 2, 3], $studio->images()->pluck('sort_order')->all());
        $this->assertTrue($first->fresh()->is_cover);
        $upload([
            'images' => [UploadedFile::fake()->image('fourth.jpg', 400, 300), UploadedFile::fake()->image('cover.jpg', 400, 300)],
            'descriptions' => ['Fourth', 'Chosen cover'], 'cover_index' => 1,
        ])->assertOk();
        $this->assertFalse($first->fresh()->is_cover);
        $this->assertSame(1, $studio->images()->where('is_cover', true)->count());
        $this->assertSame('Chosen cover', $studio->images()->where('is_cover', true)->first()->alt_text);
    }

    public function test_invalid_batch_metadata_and_files_are_atomic_and_can_be_retried(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $url = '/admin/studios/'.$studio->id.'/images';
        foreach ([['descriptions' => [str_repeat('x', 256)], 'cover_index' => 0], ['descriptions' => [], 'cover_index' => 3]] as $metadata) {
            $this->postJson($url, ['images' => [UploadedFile::fake()->image('photo.jpg', 400, 300)]] + $metadata)->assertUnprocessable();
            $this->assertSame(0, $studio->images()->count());
            $this->assertSame([], Storage::disk('studio_images')->allFiles());
        }
        $this->postJson($url, [
            'images' => [UploadedFile::fake()->image('valid.jpg', 400, 300), UploadedFile::fake()->image('tiny.jpg', 20, 20)],
            'descriptions' => ['Valid', 'Too small'], 'cover_index' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('images.1');
        $this->assertSame(0, $studio->images()->count());
        $this->assertSame([], Storage::disk('studio_images')->allFiles());
        $this->postJson($url, [
            'images' => [UploadedFile::fake()->image('valid.jpg', 400, 300), UploadedFile::fake()->image('fixed.jpg', 400, 300)],
            'descriptions' => ['Valid', 'Fixed'], 'cover_index' => 1,
        ])->assertOk();
        $this->assertSame('Fixed', $studio->images()->where('is_cover', true)->first()->alt_text);
    }

    public function test_failed_batch_rolls_back_cover_metadata_and_cleans_new_files(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [UploadedFile::fake()->image('existing.jpg', 400, 300)]])->assertSessionHasNoErrors();
        $existing = $studio->images()->first();
        $files = Storage::disk('studio_images')->allFiles();
        $event = 'eloquent.creating: '.StudioImage::class;
        $created = 0;
        app('events')->listen($event, function () use (&$created) {
            if (++$created === 2) {
                throw new \RuntimeException('Isolated insertion failure');
            }
        });
        try {
            $this->postJson('/admin/studios/'.$studio->id.'/images', [
                'images' => [UploadedFile::fake()->image('new-one.jpg', 400, 300), UploadedFile::fake()->image('new-two.jpg', 400, 300)],
                'descriptions' => ['One', 'Two'], 'cover_index' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('images');
        } finally {
            app('events')->forget($event);
        }
        $this->assertSame(1, $studio->images()->count());
        $this->assertTrue($existing->fresh()->is_cover);
        $this->assertSame($files, Storage::disk('studio_images')->allFiles());
    }

    public function test_saved_photo_arrows_reorder_ties_and_stop_at_boundaries(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [
            UploadedFile::fake()->image('one.jpg', 400, 300), UploadedFile::fake()->image('two.jpg', 400, 300),
        ]])->assertSessionHasNoErrors();
        $images = $studio->images()->get();
        $studio->images()->update(['sort_order' => 0]);
        $url = '/admin/studios/'.$studio->id.'/images/'.$images[1]->id;
        $this->patch($url, ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame([$images[1]->id, $images[0]->id], $studio->images()->pluck('id')->all());
        $this->patch($url, ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame($images[1]->id, $studio->images()->first()->id);
        $this->assertTrue($images[0]->fresh()->is_cover);
        $this->get('/admin/studios/'.$studio->id.'/gallery')->assertOk()->assertSee('aria-label="Move Up"', false);
    }

    public function test_missing_photo_file_is_safe_and_unsafe_or_shared_paths_are_not_deleted(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [UploadedFile::fake()->image('one.jpg', 400, 300)]])->assertSessionHasNoErrors();
        $image = $studio->images()->first();
        $path = $image->path;
        $url = '/admin/studios/'.$studio->id.'/images/'.$image->id;
        $image->forceFill(['path' => '../shared.jpg'])->save();
        $this->delete($url)->assertSessionHasErrors('gallery');
        $image->forceFill(['path' => $path])->save();
        Storage::disk('studio_images')->delete($path);
        $this->delete($url)->assertSessionHasNoErrors();
        $this->assertNull($image->fresh());
    }

    public function test_photo_filesystem_failure_rolls_back_record_and_cover(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $this->post('/admin/studios/'.$studio->id.'/images', ['images' => [
            UploadedFile::fake()->image('one.jpg', 400, 300), UploadedFile::fake()->image('two.jpg', 400, 300),
        ]])->assertSessionHasNoErrors();
        $images = $studio->images()->get();
        $disk = Storage::disk('studio_images');
        $files = $disk->allFiles();
        $failingDisk = new class($disk)
        {
            public bool $deleteBeforeFailure = false;

            public function __construct(private $disk) {}

            public function path($path)
            {
                return $this->disk->path($path);
            }

            public function exists($path)
            {
                return $this->disk->exists($path);
            }

            public function get($path)
            {
                return $this->disk->get($path);
            }

            public function delete($path)
            {
                if ($this->deleteBeforeFailure) {
                    $this->disk->delete($path);
                    throw new \RuntimeException('Isolated failure after file removal');
                }

                return false;
            }

            public function put($path, $bytes)
            {
                return $this->disk->put($path, $bytes);
            }
        };
        Storage::shouldReceive('disk')->with('studio_images')->andReturn($failingDisk);
        $this->delete('/admin/studios/'.$studio->id.'/images/'.$images[0]->id)->assertSessionHasErrors('gallery');
        $this->assertSame(2, $studio->images()->count());
        $this->assertTrue($images[0]->fresh()->is_cover);
        $this->assertFalse($images[1]->fresh()->is_cover);
        $this->assertSame($files, $disk->allFiles());
        $original = $disk->get($images[0]->path);
        $failingDisk->deleteBeforeFailure = true;
        $this->delete('/admin/studios/'.$studio->id.'/images/'.$images[0]->id)->assertSessionHasErrors('gallery');
        $this->assertSame(2, $studio->images()->count());
        $this->assertTrue($images[0]->fresh()->is_cover);
        $this->assertSame($original, $disk->get($images[0]->path));
    }

    public function test_model_generates_unique_slugs_and_only_explicit_manual_updates_change_them(): void
    {
        $this->actingAs($this->actor());
        $fields = $this->fields(['name' => 'Detailing Devils Jaipur']);
        unset($fields['slug']);
        $first = Studio::create($fields);
        $this->assertSame('detailing-devils-jaipur', $first->slug);
        $this->post('/admin/studios', array_replace($fields, ['name' => 'Detailing Devils Jaipur!', 'slug_mode' => 'auto', 'slug' => 'untrusted-preview']))->assertSessionHasNoErrors();
        $this->assertTrue(Studio::where('slug', 'detailing-devils-jaipur-2')->exists());
        $this->post('/admin/studios', array_replace($fields, ['name' => 'Custom Jaipur', 'slug_mode' => 'manual', 'slug' => 'jaipur-cover']))->assertSessionHasNoErrors();
        $this->post('/admin/studios', $fields + ['slug_mode' => 'manual', 'slug' => 'jaipur-cover'])->assertSessionHasErrors('slug');
        $this->post('/admin/studios', $fields + ['slug_mode' => 'manual', 'slug' => '../invalid'])->assertSessionHasErrors('slug');
        $this->put('/admin/studios/'.$first->id, array_replace($fields, ['name' => 'Renamed', 'slug' => 'ignored', 'slug_mode' => 'preserve']))->assertSessionHasNoErrors();
        $this->assertSame('detailing-devils-jaipur', $first->fresh()->slug);
        $this->put('/admin/studios/'.$first->id, $fields + ['slug' => 'jaipur-custom', 'slug_mode' => 'manual'])->assertSessionHasNoErrors();
        $this->assertSame('jaipur-custom', $first->fresh()->slug);
        $this->put('/admin/studios/'.$first->id, $fields + ['slug' => 'jaipur-cover', 'slug_mode' => 'manual'])->assertSessionHasErrors('slug');
        $this->assertSame('jaipur-custom', $first->fresh()->slug);
        $this->assertSame('studio', Studio::generateUniqueSlug('!!!'));
        $this->assertLessThanOrEqual(191, strlen(Studio::generateUniqueSlug(str_repeat('long name ', 30))));
    }

    public function test_studio_names_are_unique_ignoring_case_and_surrounding_whitespace(): void
    {
        $this->actingAs($this->actor());
        $first = Studio::create($this->fields(['name' => "  Detailing Devils Jaipur\t"]));
        foreach (['Detailing Devils Jaipur', 'detailing devils JAIPUR', "\t DETAILING DEVILS JAIPUR \n"] as $name) {
            $this->post('/admin/studios', $this->fields(['name' => $name, 'slug' => null, 'slug_mode' => 'auto']))
                ->assertSessionHasErrors(['name' => 'A studio with this name already exists.']);
        }
        $this->assertSame(1, Studio::count());
        $this->put('/admin/studios/'.$first->id, $this->fields(['name' => 'Detailing Devils Jaipur']))->assertSessionHasNoErrors();
        $this->assertSame('synthetic-studio', $first->fresh()->slug);
        $other = Studio::create($this->fields(['name' => 'Other studio', 'slug' => 'other-studio']));
        $this->put('/admin/studios/'.$other->id, $this->fields(['name' => ' DETAILING DEVILS JAIPUR ', 'slug_mode' => 'preserve']))
            ->assertSessionHasErrors(['name' => 'A studio with this name already exists.']);
        $this->assertSame('Other studio', $other->fresh()->name);
        $this->assertSame('other-studio', $other->fresh()->slug);
        $this->post('/admin/studios', $this->fields(['name' => 'Distinct name', 'slug_mode' => 'manual', 'slug' => 'custom-distinct']))->assertSessionHasNoErrors();
        $this->assertTrue(Studio::where('name', 'Distinct name')->where('slug', 'custom-distinct')->exists());
    }

    public function test_glide_assets_are_scoped_to_multi_photo_private_previews(): void
    {
        $this->actingAs($this->actor());
        $studio = Studio::create($this->fields());
        $preview = '/admin/studios/'.$studio->id;
        $this->get($preview)->assertOk()->assertDontSee('/assets/vendor/glide.min.js', false)->assertDontSee('data-photo-play', false);
        $this->post($preview.'/images', ['images' => [UploadedFile::fake()->image('one.jpg', 400, 300)]])->assertSessionHasNoErrors();
        $this->get($preview)->assertOk()->assertDontSee('/assets/vendor/glide.min.js', false)->assertDontSee('data-photo-play', false);
        $this->post($preview.'/images', ['images' => [UploadedFile::fake()->image('two.jpg', 400, 300)]])->assertSessionHasNoErrors();
        $this->get($preview)->assertOk()->assertSee('/assets/vendor/glide.min.js', false)
            ->assertSee('/assets/vendor/glide.core.min.css', false)->assertSee('data-glide-el="track"', false)
            ->assertSee('data-photo-play', false)->assertSee('aria-label="Pause slideshow"', false);
        $this->get($preview.'/gallery')->assertOk()->assertDontSee('/assets/vendor/glide.min.js', false)
            ->assertSee('data-photo-original', false)->assertSee('data-photo-save', false)
            ->assertSee('data-icon-loading', false)->assertSee('data-submit-scope=".studio-gallery-card"', false)
            ->assertSee('aria-label="Remove Photo"', false);
    }
}
