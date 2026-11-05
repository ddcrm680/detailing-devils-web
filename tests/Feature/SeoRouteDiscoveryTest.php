<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Http\Controllers\HomeController;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\ContentMetadata;
use App\Services\Seo\EmptyMetadataRepository;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoRouteDiscovery;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SeoRouteDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        Mail::fake();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
    }

    public function test_new_public_view_automatically_appears_in_admin_metadata_and_generated_sitemap(): void
    {
        Route::view('/paint-care-guide', 'layouts.app')->name('paint.guide');
        $this->refresh();
        $page = app(SeoAdminPages::class)->page('paint.guide');
        $this->assertSame('Paint Care Guide | Detailing Devils', $page->title);
        $this->assertSame('Explore Paint Care Guide at Detailing Devils.', $page->description);
        $this->assertSame('https://www.detailingdevils.com/paint-care-guide', $page->canonical);
        $this->get('/paint-care-guide')
            ->assertOk()
            ->assertSee('<title>'.e($page->title).'</title>', false);
        $this->assertContains($page->canonical, $this->urls());
        $this->assertContains(
            'paint.guide',
            array_map(fn ($page) => $page->id, app(SeoAdminPages::class)->base()->inventory())
        );
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_unnamed_public_pages_have_stable_identity_even_with_a_generated_route_cache_name(): void
    {
        $route = Route::view('/new-studio-guide', 'layouts.app');
        $id = app(SeoRouteDiscovery::class)->id($route);
        $this->refresh();
        $this->assertSame(
            'https://www.detailingdevils.com/new-studio-guide',
            app(SeoAdminPages::class)->page($id)->canonical
        );
        $this->get('/new-studio-guide')
            ->assertOk()
            ->assertSee(
                '<title>New Studio Guide | Detailing Devils</title>',
                false
            );
        $route->name('generated::simulated-route-cache-name');
        $this->refresh();
        $this->assertSame($id, app(SeoRouteDiscovery::class)->id($route));
        $this->assertContains('https://www.detailingdevils.com/new-studio-guide', $this->urls());
    }

    public function test_invokable_view_controllers_are_discovered_without_configuration_or_execution(): void
    {
        $route = Route::get('/invokable-guide', HomeController::class)->name('qa.invokable');
        Route::get('/private-invokable', HomeController::class)->middleware('auth:web')->name('qa.private');
        $route->prepareForSerialization();
        $this->refresh();

        $this->assertSame('home', app(SeoAdminPages::class)->page('home')->id);
        $page = app(SeoAdminPages::class)->page('qa.invokable');
        $this->assertSame('Invokable Guide | Detailing Devils', $page->title);
        $this->assertContains($page->canonical, $this->urls());
        $this->assertNotContains('https://www.detailingdevils.com/private-invokable', $this->urls());
        $this->get('/invokable-guide')->assertOk()->assertSee(e($page->title), false);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_route_policy_preserves_thirteen_identities_and_urls_without_a_configured_page_list(): void
    {
        $this->refresh();
        $pages = app(SeoResolver::class)->inventory();
        $this->assertCount(13, $pages);
        $this->assertSame('home', app(SeoAdminPages::class)->page('home')->id);
        $this->assertSame(
            'https://www.detailingdevils.com/studio-tour?model=fofo',
            app(SeoAdminPages::class)->page('studios.tour.fofo')->canonical
        );
        $this->assertSame(
            'https://www.detailingdevils.com/studio-tour?model=prime',
            app(SeoAdminPages::class)->page('studios.tour.prime')->canonical
        );
        $warranty = app(SeoAdminPages::class)->page('legal.warranty-terms');
        $this->assertTrue($warranty->sitemap);
        $this->assertSame('index, follow', $warranty->robots);
        $sitemap = app(SeoSitemap::class);
        $expected = $sitemap->urls(file_get_contents(base_path('tests/Fixtures/seo/sitemap.xml')));
        $actual = $this->urls();
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function test_query_variants_are_selected_only_from_the_route_policy(): void
    {
        Route::view('/query-guide', 'layouts.app')
            ->defaults('_seo', ['variants' => ['alternate' => ['edition' => 'alternate']]])
            ->name('qa.query-guide');
        $this->refresh();
        foreach ([
            '?edition=alternate&utm_source=qa' => '?edition=alternate',
            '?edition=invalid' => '',
            '?edition[]=alternate' => '',
        ] as $query => $canonicalQuery) {
            $this->get('/query-guide'.$query)
                ->assertOk()
                ->assertSee(
                    'rel="canonical" href="https://www.detailingdevils.com/query-guide'.$canonicalQuery.'"',
                    false
                );
        }
        $this->assertContains('https://www.detailingdevils.com/query-guide?edition=alternate', $this->urls());
    }

    public function test_invokable_discovery_and_query_policy_survive_laravel_route_compilation(): void
    {
        $routes = Route::getRoutes();
        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }
        app('router')->setCompiledRoutes($routes->compile());
        $this->app->forgetInstance(SeoResolver::class);

        $this->assertCount(13, app(SeoResolver::class)->inventory());
        $this->assertSame('home', app(SeoAdminPages::class)->page('home')->id);
        $this->assertCount(13, $this->urls());
        foreach (['fofo', 'prime'] as $model) {
            $this->get('/studio-tour?model='.$model.'&utm_source=qa')
                ->assertOk()
                ->assertSee(
                    'rel="canonical" href="https://www.detailingdevils.com/studio-tour?model='.$model.'"',
                    false
                );
        }
        $this->assertTrue(app(SeoAdminPages::class)->page('legal.warranty-terms')->sitemap);
    }

    public function test_private_internal_api_redirect_and_non_page_routes_are_never_discovered(): void
    {
        Route::view('/admin/seo/qa', 'layouts.app')->name('qa.admin');
        Route::view('/staff-only', 'layouts.app')->middleware('auth:web')->name('qa.private');
        Route::view('/permission-only', 'layouts.app')->middleware('can:manage-seo')->name('qa.permission');
        Route::view('/signed-only', 'layouts.app')->middleware('signed')->name('qa.signed');
        Route::view('/signed-declared', 'layouts.app')
            ->middleware('signed')
            ->defaults(
                '_seo',
                ['public' => true]
            )
            ->name('qa.signed-declared');
        Route::view('/login', 'layouts.app')->name('login');
        Route::view('/internal/report', 'layouts.app')->name('qa.internal');
        Route::view('/api/display', 'layouts.app')->middleware('api')->name('qa.api');
        Route::view('/custom-private', 'layouts.app')->middleware('custom.access')->name('qa.custom');
        Route::view('/non-public-page', 'layouts.app')->defaults('_seo', ['public' => false])->name('qa.opt-out');
        Route::view('/external-page', 'layouts.app')->domain('other.invalid')->name('qa.external');
        Route::redirect('/old-url', '/')->name('qa.redirect');
        Route::view('/missing-view', 'qa-no-such-view')->name('qa.missing');
        Route::view('/error-view', 'layouts.app', [], 404)->name('qa.error');
        Route::post('/post-only', fn () => 'not a page')->name('qa.post');
        Route::get('/data-endpoint', fn () => response()->json(['ok' => true]))->name('qa.data');
        $this->refresh();
        $ids = array_map(fn ($page) => $page->id, app(SeoResolver::class)->inventory());
        $this->assertCount(13, $ids);
        $this->assertCount(13, $this->urls());
        $this->assertNotContains('qa.data', $ids);
    }

    public function test_view_typed_actions_and_single_route_public_hints_support_controller_style_pages(): void
    {
        Route::get('/typed-guide', fn (): View => view('layouts.app'))->name('qa.typed');
        Route::get('/declared-guide', fn () => view('layouts.app'))
            ->defaults(
                '_seo',
                [
                    'public' => true,
                    'title' => 'Approved guide',
                    'description' => 'Approved route description',
                ]
            )
            ->name('qa.declared');
        $this->refresh();
        $this->assertSame('Approved guide', app(SeoAdminPages::class)->page('qa.declared')->title);
        $this->assertContains('https://www.detailingdevils.com/typed-guide', $this->urls());
        $this->assertContains('https://www.detailingdevils.com/declared-guide', $this->urls());
    }

    public function test_noindex_pages_remain_manageable_but_are_excluded_from_sitemaps(): void
    {
        Route::view('/private-campaign', 'layouts.app')
            ->defaults(
                '_seo',
                ['robots' => 'noindex, follow']
            )
            ->name('qa.noindex');
        Route::view('/published-guide', 'layouts.app')->name('qa.published');
        $this->app->instance(
            MetadataRepository::class,
            new class implements MetadataRepository
            {
                public function snapshot(): SeoSnapshot
                {
                    return new SeoSnapshot([
                        new SeoOverride(
                            'qa.published',
                            ['robots' => 'noindex, follow', 'sitemap' => false],
                            true,
                            true
                        ),
                    ]);
                }
            }
        );
        $this->refresh();
        $this->assertFalse(app(SeoAdminPages::class)->page('qa.noindex')->indexable());
        $this->assertFalse(app(SeoAdminPages::class)->page('qa.published')->indexable());
        $this->assertNotContains('https://www.detailingdevils.com/private-campaign', $this->urls());
        $this->assertNotContains('https://www.detailingdevils.com/published-guide', $this->urls());
        $this->get('/private-campaign')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_view_returning_closures_remain_discoverable_after_laravel_serialization(): void
    {
        $route = Route::get(
            '/cached-public-guide',
            static fn (): View => view('layouts.app')
        )->name('qa.cached-guide');
        $route->prepareForSerialization();
        $this->refresh();
        $this->assertSame(
            'Cached Public Guide | Detailing Devils',
            app(SeoAdminPages::class)->page('qa.cached-guide')->title
        );
        $this->assertContains('https://www.detailingdevils.com/cached-public-guide', $this->urls());
    }

    public function test_deleted_routes_disappear_even_if_old_config_or_published_overrides_remain(): void
    {
        Route::view('/temporary-guide', 'layouts.app')->name('qa.temporary');
        $this->app->instance(
            MetadataRepository::class,
            new class implements MetadataRepository
            {
                public function snapshot(): SeoSnapshot
                {
                    return new SeoSnapshot([
                        new SeoOverride('qa.temporary', ['title' => 'Stored old page title'], true, true),
                        new SeoOverride('fofo.show', ['title' => 'Stored old FOFO title'], true, true),
                    ]);
                }
            }
        );
        $this->refresh();
        $this->assertContains('https://www.detailingdevils.com/temporary-guide', $this->urls());
        $routes = new RouteCollection;
        foreach (Route::getRoutes() as $route) {
            if (! in_array($route->getName(), ['qa.temporary', 'fofo.show'], true)) {
                $routes->add($route);
            }
        }
        Route::setRoutes($routes);
        $this->refresh();
        $ids = array_map(fn ($page) => $page->id, app(SeoResolver::class)->inventory());
        $this->assertNotContains('qa.temporary', $ids);
        $this->assertNotContains('fofo.show', $ids);
        $this->assertNotContains('https://www.detailingdevils.com/temporary-guide', $this->urls());
        $this->assertNotContains(
            'https://www.detailingdevils.com/franchise-owned-franchise-operated-model',
            $this->urls()
        );
    }

    public function test_dynamic_routes_require_concrete_content_urls_and_ignore_removed_or_protected_routes(): void
    {
        Route::view('/future-studios/{slug}', 'layouts.app')->name('qa.studio');
        Route::view('/staff-studios/{slug}', 'layouts.app')->middleware('auth:web')->name('qa.staff-studio');
        $this->refresh();
        $this->assertCount(13, app(SeoResolver::class)->inventory());
        $this->app->instance(
            MetadataRepository::class,
            new class implements MetadataRepository
            {
                public function snapshot(): SeoSnapshot
                {
                    return new SeoSnapshot(content: [
                        new ContentMetadata(
                            'studio:published',
                            'studio',
                            'qa.studio',
                            ['slug' => 'verified-studio'],
                            'Verified studio',
                            published: true,
                            sitemap: true
                        ),
                        new ContentMetadata(
                            'studio:draft',
                            'studio',
                            'qa.studio',
                            ['slug' => 'draft-studio'],
                            'Draft studio',
                            sitemap: true
                        ),
                        new ContentMetadata(
                            'studio:staff',
                            'studio',
                            'qa.staff-studio',
                            ['slug' => 'staff'],
                            'Staff studio',
                            published: true,
                            sitemap: true
                        ),
                        new ContentMetadata(
                            'studio:removed',
                            'studio',
                            'qa.removed',
                            ['slug' => 'removed'],
                            'Removed studio',
                            published: true,
                            sitemap: true
                        ),
                    ]);
                }
            }
        );
        $this->refresh();
        $this->assertContains('https://www.detailingdevils.com/future-studios/verified-studio', $this->urls());
        $this->assertCount(14, $this->urls());
        $this->assertStringNotContainsString('{slug}', app(SeoSitemap::class)->xml());
    }

    public function test_static_sitemap_generation_adds_and_removes_discovered_urls_without_touching_the_public_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dd-seo-discovery-');
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
        try {
            Route::view('/new-route-guide', 'layouts.app')->name('qa.new');
            $this->refresh();
            $this->artisan('seo:sitemap', ['--output' => $path])->assertSuccessful();
            $this->assertStringContainsString('/new-route-guide</loc>', file_get_contents($path));
            Route::getRoutes()->getByName('qa.new')->defaults('_seo', ['robots' => 'noindex, follow']);
            $this->refresh();
            $this->artisan('seo:sitemap', ['--output' => $path, '--check' => true])->assertFailed();
            $this->artisan('seo:sitemap', ['--output' => $path])->assertSuccessful();
            $this->assertStringNotContainsString('/new-route-guide</loc>', file_get_contents($path));
            $this->assertFileDoesNotExist(public_path('sitemap.xml'));
        } finally {
            unlink($path);
        }
    }

    private function refresh(): void
    {
        Route::getRoutes()->refreshNameLookups();
        $this->app->forgetInstance(SeoResolver::class);
    }

    private function urls(): array
    {
        return app(SeoSitemap::class)->urls(app(SeoSitemap::class)->xml());
    }
}
