<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\EmptyMetadataRepository;
use App\Services\Seo\SeoDiagnostics;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiteMetadataTest extends TestCase
{
    public function test_existing_published_metadata_can_survive_removal_of_the_configured_page_fallbacks(): void
    {
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
        $baseline = app(SeoResolver::class)->inventory();
        $fields = app(SeoAdminPages::class);
        $snapshot = new SeoSnapshot(array_map(
            fn ($page) => new SeoOverride($page->id, $fields->fields($page), true, true),
            $baseline
        ));
        $this->app->instance(MetadataRepository::class, new class($snapshot) implements MetadataRepository
        {
            public function __construct(private SeoSnapshot $snapshot) {}

            public function snapshot(): SeoSnapshot
            {
                return $this->snapshot;
            }
        });
        config()->set('seo', array_diff_key(config('seo'), ['pages' => true]));
        $this->app->forgetInstance(SeoResolver::class);
        $this->assertCount(13, app(SeoResolver::class)->inventory());
        foreach ($baseline as $page) {
            $this->assertSame($fields->fields($page), $fields->fields(app(SeoResolver::class)->forPage($page->id)));
            $path = substr($page->canonical, strlen(config('seo.origin')));
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertSame([], app(SeoDiagnostics::class)->html($html, $page), $page->id);
        }
    }

    public function test_existing_page_fallbacks_remain_exact_when_publication_storage_is_unavailable(): void
    {
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
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
            $html = $this->get($path)->assertOk()->assertDontSee('Private synthetic publication failure')->getContent();
            $this->assertSame([], app(SeoDiagnostics::class)->html($html, $page), $page->id);
        }
    }

    public function test_registered_public_views_use_central_route_metadata_without_duplicate_sections(): void
    {
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
        $definitions = config('seo.pages');
        foreach ($definitions as $id => $definition) {
            if ($id === 'home') {
                continue;
            }
            $title = 'Central title '.$id.' < & QA';
            $description = 'Central description '.$id.' < & QA';
            $definitions[$id]['title'] = $title;
            $definitions[$id]['description'] = $description;
            config()->set('seo.pages', $definitions);
            $this->app->forgetInstance(SeoResolver::class);
            $path = route($definition['route'], $definition['parameters'] ?? [], false);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$this->get($path)->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $titles = $xpath->query('/html/head/title');
            $descriptions = $xpath->query('/html/head/meta[@name="description"]');
            $this->assertSame(1, $titles->length, $path);
            $this->assertSame(1, $descriptions->length, $path);
            $this->assertSame($title, $titles->item(0)->textContent, $path);
            $this->assertSame($description, $descriptions->item(0)->getAttribute('content'), $path);
        }
    }

    public function test_published_metadata_still_wins_over_central_page_fallbacks(): void
    {
        config()->set('database.default', 'unconfigured');
        DB::shouldReceive('connection')->never();
        foreach (config('seo.pages') as $id => $definition) {
            $title = 'Published title '.$id.' < & QA';
            $description = 'Published description '.$id.' < & QA';
            $snapshot = new SeoSnapshot([
                new SeoOverride($id, ['title' => $title, 'description' => $description], true, true),
            ]);
            $this->app->instance(MetadataRepository::class, new class($snapshot) implements MetadataRepository
            {
                public function __construct(private SeoSnapshot $fixture) {}

                public function snapshot(): SeoSnapshot
                {
                    return $this->fixture;
                }
            });
            $this->app->forgetInstance(SeoResolver::class);
            $path = route($definition['route'], $definition['parameters'] ?? [], false);
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$this->get($path)->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame($title, $xpath->query('/html/head/title')->item(0)->textContent, $path);
            $this->assertSame(
                $description,
                $xpath->query('/html/head/meta[@name="description"]')->item(0)->getAttribute('content'),
                $path
            );
        }
    }

    public function test_page_titles_keep_their_existing_overrides_when_the_shared_seo_title_changes(): void
    {
        config()->set('database.default', 'unconfigured');
        config()->set('site.seo_title', 'Shared SEO Title');

        foreach ([
            '/' => 'Shared SEO Title',
            '/detailing-devils-prime-form' => 'Franchise Application | Detailing Devils Prime',
            '/franchise-owned-franchise-operated-model' => 'FOFO Franchise Application | Detailing Devils',
            '/x-clusive-franchise-form' => 'X-Clusive Franchise Application | Detailing Devils',
            '/studio-tour' => 'X-Clusive Studio Tour | Detailing Devils',
            '/studio-tour?model=xclusive' => 'X-Clusive Studio Tour | Detailing Devils',
            '/studio-tour?model=fofo' => 'FOFO Studio Tour | Detailing Devils',
            '/studio-tour?model=prime' => 'Prime Studio Tour | Detailing Devils',
            '/locate-a-store' => 'Studio Locator | Detailing Devils',
            '/terms-of-use' => 'Terms of Use | Detailing Devils',
            '/privacy-policy' => 'Privacy Policy | Detailing Devils',
            '/refund-policy' => 'Refund and Returns Policy | Detailing Devils',
            '/cancellation-policy' => 'Cancellation Policy | Detailing Devils',
            '/warranty-terms-condition' => 'Warranty Terms & Conditions | Detailing Devils',
        ] as $path => $title) {
            $document = new \DOMDocument;
            @$document->loadHTML($this->get($path)
                ->assertOk()
                ->getContent());
            $xpath = new \DOMXPath($document);
            $titles = $xpath->query('/html/head/title');
            $this->assertSame(1, $titles->length, $path);
            $this->assertSame($title, $titles->item(0)->textContent, $path);
        }
    }

    public function test_discovered_app_pages_use_a_page_title_instead_of_homepage_seo_title(): void
    {
        config()->set('database.default', 'unconfigured');
        Route::view('/test-untitled-page', 'layouts.app');
        $site = config('site');
        $withoutSeoTitle = $site;
        unset($withoutSeoTitle['seo_title']);

        foreach ([
            [$site, 'Test Untitled Page | Detailing Devils'],
            [$withoutSeoTitle, 'Test Untitled Page | Detailing Devils'],
        ] as [$settings, $title]) {
            config()->set('site', $settings);
            $document = new \DOMDocument;
            @$document->loadHTML($this->get('/test-untitled-page')
                ->assertOk()
                ->getContent());
            $xpath = new \DOMXPath($document);
            $titles = $xpath->query('/html/head/title');
            $this->assertSame(1, $titles->length);
            $this->assertSame($title, $titles->item(0)->textContent);
        }
    }

    public function test_homepage_seo_title_is_independent_of_company_and_email_branding(): void
    {
        config()->set('database.default', 'unconfigured');
        $approvedTitle = 'Detailing Devils – Premium Car & Bike Detailing, Ceramic Coating & PPF Experts in India';
        $this->assertSame('Detailing Devils', config('site.brand'));
        $this->assertSame($approvedTitle, config('site.seo_title'));

        foreach ([$approvedTitle, 'Independent SEO Title & Search Keywords'] as $title) {
            config()->set('site.seo_title', $title);
            $document = new \DOMDocument;
            @$document->loadHTML($this->get('/')
                ->assertOk()
                ->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame($title, $xpath->query('/html/head/title')->item(0)->textContent);
            $verification = $xpath->query('/html/head/meta[@name="google-site-verification"]');
            $this->assertSame(1, $verification->length);
            $this->assertSame(
                config('site.google_site_verification'),
                $verification->item(0)->getAttribute('content')
            );

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
        config()->set('database.default', 'unconfigured');
        $token = 'verification<&"qa';
        config()->set('site.google_site_verification', $token);

        foreach ($this->pages() as $page) {
            $document = new \DOMDocument;
            @$document->loadHTML($this->get($page)
                ->assertOk()
                ->getContent());
            $xpath = new \DOMXPath($document);
            $all = $xpath->query('//meta[@name="google-site-verification"]');
            $head = $xpath->query('/html/head/meta[@name="google-site-verification"]');

            $this->assertSame(1, $all->length, $page);
            $this->assertSame(1, $head->length, $page);
            $this->assertSame($token, $head->item(0)->getAttribute('content'), $page);
            $this->assertSame(1, $xpath->query('/html/head/title')->length, $page);
            $this->assertSame(1, $xpath->query('/html/head/meta[@name="description"]')->length, $page);
            $this->assertSame(1, $xpath->query('/html/head/meta[@name="viewport"]')->length, $page);
        }
    }

    public function test_empty_verification_configuration_omits_the_tag(): void
    {
        config()->set('database.default', 'unconfigured');
        config()->set('site.google_site_verification', null);

        foreach ($this->pages() as $page) {
            $this->get($page)
                ->assertOk()
                ->assertDontSee('name="google-site-verification"', false);
        }
    }

    private function pages(): array
    {
        return [
            '/',
            '/studio-tour?model=prime',
            '/studio-tour?model=fofo',
            '/studio-tour?model=xclusive',
            '/detailing-devils-prime-form',
            '/franchise-owned-franchise-operated-model',
            '/x-clusive-franchise-form',
        ];
    }
}
