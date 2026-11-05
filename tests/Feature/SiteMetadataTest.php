<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\SeoDiagnostics;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiteMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        Http::preventStrayRequests();
        Mail::fake();
        $this->useSnapshot(new SeoSnapshot);
    }

    public function test_existing_publication_format_preserves_metadata_for_all_discovered_pages(): void
    {
        $fields = app(SeoAdminPages::class);
        $overrides = [];
        $expected = [];
        foreach (app(SeoResolver::class)->inventory() as $page) {
            $expected[$page->id] = array_replace($fields->fields($page), [
                'title' => 'Published title '.$page->id.' < & QA',
                'description' => 'Published description '.$page->id.' < & QA',
                'og_title' => 'Published social title '.$page->id,
                'twitter_description' => 'Published Twitter description '.$page->id,
            ]);
            $overrides[] = new SeoOverride($page->id, $expected[$page->id], true, true);
        }
        $codec = app(SeoPublicationCodec::class);
        $payload = $codec->encode(new SeoSnapshot($overrides));
        $this->useSnapshot($codec->decode($payload));
        $this->assertCount(13, app(SeoResolver::class)->inventory());
        $this->assertArrayNotHasKey('pages', config('seo'));
        foreach (app(SeoResolver::class)->inventory() as $page) {
            $this->assertSame($expected[$page->id], $fields->fields($page));
            $path = substr($page->canonical, strlen(config('seo.origin')));
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame([], app(SeoDiagnostics::class)->html($html, $page), $page->id);
        }
        $this->assertSame($payload, $codec->encode($codec->decode($payload)));
    }

    public function test_storage_failure_uses_generic_metadata_without_disclosing_the_exception(): void
    {
        $baseline = app(SeoResolver::class)->inventory();
        $this->app->instance(MetadataRepository::class, new class implements MetadataRepository
        {
            public function snapshot(): SeoSnapshot
            {
                throw new \RuntimeException('Private synthetic publication failure');
            }
        });
        foreach ($baseline as $page) {
            $this->app->forgetInstance(SeoResolver::class);
            $path = substr($page->canonical, strlen(config('seo.origin')));
            $response = $this->get($path)->assertOk()->assertDontSee('Private synthetic publication failure');
            $this->assertSame([], app(SeoDiagnostics::class)->html($response->getContent(), $page), $page->id);
        }
    }

    public function test_unpublished_pages_use_generic_route_copy_instead_of_homepage_marketing_metadata(): void
    {
        config()->set('site.seo_title', 'Homepage marketing title');
        config()->set('site.description', 'Homepage marketing description');
        foreach ([
            '/' => 'Home | Detailing Devils',
            '/detailing-devils-prime-form' => 'Detailing Devils Prime Form | Detailing Devils',
            '/franchise-owned-franchise-operated-model' => 'Franchise Owned Franchise Operated Model | Detailing Devils',
            '/x-clusive-franchise-form' => 'X Clusive Franchise Form | Detailing Devils',
        ] as $path => $title) {
            $document = new \DOMDocument;
            @$document->loadHTML($this->get($path)->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('/html/head/title')->length);
            $this->assertSame($title, $xpath->evaluate('string(/html/head/title)'));
            $this->assertStringNotContainsString(
                'Homepage marketing',
                $xpath->evaluate('string(/html/head/meta[@name="description"]/@content)')
            );
        }
    }

    public function test_published_titles_are_independent_of_legacy_site_title_configuration(): void
    {
        $overrides = [];
        foreach (app(SeoResolver::class)->inventory() as $page) {
            $overrides[] = new SeoOverride($page->id, ['title' => 'Published '.$page->id], true);
        }
        $this->useSnapshot(new SeoSnapshot($overrides));
        config()->set('site.seo_title', 'Unrelated legacy marketing title');
        foreach (app(SeoResolver::class)->inventory() as $page) {
            $this->assertSame('Published '.$page->id, $page->title);
            $path = substr($page->canonical, strlen(config('seo.origin')));
            $this->get($path)->assertOk()->assertSee('<title>'.e($page->title).'</title>', false);
        }
    }

    public function test_unused_blade_metadata_sections_cannot_override_central_metadata(): void
    {
        Route::get('/section-guide', function (): View {
            app('view')->startSection('title', 'Old Blade title');
            app('view')->startSection('description', 'Old Blade description');

            return view('layouts.app');
        })->name('qa.section-guide');
        Route::getRoutes()->refreshNameLookups();
        $this->useSnapshot(new SeoSnapshot([
            new SeoOverride('qa.section-guide', ['title' => 'Published guide title'], true),
        ]));
        $this->get('/section-guide')->assertOk()
            ->assertSee('<title>Published guide title</title>', false)
            ->assertDontSee('Old Blade title')
            ->assertDontSee('Old Blade description');
    }

    public function test_discovered_pages_render_metadata_without_a_manually_registered_entry(): void
    {
        Route::view('/test-untitled-page', 'layouts.app');
        Route::getRoutes()->refreshNameLookups();
        $this->app->forgetInstance(SeoResolver::class);
        $this->get('/test-untitled-page')->assertOk()
            ->assertSee('<title>Test Untitled Page | Detailing Devils</title>', false)
            ->assertSee('content="Explore Test Untitled Page at Detailing Devils."', false);
    }

    public function test_homepage_published_seo_title_is_independent_of_company_and_email_branding(): void
    {
        $approvedTitle = 'Detailing Devils – Premium Car & Bike Detailing, Ceramic Coating & PPF Experts in India';
        $this->assertSame('Detailing Devils', config('site.brand'));
        $this->assertSame($approvedTitle, config('site.seo_title'));
        foreach ([$approvedTitle, 'Independent SEO Title & Search Keywords'] as $title) {
            $this->useSnapshot(new SeoSnapshot([new SeoOverride('home', ['title' => $title], true)]));
            $document = new \DOMDocument;
            @$document->loadHTML($this->get('/')->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame($title, $xpath->evaluate('string(/html/head/title)'));
            $verification = $xpath->query('/html/head/meta[@name="google-site-verification"]');
            $this->assertSame(1, $verification->length);
            $this->assertSame(config('site.google_site_verification'), $verification->item(0)->getAttribute('content'));
            foreach (['fofo', 'prime', 'xclusive'] as $model) {
                $html = view('emails.franchise.'.$model.'-applicant', ['name' => 'Synthetic QA'])->render();
                $this->assertStringContainsString('Detailing Devils', $html);
                $this->assertStringNotContainsString($title, $html);
                $this->assertStringNotContainsString(e($title), $html);
            }
        }
    }

    public function test_verification_is_configured_once_in_each_shared_page_head_and_escaped(): void
    {
        $token = 'verification<&"qa';
        config()->set('site.google_site_verification', $token);
        foreach ($this->pages() as $page) {
            $document = new \DOMDocument;
            @$document->loadHTML($this->get($page)->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $all = $xpath->query('//meta[@name="google-site-verification"]');
            $head = $xpath->query('/html/head/meta[@name="google-site-verification"]');
            $this->assertSame(1, $all->length, $page);
            $this->assertSame(1, $head->length, $page);
            $this->assertSame($token, $head->item(0)->getAttribute('content'), $page);
            foreach (['title', 'meta[@name="description"]', 'meta[@name="viewport"]'] as $selector) {
                $this->assertSame(1, $xpath->query('/html/head/'.$selector)->length, $page);
            }
        }
    }

    public function test_empty_verification_configuration_omits_the_tag(): void
    {
        config()->set('site.google_site_verification', null);
        foreach ($this->pages() as $page) {
            $this->get($page)->assertOk()->assertDontSee('name="google-site-verification"', false);
        }
    }

    private function useSnapshot(SeoSnapshot $snapshot): void
    {
        $this->app->instance(MetadataRepository::class, new class($snapshot) implements MetadataRepository
        {
            public function __construct(private SeoSnapshot $snapshot) {}

            public function snapshot(): SeoSnapshot
            {
                return $this->snapshot;
            }
        });
        $this->app->forgetInstance(SeoResolver::class);
    }

    private function pages(): array
    {
        return array_map(
            fn ($page) => substr($page->canonical, strlen(config('seo.origin'))),
            app(SeoResolver::class)->inventory()
        );
    }
}
