<?php

namespace Tests\Feature;

use Tests\TestCase;

class SiteMetadataTest extends TestCase
{
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
