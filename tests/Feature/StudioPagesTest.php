<?php

namespace Tests\Feature;

use App\Contracts\Seo\ContentMetadataProvider;
use App\Models\Studio;
use App\Models\StudioImage;
use App\Models\User;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoPublisher;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use App\Services\StudioUrls;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioPagesTest extends TestCase
{
    private string $directory;

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
        $this->publication(new SeoSnapshot);
        $this->directory = sys_get_temp_dir().'/dd-studio-pages-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        foreach (['assets/css/admin.css', 'assets/js/admin/workspace.js'] as $asset) {
            File::ensureDirectoryExists($this->directory.'/'.dirname($asset));
            copy(public_path($asset), $this->directory.'/'.$asset);
        }
        $this->app->usePublicPath($this->directory);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        if (isset($this->directory) && realpath($this->directory)
            && str_starts_with(realpath($this->directory), realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'dd-studio-pages-')) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    private function studio(array $fields = []): Studio
    {
        return Studio::create(array_replace([
            'name' => 'Detailing Devils Jaipur', 'address' => '12 Example Road, Jaipur',
            'city' => 'Jaipur', 'state' => 'Rajasthan', 'country_code' => 'IN',
            'phones' => ['(+91) 98765 43210', '+91 99887 76655'],
            'map_url' => 'https://maps.app.goo.gl/FixtureStudio', 'status' => 'active', 'sort_order' => 1,
        ], $fields));
    }

    private function publication(SeoSnapshot $snapshot): void
    {
        $payload = app(SeoPublicationCodec::class)->encode($snapshot);
        DB::table('seo_metadata')->updateOrInsert(['id' => 1], [
            'origin' => config('seo.origin'), 'version' => 0, 'payload' => $payload,
            'payload_hash' => hash('sha256', $payload), 'sitemap_xml' => '',
            'sitemap_hash' => hash('sha256', ''), 'updated_at' => now(),
        ]);
        $this->app->forgetInstance(SeoResolver::class);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }

    private function sitemap(): array
    {
        $this->app->forgetInstance(SeoResolver::class);
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('<priority>', $xml);

        return app(SeoSitemap::class)->urls($xml);
    }

    public function test_active_studio_renders_unique_server_side_metadata_contact_and_verified_schema(): void
    {
        $studio = $this->studio();
        $canonical = config('seo.origin').'/studios/'.$studio->slug;
        $html = $this->get('/studios/'.$studio->slug.'?utm_source=fixture')->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $this->assertSame($studio->name, $xpath->evaluate('string(//h1)'));
        $this->assertSame($canonical, $xpath->evaluate('string(/html/head/link[@rel="canonical"]/@href)'));
        $this->assertSame($canonical, $xpath->evaluate('string(/html/head/meta[@property="og:url"]/@content)'));
        $this->assertSame('index, follow', $xpath->evaluate('string(/html/head/meta[@name="robots"]/@content)'));
        foreach (['title', 'meta[@name="description"]', 'link[@rel="canonical"]', 'meta[@name="robots"]', 'meta[@property="og:title"]', 'meta[@name="twitter:title"]'] as $tag) {
            $this->assertSame(1, $xpath->query('/html/head/'.$tag)->length);
        }
        $this->assertStringContainsString($studio->name, $xpath->evaluate('string(/html/head/meta[@name="description"]/@content)'));
        $this->assertStringContainsString('Rajasthan', $html);
        $this->assertStringContainsString('tel:+919876543210', $html);
        $this->assertStringContainsString('tel:+919988776655', $html);
        $this->assertStringContainsString('https://maps.app.goo.gl/FixtureStudio', $html);
        $this->assertStringContainsString('Studio photos are not available.', $html);
        $nodes = $xpath->query('/html/head/script[@type="application/ld+json"]');
        $this->assertSame(2, $nodes->length);
        $business = json_decode($nodes->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('LocalBusiness', $business['@type']);
        $this->assertSame($studio->address, $business['address']['streetAddress']);
        $this->assertSame('Jaipur', $business['address']['addressLocality']);
        $this->assertSame('IN', $business['address']['addressCountry']);
        $this->assertSame($studio->phones, $business['telephone']);
        foreach (['openingHours', 'aggregateRating', 'review', 'image', 'geo', 'priceRange'] as $unknown) {
            $this->assertArrayNotHasKey($unknown, $business);
        }
        $breadcrumbs = json_decode($nodes->item(1)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['Home', 'Studio Locator', $studio->name], array_column($breadcrumbs['itemListElement'], 'name'));
        $this->assertSame($canonical, $breadcrumbs['itemListElement'][2]['item']);
        $other = $this->studio(['name' => 'Detailing Devils Noida', 'city' => null, 'state' => 'Uttar Pradesh']);
        $this->app->forgetInstance(SeoResolver::class);
        $second = $this->xpath($this->get('/studios/'.$other->slug)->assertOk()->getContent());
        foreach (['title', 'meta[@name="description"]/@content'] as $field) {
            $this->assertNotSame($xpath->evaluate('string(/html/head/'.$field.')'), $second->evaluate('string(/html/head/'.$field.')'));
        }
    }

    public function test_unknown_inactive_and_draft_studios_return_404_without_leaking_details(): void
    {
        foreach (['inactive', 'draft'] as $status) {
            $studio = $this->studio(['name' => 'Hidden '.$status, 'status' => $status]);
            $this->get('/studios/'.$studio->slug)->assertNotFound()->assertDontSee($studio->address);
        }
        $this->get('/studios/unknown-studio')->assertNotFound();
        $this->get('/studios/Invalid-Slug')->assertNotFound();
    }

    public function test_gallery_uses_only_existing_owned_photos_cover_first_and_real_coordinates(): void
    {
        $studio = $this->studio(['latitude' => 26.9124, 'longitude' => 75.7873]);
        $images = [];
        foreach ([false, true] as $cover) {
            $path = $studio->id.'/'.Str::uuid().'.jpg';
            File::ensureDirectoryExists(public_path('uploads/studios/'.$studio->id));
            $file = UploadedFile::fake()->image('fixture.jpg', $cover ? 400 : 240, $cover ? 300 : 480);
            copy($file->getRealPath(), public_path('uploads/studios/'.$path));
            $image = new StudioImage(['alt_text' => $cover ? 'Fixture reception' : 'Fixture bay']);
            $image->forceFill(['path' => $path, 'width' => $cover ? 400 : 240, 'height' => $cover ? 300 : 480, 'is_cover' => $cover]);
            $studio->images()->save($image);
            $images[] = $image;
        }
        foreach ([$studio->id.'/'.Str::uuid().'.jpg', '../other-studio.jpg'] as $path) {
            $image = new StudioImage;
            $image->forceFill(['path' => $path, 'width' => 400, 'height' => 300]);
            $studio->images()->save($image);
        }
        $html = $this->get('/studios/'.$studio->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Fixture reception', $html);
        $this->assertStringContainsString('Fixture bay', $html);
        $this->assertLessThan(strpos($html, $images[0]->path), strpos($html, $images[1]->path));
        $this->assertStringNotContainsString('../other-studio.jpg', $html);
        $xpath = $this->xpath($html);
        $this->assertSame(config('seo.origin').'/uploads/studios/'.$images[1]->path, $xpath->evaluate('string(/html/head/meta[@property="og:image"]/@content)'));
        $business = json_decode($xpath->query('/html/head/script[@type="application/ld+json"]')->item(0)->textContent, true);
        $this->assertSame(26.9124, $business['geo']['latitude']);
        $this->assertCount(1, $business['image']);
        File::delete(public_path('uploads/studios/'.$images[1]->path));
        $this->app->forgetInstance(SeoResolver::class);
        $html = $this->get('/studios/'.$studio->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('property="og:image"', $html);
        $this->assertStringNotContainsString($images[1]->path, $html);
    }

    public function test_sitemap_preserves_all_existing_urls_and_tracks_active_studios_and_modification_dates(): void
    {
        $base = app(SeoSitemap::class)->urls(file_get_contents(base_path('tests/Fixtures/seo/sitemap.xml')));
        $this->assertSame($base, $this->sitemap());
        $studio = $this->studio();
        $studio->forceFill(['updated_at' => '2026-11-10 12:00:00'])->save();
        $inactive = $this->studio(['name' => 'Inactive studio', 'status' => 'inactive']);
        $this->studio(['name' => 'Draft studio', 'status' => 'draft']);
        $urls = $this->sitemap();
        $this->assertCount(count($base) + 1, $urls);
        $this->assertSame([], array_diff($base, $urls));
        $this->assertCount(count($urls), array_unique($urls));
        $xml = app(SeoSitemap::class)->xml();
        $this->assertStringContainsString('<lastmod>'.$studio->updated_at->format(DATE_ATOM).'</lastmod>', $xml);
        $inactive->update(['status' => 'active']);
        $this->assertCount(count($base) + 2, $this->sitemap());
        $studio->update(['status' => 'draft']);
        $this->assertNotContains(config('seo.origin').'/studios/'.$studio->slug, $this->sitemap());
        $this->assertStringContainsString('Sitemap: '.config('seo.origin').'/sitemap.xml', file_get_contents(base_path('public/robots.txt')));
    }

    public function test_existing_seo_editor_and_publisher_manage_studios_with_stable_identity_after_slug_changes(): void
    {
        $studio = $this->studio();
        $actor = new User(['name' => 'Isolated Admin', 'email' => 'admin@example.test', 'password' => 'Synthetic-password']);
        $actor->forceFill(['role' => 'admin'])->save();
        $this->actingAs($actor);
        $id = 'studio.'.$studio->id;
        $this->get('/admin/seo?q=Jaipur')->assertOk()->assertSee($studio->name);
        $this->get('/admin/seo/pages/'.$id)->assertOk()->assertSee($studio->name);
        $fields = app(SeoAdminPages::class)->fields(app(SeoAdminPages::class)->page($id));
        $fields['title'] = 'Reviewed studio title';
        $publisher = app(SeoPublisher::class);
        $draft = $publisher->draft($id, $fields, 0, null, $actor);
        $this->assertSame(1, $publisher->publish($draft, 0, true, false, $actor));
        $oldSlug = $studio->slug;
        app(StudioUrls::class)->change($studio, 'reviewed-jaipur-studio', $oldSlug, $actor);
        $studio->refresh();
        $this->app->forgetInstance(SeoResolver::class);
        $this->get('/studios/'.$studio->slug)->assertOk()->assertSee('<title>Reviewed studio title</title>', false)
            ->assertSee('rel="canonical" href="'.config('seo.origin').'/studios/'.$studio->slug.'"', false);
        $this->get('/studios/'.$oldSlug)->assertStatus(301)->assertRedirect(config('seo.origin').'/studios/'.$studio->slug);
        $this->assertContains(config('seo.origin').'/studios/'.$studio->slug, $this->sitemap());
        $this->assertNotContains(config('seo.origin').'/studios/'.$oldSlug, $this->sitemap());
        $this->assertSame($studio->name, $studio->fresh()->name);
    }

    public function test_existing_seo_policy_overrides_still_control_indexing_and_provider_failures_return_retryable_sitemap(): void
    {
        $studio = $this->studio();
        $this->publication(new SeoSnapshot([new SeoOverride('studio.'.$studio->id, ['robots' => 'noindex, follow', 'sitemap' => false], true, true)]));
        $this->get('/studios/'.$studio->slug)->assertOk()->assertSee('name="robots" content="noindex, follow"', false);
        $this->assertNotContains(config('seo.origin').'/studios/'.$studio->slug, $this->sitemap());
        $this->app->forgetInstance(SeoResolver::class);
        $provider = new class implements ContentMetadataProvider
        {
            public function pages(): iterable
            {
                throw new \RuntimeException('Synthetic content storage failure');
            }
        };
        $this->app->instance($provider::class, $provider);
        config()->set('seo.content_providers', [$provider::class]);
        $this->get('/sitemap.xml')->assertStatus(503)->assertHeader('Retry-After', '60');
    }

    public function test_locator_adds_active_detail_links_without_changing_phone_or_directions_actions(): void
    {
        $studio = $this->studio();
        $this->studio(['name' => 'Hidden studio', 'status' => 'inactive']);
        $this->get('/locate-a-store')->assertOk()->assertSee(route('studios.show', $studio->slug), false)
            ->assertSee('View Studio')->assertSee('tel:+919876543210', false)
            ->assertSee($studio->map_url, false)->assertDontSee('hidden-studio');
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_admin_changes_are_visible_on_subsequent_public_requests_without_a_new_seo_publication(): void
    {
        $studio = $this->studio();
        $actor = new User(['name' => 'Isolated Admin', 'email' => 'editor@example.test', 'password' => 'Synthetic-password']);
        $actor->forceFill(['role' => 'admin'])->save();
        $this->actingAs($actor);
        $this->get('/studios/'.$studio->slug)->assertOk()->assertSee($studio->address);
        $fields = $studio->only([
            'name', 'slug', 'address', 'city', 'state', 'country_code', 'phones',
            'map_url', 'latitude', 'longitude', 'status', 'sort_order',
        ]);
        $fields['address'] = 'Updated fixture studio address';
        $fields['phones'] = ['+91 99887 76655', '(+91) 98765 43210'];
        $this->put('/admin/studios/'.$studio->id, $fields)->assertSessionHasNoErrors();
        $this->app->forgetInstance(SeoResolver::class);
        $html = $this->get('/studios/'.$studio->slug)->assertOk()->assertSee($fields['address'])->getContent();
        $xpath = $this->xpath($html);
        $this->assertSame('tel:+919988776655', $xpath->evaluate('string(//a[contains(@class,"studio-detail__button--red")]/@href)'));
        $business = json_decode($xpath->query('/html/head/script[@type="application/ld+json"]')->item(0)->textContent, true);
        $this->assertSame($fields['address'], $business['address']['streetAddress']);
        $fields['status'] = 'inactive';
        $this->put('/admin/studios/'.$studio->id, $fields)->assertSessionHasNoErrors();
        $this->get('/studios/'.$studio->slug)->assertNotFound();
        $this->assertNotContains(config('seo.origin').'/studios/'.$studio->slug, $this->sitemap());
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_studio_content_is_escaped_in_html_and_json_ld_without_inventing_missing_location_data(): void
    {
        $studio = $this->studio(['name' => 'Studio </script><script>alert(1)</script>', 'city' => null, 'country_code' => null, 'map_url' => null]);
        $html = $this->get('/studios/'.$studio->slug)->assertOk()->getContent();
        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $nodes = $this->xpath($html)->query('/html/head/script[@type="application/ld+json"]');
        $business = json_decode($nodes->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($studio->name, $business['name']);
        $this->assertArrayNotHasKey('addressLocality', $business['address']);
        $this->assertArrayNotHasKey('addressCountry', $business['address']);
        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=', $business['hasMap']);
    }
}
