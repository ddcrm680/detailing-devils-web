<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\ContentMetadata;
use App\Services\Seo\EmptyMetadataRepository;
use App\Services\Seo\JsonLd;
use App\Services\Seo\SeoDiagnostics;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use App\Services\Seo\SeoUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class SeoArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'unconfigured');
        Http::preventStrayRequests();
        Mail::fake();
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
    }

    public function test_all_registered_pages_have_one_complete_safe_metadata_head_without_database_access(): void
    {
        foreach (app(SeoResolver::class)->inventory() as $page) {
            $path = substr($page->canonical, strlen(config('seo.origin')));
            $html = $this->get($path)
                ->assertOk()
                ->getContent();
            $this->assertSame([], app(SeoDiagnostics::class)->html($html, $page), $page->id);
            $this->assertSame('index, follow', $page->robots);
        }
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_tour_canonicals_only_preserve_the_two_approved_meaningful_model_parameters(): void
    {
        foreach ([
            '' => '',
            '?model=xclusive' => '',
            '?model=bad' => '',
            '?model[]=prime' => '',
            '?utm_source=qa' => '',
            '?model=fofo&utm_source=qa' => '?model=fofo',
            '?model=prime&gclid=qa' => '?model=prime',
        ] as $query => $suffix) {
            $html = $this->get('/studio-tour'.$query)
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString(
                'rel="canonical" href="https://www.detailingdevils.com/studio-tour'.$suffix.'"',
                $html
            );
        }
    }

    public function test_request_host_and_tracking_cannot_poison_canonicals_social_urls_or_schema(): void
    {
        $html = $this->get('http://untrusted.invalid/studio-tour?model=fofo&redirect=https://untrusted.invalid')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString(
            'rel="canonical" href="https://www.detailingdevils.com/studio-tour?model=fofo"',
            $html
        );
        $this->assertStringContainsString(
            'property="og:url" content="https://www.detailingdevils.com/studio-tour?model=fofo"',
            $html
        );
    }

    public function test_published_override_wins_while_drafts_and_other_locales_are_ignored(): void
    {
        $this->repository(new SeoSnapshot([
            new SeoOverride('fofo.show', ['title' => 'Published title & identity'], published: true),
            new SeoOverride('prime.show', ['title' => 'Unpublished draft']),
            new SeoOverride('xclusive.show', ['title' => 'Different locale'], published: true, locale: 'fr'),
        ]));
        $this->assertSame('Published title & identity', app(SeoResolver::class)->forPage('fofo.show')->title);
        $this->assertSame(
            'Franchise Application | Detailing Devils Prime',
            app(SeoResolver::class)->forPage('prime.show')->title
        );
        $this->assertSame(
            'X-Clusive Franchise Application | Detailing Devils',
            app(SeoResolver::class)->forPage('xclusive.show')->title
        );
    }

    public function test_policy_controls_require_explicit_trusted_publishing_approval(): void
    {
        foreach ([
            'canonical' => '/elsewhere',
            'robots' => 'noindex, follow',
            'sitemap' => false,
        ] as $key => $value) {
            try {
                new SeoOverride('home', [$key => $value], published: true);
                $this->fail('Unapproved indexing-policy change accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->repository(new SeoSnapshot([new SeoOverride(
            'fofo.show',
            ['robots' => 'noindex, follow'],
            true,
            true
        )]));
        $page = app(SeoResolver::class)->forPage('fofo.show');
        $this->assertFalse($page->indexable());
        $this->assertNotContains($page->canonical, app(SeoSitemap::class)->urls(app(SeoSitemap::class)->xml()));
    }

    public function test_invalid_publication_falls_back_atomically_instead_of_breaking_public_pages(): void
    {
        $this->repository(new SeoSnapshot([
            new SeoOverride(
                'home',
                [
                    'title' => 'Must not partially publish',
                    'image' => 'https://evil.invalid/a.png',
                ],
                true
            ),
        ]));
        $page = app(SeoResolver::class)->forPage('home');
        $this->assertSame(config('site.seo_title'), $page->title);
        $this->assertContains('Invalid published override rejected; using base metadata.', $page->warnings);
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Must not partially publish');
    }

    public function test_optional_repository_failure_keeps_public_metadata_available_and_errors_independent(): void
    {
        $this->app->bind(
            MetadataRepository::class,
            fn () => new class implements MetadataRepository
            {
                public function snapshot(): SeoSnapshot
                {
                    throw new \RuntimeException('Private storage diagnostic');
                }
            }
        );
        $this->get('/')
            ->assertOk()
            ->assertSee(e(config('site.seo_title')), false)
            ->assertDontSee('Private storage diagnostic');
        $this->get('/nonexistent-seo-test')
            ->assertNotFound()
            ->assertDontSee('Private storage diagnostic')
            ->assertDontSee(
                'property="og:title"',
                false
            );
    }

    public function test_snapshot_is_read_once_per_resolver_lifecycle_and_not_globally_cached(): void
    {
        $repository = new class implements MetadataRepository
        {
            public int $calls = 0;

            public function snapshot(): SeoSnapshot
            {
                $this->calls++;

                return new SeoSnapshot;
            }
        };
        $resolver = new SeoResolver($repository, new SeoUrl);
        $resolver->inventory();
        $resolver->inventory();
        $this->assertSame(1, $repository->calls);
        (new SeoResolver($repository, new SeoUrl))->forPage('home');
        $this->assertSame(2, $repository->calls);
    }

    public function test_dynamic_content_templates_overrides_and_verified_dates_have_deterministic_precedence(): void
    {
        Route::view('/qa-articles/{slug}', 'layouts.app')->name('qa.articles.show');
        Route::getRoutes()->refreshNameLookups();
        config()->set(
            'seo.route_defaults',
            [
                'qa.articles.show' => [
                    'title' => 'Route fallback',
                    'description' => 'Route description',
                    'og_type' => 'website',
                ],
            ]
        );
        $content = new ContentMetadata(
            'article:42',
            'article',
            'qa.articles.show',
            ['slug' => 'safe'],
            'Verified article',
            'Verified description',
            published: true,
            sitemap: true,
            modifiedAt: new \DateTimeImmutable('2026-10-01T12:00:00+00:00')
        );
        $this->repository(new SeoSnapshot(
            [new SeoOverride('article:42', ['title' => 'Published editorial title'], true)],
            [$content]
        ));
        $page = app(SeoResolver::class)->forPage($content->id, $content);
        $this->assertSame('Published editorial title', $page->title);
        $this->assertSame('Verified description', $page->description);
        $this->assertSame('article', $page->ogType);
        $this->assertSame('https://www.detailingdevils.com/qa-articles/safe', $page->canonical);
        $this->assertStringContainsString(
            '<lastmod>2026-10-01T12:00:00+00:00</lastmod>',
            app(SeoSitemap::class)->xml()
        );
        $this->assertContains($page->canonical, app(SeoSitemap::class)->urls(app(SeoSitemap::class)->xml()));
    }

    public function test_future_content_is_not_published_or_indexed_by_default(): void
    {
        Route::view('/qa-service/{slug}', 'layouts.app')->name('qa.service');
        Route::getRoutes()->refreshNameLookups();
        $content = new ContentMetadata(
            'service:1',
            'service',
            'qa.service',
            ['slug' => 'test'],
            'Verified service'
        );
        $this->repository(new SeoSnapshot(content: [$content]));
        $page = app(SeoResolver::class)->forPage($content->id, $content);
        $this->assertSame('Verified service | Detailing Devils', $page->title);
        $this->assertFalse($page->indexable());
        $this->assertSame('noindex, follow', $page->robots);
        $this->assertNotContains($page->canonical, app(SeoSitemap::class)->urls(app(SeoSitemap::class)->xml()));
    }

    public function test_content_adapter_passes_metadata_into_layout_without_template_seo_sections(): void
    {
        $content = new ContentMetadata(
            'service:2',
            'service',
            'qa.content',
            [],
            'Page content',
            'Content description',
            published: true
        );
        Route::get('/qa-content', fn () => view('layouts.app', ['seoContent' => $content]))->name('qa.content');
        Route::getRoutes()->refreshNameLookups();
        $this->get('/qa-content')
            ->assertOk()
            ->assertSee('<title>Page content | Detailing Devils</title>', false);
    }

    public function test_metadata_escaping_cannot_create_extra_tags_or_executable_json_ld(): void
    {
        $attack = '</title><script>alert("QA")</script>';
        $this->repository(new SeoSnapshot([new SeoOverride(
            'home',
            ['title' => $attack, 'description' => $attack],
            true
        )]));
        $html = $this->get('/')
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString($attack, $html);
        $this->assertStringContainsString(e($attack), $html);
        $node = JsonLd::article($attack, 'https://www.detailingdevils.com/verified', $attack);
        $this->assertStringNotContainsString('</script>', $node->json());
        $this->assertSame($attack, json_decode($node->json(), true)['headline']);
        $this->assertSame([], app(SeoDiagnostics::class)->html($html, app(SeoResolver::class)->forPage('home')));
    }

    public function test_social_images_require_verified_existing_owned_raster_files(): void
    {
        $url = (new SeoUrl)->image('/img/recent-work.webp');
        $this->assertSame('https://www.detailingdevils.com/img/recent-work.webp', $url);
        $this->repository(new SeoSnapshot([
            new SeoOverride(
                'home',
                [
                    'image' => '/img/recent-work.webp',
                    'og_title' => 'Social title',
                    'twitter_title' => 'Twitter title',
                ],
                true
            ),
        ]));
        $html = $this->get('/')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $html);
        $this->assertSame([], app(SeoDiagnostics::class)->html($html, app(SeoResolver::class)->forPage('home')));
        foreach ([
            'https://other.invalid/image.png',
            '/img/missing.png',
            '/img/../logo.png',
            '//evil.invalid/a.png',
            '/img/recent-work.webp?x=1',
            '/%2e%2e/a.png',
        ] as $invalid) {
            try {
                (new SeoUrl)->image($invalid);
                $this->fail('Unsafe image accepted: '.$invalid);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_canonical_validation_rejects_unsafe_urls(): void
    {
        foreach ([
            'https://evil.invalid/',
            '//evil.invalid/',
            '/%0aevil',
            '/a#fragment',
            '/a\\b',
            '/a/../b',
            'javascript:alert(1)',
        ] as $invalid) {
            try {
                (new SeoUrl)->canonical($invalid);
                $this->fail('Unsafe canonical accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sitemap_keeps_exactly_the_approved_twelve_urls_and_no_fabricated_dates(): void
    {
        $sitemap = app(SeoSitemap::class);
        $this->assertCount(12, $sitemap->pages());
        $this->assertSame(
            $sitemap->urls(file_get_contents(resource_path('seo/sitemap.xml'))),
            $sitemap->urls($sitemap->xml())
        );
        $this->assertStringNotContainsString('<lastmod>', $sitemap->xml());
        $this->assertStringNotContainsString('model=xclusive', $sitemap->xml());
        $this->assertStringNotContainsString('warranty-terms', $sitemap->xml());
        $this->assertSame('index, follow', app(SeoResolver::class)->forPage('legal.warranty-terms')->robots);
        $this->artisan('seo:sitemap --check')->assertSuccessful();
    }

    public function test_sitemap_generation_is_valid_and_check_does_not_rewrite_files(): void
    {
        $path = sys_get_temp_dir().'/dd-seo-'.bin2hex(random_bytes(6)).'.xml';
        try {
            $this->artisan('seo:sitemap', ['--output' => $path])->assertSuccessful();
            $hash = hash_file('sha256', $path);
            $this->artisan('seo:sitemap', ['--output' => $path, '--check' => true])->assertSuccessful();
            $this->assertSame($hash, hash_file('sha256', $path));
            $this->assertCount(12, app(SeoSitemap::class)->urls(file_get_contents($path)));
            file_put_contents($path, '<invalid/>');
            $this->artisan('seo:sitemap', ['--output' => $path, '--check' => true])->assertFailed();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_sitemap_refuses_duplicate_or_entity_injected_xml(): void
    {
        $this->expectException(\RuntimeException::class);
        app(SeoSitemap::class)->urls('<!DOCTYPE urlset [<!ENTITY qa SYSTEM "file:///private">]><urlset/>');
    }

    public function test_diagnostics_report_intentional_duplicates_and_missing_editorial_assets_without_changing_copy(): void
    {
        $issues = app(SeoDiagnostics::class)->inspect(app(SeoResolver::class)->inventory());
        $this->assertContains('Intentional shared description.', array_column($issues, 'message'));
        $this->assertContains('No approved social-sharing image.', array_column($issues, 'message'));
        $this->assertContains(
            'Long title preserved; editorial review recommended.',
            array_column($issues, 'message')
        );
        $this->assertSame([], array_values(array_filter($issues, fn ($issue) => $issue['level'] === 'error')));
        $this->artisan('seo:audit', ['--search' => 'franchise'])->assertSuccessful();
    }

    public function test_rendered_diagnostics_detect_missing_duplicate_and_conflicting_tags(): void
    {
        $page = app(SeoResolver::class)->forPage('home');
        $html = $this->get('/')
            ->assertOk()
            ->getContent();
        $bad = str_replace('</head>', '<title>Duplicate</title></head>', $html);
        $this->assertNotEmpty(app(SeoDiagnostics::class)->html($bad, $page));
        $bad = str_replace('rel="canonical"', 'rel="alternate"', $html);
        $this->assertNotEmpty(app(SeoDiagnostics::class)->html($bad, $page));
    }

    public function test_typed_schema_factories_only_add_explicit_verified_facts(): void
    {
        $nodes = [
            JsonLd::organization('Detailing Devils', config('seo.origin').'/'),
            JsonLd::website('Detailing Devils', config('seo.origin').'/'),
            JsonLd::localBusiness('Verified studio', config('seo.origin').'/studio'),
            JsonLd::breadcrumbs([['name' => 'Home', 'url' => config('seo.origin').'/']]),
        ];
        foreach ($nodes as $node) {
            $data = json_decode($node->json(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('https://schema.org', $data['@context']);
            foreach (['aggregateRating', 'review', 'openingHours', 'address'] as $unverified) {
                $this->assertArrayNotHasKey($unverified, $data);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        JsonLd::website('Invalid', 'javascript:alert(1)');
    }

    public function test_missing_content_description_falls_back_to_route_metadata_and_reports_the_gap(): void
    {
        Route::view('/qa-fallback', 'layouts.app')->name('qa.fallback');
        Route::getRoutes()->refreshNameLookups();
        config()->set('seo.route_defaults', ['qa.fallback' => ['description' => 'Approved route description']]);
        $content = new ContentMetadata('service:fallback', 'service', 'qa.fallback', [], 'Verified title');
        $page = app(SeoResolver::class)->forPage($content->id, $content);
        $this->assertSame('Approved route description', $page->description);
        $this->assertContains('Content description missing; using route/site fallback.', $page->warnings);
    }

    public function test_snapshot_rejects_ambiguous_publication_identities_and_invalid_contracts(): void
    {
        try {
            new SeoSnapshot([new SeoOverride('home', []), new SeoOverride('home', [])]);
            $this->fail('Ambiguous publication snapshot accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        new SeoOverride('home', ['title' => null], true);
    }

    public function test_duplicate_sitemap_canonicals_stop_generation_instead_of_silently_dropping_pages(): void
    {
        $content = new ContentMetadata(
            'service:duplicate',
            'service',
            'home',
            [],
            'Distinct record',
            'Verified text',
            published: true,
            sitemap: true
        );
        $this->repository(new SeoSnapshot(content: [$content]));
        $this->expectException(\RuntimeException::class);
        app(SeoSitemap::class)->xml();
    }

    public function test_saved_html_cli_diagnostics_run_without_http_requests(): void
    {
        $path = sys_get_temp_dir().'/dd-seo-html-'.bin2hex(random_bytes(6)).'.html';
        try {
            file_put_contents($path, $this->get('/')
                ->assertOk()
                ->getContent());
            $this->artisan('seo:audit', ['--html' => $path, '--page' => 'home'])->assertSuccessful();
            file_put_contents($path, '<html><head><title>Outdated</title></head><body></body></html>');
            $this->artisan('seo:audit', ['--html' => $path, '--page' => 'home'])->assertFailed();
            Http::assertNothingSent();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_five_hundred_future_records_use_one_consistent_database_free_publication_snapshot(): void
    {
        Route::view('/qa-records/{id}', 'layouts.app')->name('qa.records');
        Route::getRoutes()->refreshNameLookups();
        $records = [];
        for ($id = 1; $id <= 500; $id++) {
            $records[] = new ContentMetadata(
                'service:'.$id,
                'service',
                'qa.records',
                ['id' => $id],
                'Verified service '.$id,
                'Verified description '.$id,
                published: true,
                sitemap: true
            );
        }
        $this->repository(new SeoSnapshot(content: $records));
        $this->assertCount(513, app(SeoResolver::class)->inventory());
        $urls = app(SeoSitemap::class)->urls(app(SeoSitemap::class)->xml());
        $this->assertCount(512, $urls);
        $this->assertCount(512, array_unique($urls));
        $this->assertContains('https://www.detailingdevils.com/qa-records/500', $urls);
    }

    public function test_admin_reuses_standard_authentication_and_root_migrations_include_its_schema(): void
    {
        $this->assertNull(config('database.connections.seo'));
        $this->assertNull(config('auth.guards.seo'));
        $this->assertTrue(Route::has('admin.login'));
        $this->assertContains('auth:web', Route::getRoutes()->getByName('admin.seo.draft')->gatherMiddleware());
        $this->assertContains(
            'can:manage-seo',
            Route::getRoutes()->getByName('admin.seo.publish')->gatherMiddleware()
        );
        $files = app('migrator')->getMigrationFiles(database_path('migrations'));
        $this->assertArrayHasKey('2026_11_02_000000_add_role_to_users_table', $files);
        $this->assertArrayHasKey('2026_11_02_000001_create_website_seo_store', $files);
    }

    public function test_relative_self_canonical_override_is_normalized_before_alias_classification(): void
    {
        $this->repository(new SeoSnapshot([
            new SeoOverride('fofo.show', ['canonical' => '/franchise-owned-franchise-operated-model'], true, true),
        ]));
        $page = app(SeoResolver::class)->forPage('fofo.show');
        $this->assertFalse($page->canonicalAliasApproved);
        $this->assertSame(
            'https://www.detailingdevils.com/franchise-owned-franchise-operated-model',
            $page->canonical
        );
    }

    private function repository(SeoSnapshot $snapshot): void
    {
        $this->app->instance(
            MetadataRepository::class,
            new class($snapshot) implements MetadataRepository
            {
                public function __construct(private SeoSnapshot $snapshot) {}

                public function snapshot(): SeoSnapshot
                {
                    return $this->snapshot;
                }
            }
        );
        $this->app->forgetInstance(SeoResolver::class);
    }
}
