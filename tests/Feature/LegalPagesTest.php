<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_each_policy_uses_its_original_url_shared_layout_and_own_content(): void
    {
        $descriptions = [];

        foreach ([
            ['legal.terms', '/terms-of-use', 'Terms of Use', 'Use Of Website', 'Indemnification', 'Privacy Policy'],
            ['legal.privacy', '/privacy-policy', 'Privacy Policy', 'Information Collection and Use', 'Changes to This Privacy Policy', 'Refund and Returns Policy'],
            ['legal.returns', '/refund-policy', 'Refund and Returns Policy', 'Return Process:', 'Exchange Process', 'Cancellation Period'],
            ['legal.cancellation', '/cancellation-policy', 'Cancellation Policy', 'Cancellation Period', 'Policy Updates', 'Disclaimer Of Warranty'],
        ] as [$route, $path, $heading, $firstSection, $lastSection, $otherPolicy]) {
            $this->assertSame(url($path), route($route));

            $response = $this->get($path)->assertOk();
            $xpath = $this->xpath($response->getContent());

            $this->assertSame(1, $xpath->query('//html/head/title')->length);
            $description = $xpath->query('//html/head/meta[@name="description"]')->item(0)?->getAttribute('content');
            $this->assertNotEmpty($description);
            $descriptions[] = $description;
            $this->assertSame(1, $xpath->query('//main')->length);
            $this->assertSame(1, $xpath->query('//header[contains(concat(" ", normalize-space(@class), " "), " hdr ")]')->length);
            $this->assertSame(1, $xpath->query('//footer')->length);
            $this->assertSame($heading, trim($xpath->query('//main//h1')->item(0)->textContent));
            $this->assertSame(1, $xpath->query('//main//*[self::h2 or self::li][contains(., "'.$firstSection.'")]')->length);
            $this->assertSame(1, $xpath->query('//main//*[self::h2 or self::li][contains(., "'.$lastSection.'")]')->length);
            $this->assertStringContainsString($heading.' | Detailing Devils', $xpath->query('//html/head/title')->item(0)->textContent);
            $this->assertStringNotContainsString($otherPolicy, $xpath->query('//main')->item(0)->textContent);
        }

        $this->assertCount(4, array_unique($descriptions));
    }

    public function test_original_deadlines_conditions_and_support_contact_are_present(): void
    {
        $support = config('site.support_email');
        $this->assertSame('info@detailingdevils.com', config('site.email'));

        $this->get(route('legal.terms'))->assertOk()
            ->assertSeeText('Disclaimer Of Warranty')
            ->assertSeeText('However, if the product is out of stock the seller shall completely refund the amount paid.');

        $this->get(route('legal.privacy'))->assertOk()
            ->assertSeeText('January 2017')
            ->assertSeeText(config('site.address'))
            ->assertDontSeeText('D-50, Sector 2, Noida,')
            ->assertDontSee('stagingweb.detailingdevils.com/tips')
            ->assertSee('href="tel:'.config('site.phone_link').'"', false)
            ->assertSeeText(config('site.phone'))
            ->assertSeeText('Call our team')
            ->assertSee('id="cookies"', false)
            ->assertSee('mailto:'.$support, false)
            ->assertSeeText($support);

        $this->get(route('legal.returns'))->assertOk()
            ->assertSeeText('within 10 days of purchase')
            ->assertSeeText('damaged, defective or not as shown on the website')
            ->assertSee('mailto:'.$support, false)
            ->assertSeeText($support);

        $this->get(route('legal.cancellation'))->assertOk()
            ->assertSeeText('within 24 hours of placing it')
            ->assertSeeText('within 5 – 7 business days')
            ->assertSee('mailto:'.$support, false)
            ->assertSeeText($support);
    }

    public function test_footer_links_point_to_the_four_policy_routes_and_privacy_cookies_section(): void
    {
        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        foreach ([
            'Terms & Conditions' => route('legal.terms'),
            'Privacy Policy' => route('legal.privacy'),
            'Return & Refund Policy' => route('legal.returns'),
            'Cancellation Policy' => route('legal.cancellation'),
            'Cookies' => route('legal.privacy').'#cookies',
        ] as $label => $url) {
            $links = $xpath->query('//footer//nav[@aria-label="Legal policies"]/a[@href="'.$url.'"]');
            $this->assertSame(1, $links->length, "Footer link for {$label} is missing.");
            $this->assertSame($label, trim($links->item(0)->textContent));
        }
    }

    public function test_policy_support_email_uses_site_configuration(): void
    {
        config()->set('site.support_email', 'help@example.test');

        foreach (['legal.privacy', 'legal.returns', 'legal.cancellation'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('mailto:help@example.test', false)
                ->assertSeeText('help@example.test');
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
