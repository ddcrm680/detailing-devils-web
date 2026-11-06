<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_optional_entrance_is_homepage_only_and_preserves_server_rendered_content(): void
    {
        config()->set('database.default', 'unconfigured');
        $document = new \DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query('//*[@id="home-entrance" and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="home-entrance" and @style="display: none"]')->length);
        $this->assertSame(1, $xpath->query('//link[contains(@href, "/assets/css/home-entrance.css")]')->length);
        $this->assertSame(1, $xpath->query('//script[contains(@src, "/assets/js/modules/home-entrance.js") and @defer]')->length);
        $this->assertSame(12, $xpath->query('//main/section')->length);
        $this->assertSame(1, $xpath->query('/html/head/title')->length);
        $this->assertSame(1, $xpath->query('/html/head/link[@rel="canonical"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="form" and @action="'.route('enquiry.store').'"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//main//a[@href]')->length);

        foreach (['studios.tour', 'prime.show', 'fofo.show', 'xclusive.show'] as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertDontSee('id="home-entrance"', false)
                ->assertDontSee('/assets/css/home-entrance.css', false)
                ->assertDontSee('/assets/js/modules/home-entrance.js', false);
        }
    }

    public function test_homepage_franchise_actions_preserve_order_and_named_destinations(): void
    {
        config()->set('database.default', 'unconfigured');
        $document = new \DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//section[@id="franchise"]//*[@class="fr-ctas"]/a');
        $destinations = [route('prime.show'), route('fofo.show'), route('xclusive.show'), '#contact'];
        $labels = ['Apply for Prime Franchise', 'Apply for FOFO Franchise', 'Apply for X-Clusive Franchise', 'Franchise Enquiry'];

        $this->assertSame(4, $links->length);
        foreach ($destinations as $index => $destination) {
            $this->assertSame($destination, $links->item($index)->getAttribute('href'));
            $this->assertStringContainsString($labels[$index], $links->item($index)->textContent);
        }
        $this->assertSame(1, $xpath->query('//section[@id="contact"]//form[@id="form" and @action="'.route('enquiry.store').'"]')->length);
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
