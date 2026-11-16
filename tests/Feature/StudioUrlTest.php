<?php

namespace Tests\Feature;

use App\Models\ContentUrl;
use App\Models\Studio;
use App\Models\StudioImage;
use App\Models\User;
use App\Services\ContentUrls;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoPublisher;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSnapshot;
use App\Services\StudioUrls;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudioUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
            'session.driver' => 'array', 'cache.default' => 'array', 'app.debug' => false,
        ]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_02_000000_add_role_to_users_table.php',
            '2026_11_02_000001_create_website_seo_store.php',
            '2026_11_12_000000_create_studio_cms_tables.php',
            '2026_11_16_000000_create_content_urls.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $this->publication();
    }

    private function actor(string $role = 'admin'): User
    {
        $actor = new User(['name' => 'Isolated URL editor', 'email' => uniqid().'@example.test', 'password' => 'Fixture-password']);
        $actor->forceFill(['role' => $role])->save();

        return $actor;
    }

    private function studio(array $fields = []): Studio
    {
        return Studio::create(array_replace([
            'name' => 'Isolated Jaipur', 'address' => '12 Example Road', 'state' => 'Rajasthan',
            'phones' => ['+91 98765 43210', '+91 99887 76655'], 'status' => 'active', 'sort_order' => 5,
        ], $fields));
    }

    private function publication(array $overrides = []): void
    {
        $payload = app(SeoPublicationCodec::class)->encode(new SeoSnapshot($overrides));
        DB::table('seo_metadata')->updateOrInsert(['id' => 1], [
            'origin' => config('seo.origin'), 'version' => 0, 'payload' => $payload,
            'payload_hash' => hash('sha256', $payload), 'sitemap_xml' => '',
            'sitemap_hash' => hash('sha256', ''), 'updated_at' => now(),
        ]);
        $this->app->forgetInstance(SeoResolver::class);
    }

    private function change(Studio $studio, string $slug, ?string $expected = null): void
    {
        app(StudioUrls::class)->change($studio, $slug, $expected ?? $studio->fresh()->slug, $this->actor());
        $studio->refresh();
    }

    private function rejected(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Expected URL validation failure.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->errors()['slug'][0]);
        }
    }

    public function test_creation_reserves_automatic_custom_and_all_status_urls_and_locks_on_first_activation(): void
    {
        $studio = $this->studio(['status' => 'draft']);
        $this->assertNull($studio->url_locked_at);
        $studio->update(['slug' => 'custom-before-publication']);
        $this->assertSame(2, ContentUrl::count());
        $this->assertSame('isolated-jaipur-2', $this->studio(['status' => 'inactive'])->slug);
        $studio->update(['status' => 'active']);
        $locked = $studio->url_locked_at;
        $this->assertNotNull($locked);
        $studio->update(['status' => 'draft', 'name' => 'Renamed Jaipur']);
        $this->assertSame('custom-before-publication', $studio->slug);
        $this->assertTrue($studio->url_locked_at->equalTo($locked));
        $this->rejected(fn () => $studio->update(['slug' => 'bypass']), 'Use Change URL');
        $this->assertSame('custom-before-publication', $studio->fresh()->slug);
    }

    public function test_both_existing_management_roles_can_confirm_url_changes_without_new_permissions(): void
    {
        $studio = $this->studio();
        $endpoint = '/admin/studios/'.$studio->id.'/url';
        $this->post($endpoint)->assertRedirect(route('admin.login'));
        foreach (['seo_admin', 'unassigned'] as $role) {
            $this->actingAs($this->actor($role))->post($endpoint, [
                'slug' => 'forbidden', 'expected_slug' => $studio->slug, 'confirm_url_change' => '1',
            ])->assertForbidden();
        }
        foreach (['admin', 'super_admin'] as $role) {
            $actor = $this->actor($role);
            $slug = 'reviewed-'.str_replace('_', '-', $role);
            $this->actingAs($actor)->get('/admin/studios/'.$studio->id.'/edit')->assertOk()
                ->assertSee('Change URL')->assertSee('name="slug_mode" value="preserve"', false)
                ->assertDontSee('data-slug-editor', false);
            $this->post($endpoint, [
                'slug' => $slug, 'expected_slug' => $studio->fresh()->slug, 'confirm_url_change' => '1',
            ])->assertSessionHasNoErrors()->assertRedirect(route('admin.studios.edit', $studio));
            $this->assertSame($actor->id, ContentUrl::where('path', '/studios/'.$slug)->firstOrFail()->created_by);
        }
    }

    public function test_confirmation_format_and_stale_submissions_are_enforced_server_side(): void
    {
        $studio = $this->studio();
        $endpoint = '/admin/studios/'.$studio->id.'/url';
        $this->actingAs($this->actor());
        $this->post($endpoint, ['slug' => 'new', 'expected_slug' => $studio->slug])
            ->assertSessionHasErrors('confirm_url_change');
        foreach (['Uppercase', 'double--dash', '../escape', 'https://example.test', 'trailing-', 'x'."\n"] as $invalid) {
            // Exercise raw service callers as well as request validation.
            $this->rejected(fn () => app(StudioUrls::class)->change($studio, $invalid, $studio->slug, $this->actor()), 'lowercase');
        }
        $original = $studio->slug;
        $stale = $studio->fresh();
        $this->change($studio, 'changed');
        $this->post($endpoint, ['slug' => 'repeated', 'expected_slug' => $original, 'confirm_url_change' => '1'])
            ->assertSessionHasErrors('slug');
        $this->rejected(fn () => $stale->update(['address' => 'Stale edit']), 'Reload');
        $this->assertSame('12 Example Road', $studio->fresh()->address);
        $this->rejected(fn () => $this->change($studio, 'changed'), 'different URL');
    }

    public function test_ordinary_edits_cannot_bypass_confirmation_but_unpublished_slugs_remain_editable(): void
    {
        $this->actingAs($this->actor());
        $studio = $this->studio();
        $fields = $studio->only(['name', 'slug', 'address', 'state', 'phones', 'status', 'sort_order']);
        $fields['slug_mode'] = 'manual';
        $fields['slug'] = 'bypassed-url';
        $this->put('/admin/studios/'.$studio->id, $fields)->assertSessionHasErrors('slug');
        $this->assertSame('isolated-jaipur', $studio->fresh()->slug);
        $studio->update(['status' => 'inactive']);
        $fields['status'] = 'inactive';
        $this->put('/admin/studios/'.$studio->id, $fields)->assertSessionHasErrors('slug');
        $draft = $this->studio(['status' => 'draft', 'name' => 'Never published']);
        $fields = $draft->only(['name', 'slug', 'address', 'state', 'phones', 'status', 'sort_order']);
        $fields['slug_mode'] = 'manual';
        $fields['slug'] = 'draft-custom-url';
        $this->put('/admin/studios/'.$draft->id, $fields)->assertSessionHasNoErrors();
        $this->assertSame('draft-custom-url', $draft->fresh()->slug);
        $fields['slug'] = ['invalid'];
        $this->put('/admin/studios/'.$draft->id, $fields)->assertSessionHasErrors('slug');
    }

    public function test_reservation_failure_rolls_back_new_studio_and_url_together(): void
    {
        ContentUrl::creating(function () {
            throw ValidationException::withMessages(['slug' => 'Fixture reservation failed.']);
        });
        $this->rejected(fn () => $this->studio(), 'reservation failed');
        $this->assertSame(0, Studio::count());
        $this->assertSame(0, ContentUrl::count());
    }

    public function test_all_history_redirects_directly_to_latest_only_while_active_and_never_enters_sitemap(): void
    {
        $studio = $this->studio(['slug' => 'first']);
        $this->change($studio, 'second');
        $this->change($studio, 'third');
        foreach (['first', 'second'] as $old) {
            $this->get('/studios/'.$old)->assertStatus(301)
                ->assertHeader('Location', config('seo.origin').'/studios/third')
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        }
        $this->get('/studios/third')->assertOk()->assertSee('rel="canonical" href="'.config('seo.origin').'/studios/third"', false);
        $this->app->forgetInstance(SeoResolver::class);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/studios/third', false)
            ->assertDontSee('/studios/first', false)->assertDontSee('/studios/second', false);
        $this->get('/locate-a-store')->assertOk()->assertSee('/studios/third', false)->assertDontSee('/studios/first', false);
        foreach (['draft', 'inactive'] as $status) {
            $studio->update(['status' => $status]);
            foreach (['first', 'second', 'third'] as $slug) {
                $this->get('/studios/'.$slug)->assertNotFound();
            }
        }
        $studio->update(['status' => 'active']);
        $this->get('/studios/first')->assertStatus(301);
        $this->get('/studios/not-a-real-studio')->assertNotFound();
        $this->get('/studios/Uppercase')->assertNotFound();
        $this->get('/studios/double--hyphen')->assertNotFound();
    }

    public function test_historical_paths_cannot_be_reused_even_by_the_same_owner_and_ownership_is_database_unique(): void
    {
        $studio = $this->studio(['slug' => 'reserved']);
        $this->change($studio, 'latest');
        $this->rejected(fn () => $this->change($studio, 'reserved'), 'reserved');
        $this->rejected(fn () => $this->studio(['slug' => 'reserved', 'name' => 'Other']), 'reserved');
        $this->assertSame(1, Studio::count());
        $this->rejected(fn () => app(ContentUrls::class)->reserve('blog', 1, '/studios/reserved'), 'reserved');
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('content_urls')->insert(['content_type' => 'blog', 'content_id' => 2, 'path' => '/studios/reserved']);
    }

    public function test_registry_ownership_is_immutable_and_redirect_targets_do_not_trust_request_hosts(): void
    {
        $studio = $this->studio(['slug' => 'trusted-old']);
        $owner = ContentUrl::firstOrFail();
        foreach ([fn () => $owner->update(['path' => '/studios/reassigned']), fn () => $owner->delete()] as $operation) {
            try {
                $operation();
                $this->fail('A reservation was changed.');
            } catch (\LogicException) {
                $this->assertSame('/studios/trusted-old', $owner->fresh()->path);
            }
        }
        $this->change($studio, 'trusted-new');
        $this->withHeader('Host', 'untrusted.example.test')->get('/studios/trusted-old')
            ->assertStatus(301)->assertHeader('Location', config('seo.origin').'/studios/trusted-new');
    }

    public function test_only_url_and_modification_time_change_while_gallery_and_seo_history_stay_intact(): void
    {
        $studio = $this->studio();
        $photo = new StudioImage;
        $photo->forceFill(['path' => $studio->id.'/fixture.jpg', 'width' => 400, 'height' => 300]);
        $studio->images()->save($photo);
        $before = $studio->refresh()->getRawOriginal();
        $this->publication([new SeoOverride('studio.'.$studio->id, ['title' => 'Reviewed Jaipur title'], true)]);
        $metadata = (array) DB::table('seo_metadata')->first();
        $this->change($studio, 'reviewed-jaipur');
        $after = $studio->getRawOriginal();
        unset($before['slug'], $before['updated_at'], $after['slug'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertSame($studio->id, $photo->fresh()->studio_id);
        $this->assertSame($metadata, (array) DB::table('seo_metadata')->first());
        $this->assertSame(0, DB::table('seo_revisions')->count());
        $this->get('/studios/reviewed-jaipur')->assertOk()->assertSee('<title>Reviewed Jaipur title</title>', false);
    }

    public function test_explicit_own_and_inbound_canonical_aliases_block_without_overwriting_seo_decisions(): void
    {
        $studio = $this->studio();
        foreach ([
            new SeoOverride('studio.'.$studio->id, ['canonical' => config('seo.origin').'/locate-a-store'], true, true),
            new SeoOverride('home', ['canonical' => config('seo.origin').'/studios/'.$studio->slug], true, true),
        ] as $alias) {
            $this->publication([$alias]);
            $before = (array) DB::table('seo_metadata')->first();
            $this->rejected(fn () => $this->change($studio, 'blocked'), 'canonical');
            $this->assertSame($before, (array) DB::table('seo_metadata')->first());
            $this->assertSame(1, ContentUrl::count());
        }
    }

    public function test_pending_own_and_inbound_seo_drafts_block_and_only_latest_revision_matters(): void
    {
        $studio = $this->studio();
        $actor = $this->actor();
        foreach (['studio.'.$studio->id, 'home'] as $id) {
            DB::table('seo_revisions')->insert([
                'page_id' => $id, 'kind' => 'draft', 'fields' => json_encode(['canonical' => config('seo.origin').'/studios/'.$studio->slug]),
                'base_version' => 0, 'administrator_id' => $actor->id, 'created_at' => now(),
            ]);
            $this->rejected(fn () => $this->change($studio, 'blocked'), 'pending SEO draft');
            DB::table('seo_revisions')->insert([
                'page_id' => $id, 'kind' => 'published', 'fields' => '{}',
                'base_version' => 0, 'administrator_id' => $actor->id, 'created_at' => now(),
            ]);
        }
        $this->change($studio, 'allowed-after-review');
        $this->assertSame(4, DB::table('seo_revisions')->count());
    }

    public function test_stale_seo_form_cannot_publish_the_previous_canonical_after_a_url_change(): void
    {
        $studio = $this->studio();
        $id = 'studio.'.$studio->id;
        $fields = app(SeoAdminPages::class)->fields(app(SeoAdminPages::class)->page($id));
        $this->change($studio, 'changed-before-draft');
        try {
            app(SeoPublisher::class)->draft($id, $fields, 0, null, $this->actor());
            $this->fail('An obsolete canonical must not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_removed_owner_urls_remain_reserved_but_return_404(): void
    {
        $studio = $this->studio();
        $old = $studio->slug;
        $this->change($studio, 'deleted-studio');
        $studio->delete(); // There is no Admin deletion endpoint; exercise unavailable-owner handling.
        $this->get('/studios/'.$old)->assertNotFound();
        $this->get('/studios/deleted-studio')->assertNotFound();
        $this->assertSame(2, ContentUrl::count());
        $this->rejected(fn () => $this->studio(['slug' => 'deleted-studio']), 'reserved');
    }

    public function test_backfill_is_explicit_transactional_idempotent_and_preserves_existing_studio_attributes(): void
    {
        $now = '2026-01-01 12:00:00';
        foreach (['active', 'draft', 'inactive'] as $position => $status) {
            // Simulate pre-release rows without model hooks.
            DB::table('studios')->insert([
                'name' => 'Legacy fixture '.$position, 'slug' => 'fixture-'.$position, 'address' => 'Example', 'state' => 'Delhi',
                'phones' => '["+91 98765 43210"]', 'status' => $status, 'sort_order' => $position,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $before = DB::table('studios')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->artisan('studios:reserve-urls', ['--check' => true])->assertFailed();
        $this->assertSame(0, ContentUrl::count());
        $this->assertNull(DB::table('studios')->first()->url_locked_at);
        $this->artisan('studios:reserve-urls')->assertSuccessful();
        $after = DB::table('studios')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $this->assertNotNull($after[0]['url_locked_at']);
        $after[0]['url_locked_at'] = null;
        $this->assertSame($before, $after);
        $this->artisan('studios:reserve-urls')->assertSuccessful();
        $this->artisan('studios:reserve-urls', ['--check' => true])->assertSuccessful();
        $this->assertSame(3, ContentUrl::count());
        $this->assertSame(1, DB::table('studios')->whereNotNull('url_locked_at')->count());
    }

    public function test_backfill_rolls_back_the_entire_batch_on_conflicting_ownership(): void
    {
        foreach (['one', 'two'] as $slug) {
            DB::table('studios')->insert([
                'name' => $slug, 'slug' => $slug, 'address' => 'Example', 'state' => 'Delhi',
                'phones' => '[]', 'status' => 'active', 'sort_order' => 1,
            ]);
        }
        app(ContentUrls::class)->reserve('blog', 999, '/studios/two');
        try {
            $this->artisan('studios:reserve-urls')->run();
            $this->fail('Expected ownership conflict.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('ownership conflict', $exception->getMessage());
        }
        $this->assertSame(1, ContentUrl::count());
        $this->assertSame(0, DB::table('studios')->whereNotNull('url_locked_at')->count());
    }
}
