<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SiteMetadataTest extends TestCase
{
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
            @$document->loadHTML($this->get($path)->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $titles = $xpath->query('/html/head/title');
            $this->assertSame(1, $titles->length, $path);
            $this->assertSame($title, $titles->item(0)->textContent, $path);
        }
    }

    public function test_app_layout_uses_the_shared_seo_title_then_the_company_name_as_fallback(): void
    {
        config()->set('database.default', 'unconfigured');
        Route::view('/test-untitled-page', 'layouts.app');
        $site = config('site');
        $withoutSeoTitle = $site;
        unset($withoutSeoTitle['seo_title']);

        foreach ([[$site, $site['seo_title']], [$withoutSeoTitle, 'Detailing Devils']] as [$settings, $title]) {
            config()->set('site', $settings);
            $document = new \DOMDocument;
            @$document->loadHTML($this->get('/test-untitled-page')->assertOk()->getContent());
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
            @$document->loadHTML($this->get('/')->assertOk()->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame($title, $xpath->query('/html/head/title')->item(0)->textContent);
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
        config()->set('database.default', 'unconfigured');
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
            $this->get($page)->assertOk()->assertDontSee('name="google-site-verification"', false);
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
