<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\ContentMetadata;
use App\Services\Seo\EmptyMetadataRepository;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DynamicSitemapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
    }

    public function test_sitemap_is_stateless_dynamic_xml_with_the_approved_urls_and_revalidation(): void
    {
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
        $sitemap = app(SeoSitemap::class);
        $this->assertSame(
            $sitemap->urls(file_get_contents(base_path('tests/Fixtures/seo/sitemap.xml'))),
            $sitemap->urls($response->getContent())
        );
        $this->assertCount(13, $sitemap->urls($response->getContent()));
        $this->assertContains(
            'https://www.detailingdevils.com/warranty-terms-condition',
            $sitemap->urls($response->getContent())
        );
        $this->get('/sitemap.xml', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
        $this->assertSame([], app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('sitemap')));
    }

    public function test_published_warranty_policy_can_still_exclude_the_page_from_the_sitemap(): void
    {
        foreach ([
            ['sitemap' => false],
            ['robots' => 'noindex, follow'],
            ['canonical' => config('seo.origin').'/', 'sitemap' => false],
        ] as $fields) {
            $snapshot = new SeoSnapshot([
                new SeoOverride('legal.warranty-terms', $fields, true, true),
            ]);
            $this->app->instance(MetadataRepository::class, new class($snapshot) implements MetadataRepository
            {
                public function __construct(private SeoSnapshot $snapshot) {}

                public function snapshot(): SeoSnapshot
                {
                    return $this->snapshot;
                }
            });
            $this->app->forgetInstance(SeoResolver::class);
            $response = $this->get('/sitemap.xml')->assertOk();
            $urls = app(SeoSitemap::class)->urls($response->getContent());
            $this->assertCount(12, $urls);
            $this->assertNotContains('https://www.detailingdevils.com/warranty-terms-condition', $urls);
            $page = app(SeoResolver::class)->forPage('legal.warranty-terms');
            $this->assertSame($fields['robots'] ?? 'index, follow', $page->robots);
        }
    }

    public function test_new_and_noindex_pages_update_the_http_sitemap_without_file_generation(): void
    {
        Route::view('/new-public-guide', 'layouts.app')->name('qa.dynamic-sitemap');
        Route::getRoutes()->refreshNameLookups();
        app()->forgetInstance(SeoResolver::class);
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/new-public-guide</loc>', false);
        Route::getRoutes()->getByName('qa.dynamic-sitemap')->defaults('_seo', ['robots' => 'noindex, follow']);
        app()->forgetInstance(SeoResolver::class);
        $this->get('/sitemap.xml', ['If-None-Match' => $response->headers->get('ETag')])
            ->assertOk()
            ->assertDontSee(
                '/new-public-guide</loc>',
                false
            );
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }

    public function test_store_outage_returns_a_safe_retryable_sitemap_failure_while_public_html_falls_back(): void
    {
        $this->app->instance(
            MetadataRepository::class,
            new class implements MetadataRepository
            {
                public function snapshot(): SeoSnapshot
                {
                    throw new \RuntimeException('Private synthetic database failure');
                }
            }
        );
        app()->forgetInstance(SeoResolver::class);
        $this->get('/sitemap.xml')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertDontSee('Private synthetic');
        $this->get('/')
            ->assertOk()
            ->assertSee(e(config('site.seo_title')), false);
    }

    public function test_default_sitemap_command_never_recreates_a_masking_static_file(): void
    {
        $this->artisan('seo:sitemap')->assertSuccessful();
        $this->artisan('seo:sitemap', ['--output' => public_path('sitemap.xml')])->assertFailed();
        $this->artisan('seo:sitemap', ['--output' => public_path('../public/sitemap.xml')])->assertFailed();
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }

    public function test_editorial_overrides_cannot_index_content_restricted_by_its_source(): void
    {
        Route::view('/qa-source/{slug}', 'layouts.app')->name('qa.source');
        Route::getRoutes()->refreshNameLookups();
        $record = new ContentMetadata(
            'article:restricted',
            'article',
            'qa.source',
            ['slug' => 'restricted'],
            'Restricted source record',
            published: true,
            indexable: false,
            sitemap: true
        );
        $this->app->instance(
            MetadataRepository::class,
            new class($record) implements MetadataRepository
            {
                public function __construct(private ContentMetadata $record) {}

                public function snapshot(): SeoSnapshot
                {
                    return new SeoSnapshot(
                        [
                            new SeoOverride(
                                'article:restricted',
                                ['robots' => 'index, follow', 'sitemap' => true],
                                true,
                                true
                            ),
                        ],
                        [$this->record]
                    );
                }
            }
        );
        $page = app(SeoResolver::class)->forPage($record->id, $record);
        $this->assertFalse($page->indexable());
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/qa-source/restricted</loc>', false);
    }
}
