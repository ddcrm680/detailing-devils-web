<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class StudioTourTest extends TestCase
{
    public function test_studio_tour_uses_shared_layout_and_existing_franchise_forms_without_a_database(): void
    {
        config()->set('database.default', 'unconfigured');

        $page = $this->get(route('studios.tour'))->assertOk()
            ->assertSee('Studio Tour | Detailing Devils')
            ->assertSee('<header', false)
            ->assertSee('<footer', false)
            ->assertSee('assets/css/studio-tour.css')
            ->assertSee('assets/js/modules/studio-tour.js')
            ->assertSee('frames/studio-tour/')
            ->assertSee('This could be')
            ->assertSee('Replay Tour')
            ->assertSee(route('prime.show'))
            ->assertSee(route('fofo.show'));

        $html = $page->getContent();
        $this->assertSame(5, substr_count($html, 'class="tour-page__caption"'));
        $this->assertSame(2, substr_count($html, 'class="tour-page__tier"'));
        $this->assertStringNotContainsString('Red Carpet', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_original_frame_sequence_is_complete(): void
    {
        $frames = glob(public_path('frames/studio-tour/*.avif'));
        $this->assertCount(330, $frames);
        $this->assertFileExists(public_path('frames/studio-tour/001.avif'));
        $this->assertFileExists(public_path('frames/studio-tour/330.avif'));
    }

    public function test_shared_header_keeps_tour_cta_and_menu_links_to_check_warranty_on_normal_pages(): void
    {
        config()->set('database.default', 'unconfigured');

        foreach (['/', route('studios.index'), route('prime.show'), route('fofo.show'), route('legal.privacy')] as $url) {
            $xpath = $this->xpath($this->get($url)->assertOk()->getContent());
            $cta = $xpath->query('//header[@id="hdr"]//div[contains(@class, "left")]/a');

            $this->assertSame(1, $cta->length, "Expected one header CTA on {$url}.");
            $this->assertSame('Franchise / Studio Tour →', trim($cta->item(0)->textContent));
            $this->assertSame(route('studios.tour'), $cta->item(0)->getAttribute('href'));
            $this->assertSame(1, $xpath->query('//header[@id="hdr"]//a[contains(@class, "brand")]')->length);
            $this->assertSame(1, $xpath->query('//header[@id="hdr"]//a[contains(@class, "ph-no")]')->length);
            $this->assertSame(1, $xpath->query('//header[@id="hdr"]//button[@id="burger"]')->length);
            $this->assertSame(1, $xpath->query('//div[@id="menu"][@inert][@data-lenis-prevent]')->length);
            $menuLinks = $xpath->query('//div[@id="menu"]/nav/a');
            $this->assertSame(8, $menuLinks->length);
            $this->assertSame('https://www.devilscrm.detailingdevils.com/check-warranty', $menuLinks->item(5)->getAttribute('href'));
            $this->assertSame('06Warranty Check', trim($menuLinks->item(5)->textContent));
            $this->assertSame('07Shop', trim($menuLinks->item(6)->textContent));
            $this->assertSame('https://www.shop.detailingdevils.com/', $menuLinks->item(6)->getAttribute('href'));
            $this->assertSame('_blank', $menuLinks->item(6)->getAttribute('target'));
            $this->assertSame('08Contact', trim($menuLinks->item(7)->textContent));
            $this->assertSame($url === '/' ? '#contact' : url('/').'#contact', $menuLinks->item(7)->getAttribute('href'));
            $this->assertSame(1, $xpath->query('//head/link[@href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"]')->length);
            $this->assertSame(0, $xpath->query('//div[@id="menu"]//a[@href="'.route('studios.tour').'"]')->length);
        }

        $home = $this->xpath($this->get('/')->assertOk()->getContent());
        $this->assertSame(1, $home->query('//section[@id="franchise"]//a[@href="'.route('studios.tour').'"]')->length);
        $serviceLinks = $home->query('//footer//div[h4="Services"]/ul/li/a');
        $this->assertSame(5, $serviceLinks->length);
        foreach (array_slice(config('site.services'), 0, 4) as $index => $service) {
            $this->assertSame($service['title'], $serviceLinks->item($index)->textContent);
            $this->assertSame('#services', $serviceLinks->item($index)->getAttribute('href'));
        }
        $this->assertSame('Warranty Check', $serviceLinks->item(4)->textContent);
        $this->assertSame('https://www.devilscrm.detailingdevils.com/check-warranty', $serviceLinks->item(4)->getAttribute('href'));
        foreach (config('site.social') as $url) {
            $this->assertSame(1, $home->query('//footer//a[@href="'.$url.'"]')->length);
        }
    }

    public function test_tour_header_applies_to_its_models_section(): void
    {
        $xpath = $this->xpath($this->get(route('studios.tour'))->assertOk()->getContent());
        $cta = $xpath->query('//header[@id="hdr"]//div[contains(@class, "left")]/a');

        $this->assertSame(1, $cta->length);
        $this->assertSame('Apply Now →', trim($cta->item(0)->textContent));
        $this->assertSame(route('studios.tour').'#models', $cta->item(0)->getAttribute('href'));
        $this->assertSame(1, $xpath->query('//*[@id="models"]')->length);
    }

    public function test_prime_reuses_the_shared_footer_without_duplicate_controls(): void
    {
        config()->set('database.default', 'unconfigured');

        $page = $this->get(route('prime.show'))->assertOk();
        $xpath = $this->xpath($page->getContent());

        $this->assertSame(1, $xpath->query('//footer')->length);
        $this->assertSame(0, $xpath->query('//footer[@class="site-footer"]')->length);
        foreach (['Services', 'Company', 'Follow'] as $heading) {
            $this->assertSame(1, $xpath->query('//footer//h4[text()="'.$heading.'"]')->length);
        }
        foreach ([
            'tel:'.config('site.phone_link'),
            'mailto:'.config('site.email'),
            route('studios.index'),
            route('legal.terms'),
            route('legal.privacy'),
            'https://www.devilscrm.detailingdevils.com/check-warranty',
        ] as $url) {
            $this->assertSame(1, $xpath->query('//footer//a[@href="'.$url.'"]')->length);
        }
        $whatsapp = $xpath->query('//div[@class="whats-btn"]/a');
        $this->assertSame(1, $whatsapp->length);
        $this->assertSame('https://api.whatsapp.com/send?phone='.config('site.whatsapp_phone'), $whatsapp->item(0)->getAttribute('href'));
        $this->assertSame('Chat with Detailing Devils on WhatsApp', $whatsapp->item(0)->getAttribute('aria-label'));
        $this->assertSame(1, $xpath->query('//form[@id="franchiseForm"]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="otpModal"]')->length);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
