<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_franchise_actions_preserve_order_and_named_destinations(): void
    {
        config()->set('database.default', 'unconfigured');
        $document = new \DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $links = (new \DOMXPath($document))->query('//*[@class="fr-ctas"]/a');
        $this->assertSame(4, $links->length);
        foreach (['prime.show', 'fofo.show', 'xclusive.show', 'studios.tour'] as $index => $name) {
            $this->assertSame(route($name), $links->item($index)->getAttribute('href'));
        }
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
