<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudioLocatorTest extends TestCase
{
    public function test_locator_renders_all_supplied_studios_without_a_database(): void
    {
        config()->set('database.default', 'unconfigured');

        $studios = File::json(resource_path('data/studios.json'));
        $this->assertNotEmpty($studios);
        foreach (['name', 'address', 'phones', 'state'] as $field) {
            $this->assertArrayHasKey($field, $studios[0]);
        }
        $this->assertTrue(File::exists(public_path('img/studio-locator.png')));

        $page = $this->get(route('studios.index'))->assertOk()
            ->assertSee('Studio Locator | Detailing Devils')
            ->assertSee('assets/css/studios.css')
            ->assertSee('assets/js/modules/studios.js')
            ->assertSee(count($studios).' locations found')
            ->assertSee('<header', false)
            ->assertSee('<footer', false);

        $this->assertSame(count($studios), substr_count($page->getContent(), 'class="studio-card"'));
        $this->assertSame(count($studios), substr_count($page->getContent(), 'data-state='));
        $this->assertSame(17, substr_count($page->getContent(), '<option value='));
        $this->assertStringNotContainsString('studio-stats', $page->getContent());
        $page->assertDontSee('studio-pagination')->assertDontSee('Page 1 of 2')
            ->assertDontSee('studio-presence')->assertDontSee('Studios by state')
            ->assertSee('id="studio-state"', false)->assertSee('Detailing Devils Head Office');
        $page->assertSee('Noida <span>(7)</span>', false)
            ->assertSee('<option value="Uttar Pradesh">Uttar Pradesh</option>', false);
    }

    public function test_locator_preserves_multiple_contacts_states_and_map_search_links(): void
    {
        $page = $this->get('/locate-a-store')->assertOk()
            ->assertSee('>Chandigarh</option>', false)
            ->assertSee('>Uttar Pradesh</option>', false)
            ->assertSee('Detailing Devils Malviya Nagar - Delhi')
            ->assertSee('Plot No - 7, Calgiri Marg')
            ->assertDontSee('Detailing Devils Malviya Nagar Delhi')
            ->assertSee('https://www.google.com/maps/search/?api=1', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $html = $page->getContent();
        $phoneCount = collect(File::json(resource_path('data/studios.json')))->sum(fn ($studio) => count($studio['phones']));
        $this->assertSame($phoneCount, substr_count($html, 'class="studio-phone"'));
        $this->assertStringContainsString('tel:+919211796769', $html);
        $this->assertStringContainsString('tel:+919555695695', $html);
        $this->assertStringContainsString('(+91) 92117 96769', $html);
        $this->assertStringContainsString('(+91) 95556 95695', $html);
        $this->assertStringNotContainsString('tbl_studio', $html);
    }

    #[DataProvider('mapsUrls')]
    public function test_directions_prefer_only_safe_google_maps_urls(mixed $storedUrl, ?string $expectedUrl, bool $fieldPresent = true): void
    {
        config()->set('database.default', 'unconfigured');
        $studio = [
            'name' => 'Test Studio',
            'address' => 'Example Road, Delhi',
            'phones' => ['(+91) 95556 95695'],
            'state' => 'Delhi',
            'map_url' => $storedUrl,
        ];
        if (! $fieldPresent) {
            unset($studio['map_url']);
        }
        File::partialMock()->shouldReceive('json')->once()->with(resource_path('data/studios.json'))->andReturn([$studio]);

        $page = $this->get(route('studios.index'))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($page->getContent());
        $links = (new DOMXPath($document))->query('//a[contains(@class, "studio-action--red")]');

        $this->assertSame(1, $links->length);
        $this->assertSame($expectedUrl ?? 'https://www.google.com/maps/search/?api=1&query=Test%20Studio%20Example%20Road%2C%20Delhi', $links->item(0)->getAttribute('href'));
        $this->assertSame('_blank', $links->item(0)->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
    }

    public static function mapsUrls(): array
    {
        return [
            'Google Maps' => ['https://www.google.com/maps/place/Test/@28,77?entry=ttu&test=1', 'https://www.google.com/maps/place/Test/@28,77?entry=ttu&test=1'],
            'short link' => ['https://maps.app.goo.gl/Example123?g_st=ic', 'https://maps.app.goo.gl/Example123?g_st=ic'],
            'maps subdomain' => ['https://maps.google.com/?q=Test', 'https://maps.google.com/?q=Test'],
            'older short link' => ['https://goo.gl/maps/Example123', 'https://goo.gl/maps/Example123'],
            'surrounding whitespace' => [" \thttps://maps.app.goo.gl/Example123\n", 'https://maps.app.goo.gl/Example123'],
            'null' => [null, null],
            'missing field' => [null, null, false],
            'empty' => ['', null],
            'whitespace only' => [" \t\n", null],
            'invalid URL' => ['not a URL', null],
            'javascript scheme' => ['javascript:alert(1)', null],
            'data scheme' => ['data:text/html,test', null],
            'HTTP' => ['http://maps.app.goo.gl/Example123', null],
            'protocol relative' => ['//maps.app.goo.gl/Example123', null],
            'untrusted host' => ['https://example.com/maps', null],
            'deceptive suffix' => ['https://maps.app.goo.gl.example.com/test', null],
            'deceptive prefix' => ['https://notgoogle.com/maps', null],
            'credentials' => ['https://example.com@maps.app.goo.gl/test', null],
            'unexpected port' => ['https://maps.app.goo.gl:8443/test', null],
            'Google non-map page' => ['https://www.google.com/search?q=test', null],
            'invalid type' => [[], null],
        ];
    }
}
