<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudioLocatorTest extends TestCase
{
    public function test_locator_renders_only_active_supplied_studios_without_a_database(): void
    {
        config()->set('database.default', 'unconfigured');

        $studios = File::json(resource_path('data/studios.json'));
        $this->assertNotEmpty($studios);
        foreach (['name', 'address', 'phones', 'state'] as $field) {
            $this->assertArrayHasKey($field, $studios[0]);
        }
        $studios = collect($studios)->whereStrict('status', 'active')->values()->all();
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
        $this->assertSame(collect($studios)->pluck('state')->unique()->count() + 1, substr_count($page->getContent(), '<option value='));
        $this->assertSame(array_column($studios, 'name'), array_column($page->viewData('studios'), 'name'));
        $this->assertStringNotContainsString('studio-stats', $page->getContent());
        $page->assertDontSee('studio-pagination')->assertDontSee('Page 1 of 2')
            ->assertDontSee('studio-presence')->assertDontSee('Studios by state')
            ->assertSee('id="studio-state"', false);
    }

    public function test_locator_preserves_multiple_contacts_states_and_map_search_links(): void
    {
        config()->set('database.default', 'unconfigured');
        $studios = collect(File::json(resource_path('data/studios.json')))
            ->map(fn ($studio) => array_replace($studio, ['status' => 'active']))->all();
        // Exercise the fallback even when every supplied studio has a map URL.
        $studios[0]['map_url'] = '';
        File::partialMock()->shouldReceive('json')->once()->with(resource_path('data/studios.json'))->andReturn($studios);

        $page = $this->get('/locate-a-store')->assertOk()
            ->assertSee('>Chandigarh</option>', false)
            ->assertSee('>Uttar Pradesh</option>', false)
            ->assertSee('Detailing Devils Malviya Nagar - Delhi')
            ->assertSee('Plot No - 7, Calgiri Marg')
            ->assertDontSee('Detailing Devils Malviya Nagar Delhi')
            ->assertSee('https://www.google.com/maps/search/?api=1', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $html = $page->getContent();
        $phoneCount = collect($studios)->sum(fn ($studio) => count($studio['phones']));
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
            'status' => 'active',
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

    #[DataProvider('studioStatuses')]
    public function test_only_explicitly_active_studios_reach_cards_counts_states_and_chips(array $status, bool $visible): void
    {
        config()->set('database.default', 'unconfigured');
        $studios = [
            ['name' => 'Visible Noida Studio', 'address' => 'Example Road', 'phones' => [], 'state' => 'Uttar Pradesh', 'status' => 'active'],
            ['name' => 'Candidate Jaipur Studio', 'address' => 'Noida Candidate Road', 'phones' => [], 'state' => 'Rajasthan'] + $status,
        ];
        File::partialMock()->shouldReceive('json')->times(2)->with(resource_path('data/studios.json'))->andReturn($studios);

        foreach (['/locate-a-store', '/locate-a-store?q=Candidate&state=Rajasthan'] as $url) {
            $page = $this->get($url)->assertOk();
            $expectedCount = $visible ? 2 : 1;
            $this->assertCount($expectedCount, $page->viewData('studios'));
            $this->assertSame($expectedCount, substr_count($page->getContent(), 'class="studio-card"'));
            $page->assertSee($expectedCount.' locations found')
                ->assertSee('Noida <span>('.$expectedCount.')</span>', false);
            $this->assertSame($visible ? ['Rajasthan', 'Uttar Pradesh'] : ['Uttar Pradesh'], $page->viewData('availableStates')->all());

            if ($visible) {
                $page->assertSee('Candidate Jaipur Studio')->assertSee('Jaipur <span>(1)</span>', false);
            } else {
                $page->assertDontSee('Candidate Jaipur Studio')->assertDontSee('Noida Candidate Road')
                    ->assertDontSee('<option value="Rajasthan">', false)->assertDontSee('data-studio-search="Jaipur"', false);
            }
        }
    }

    public static function studioStatuses(): array
    {
        return [
            'active' => [['status' => 'active'], true],
            'inactive' => [['status' => 'inactive'], false],
            'missing' => [[], false],
            'empty' => [['status' => ''], false],
            'null' => [['status' => null], false],
            'unknown' => [['status' => 'pending'], false],
            'wrong case' => [['status' => 'ACTIVE'], false],
            'whitespace' => [['status' => ' active '], false],
            'boolean' => [['status' => true], false],
            'number' => [['status' => 1], false],
            'array' => [['status' => ['active']], false],
        ];
    }

    public function test_all_inactive_studios_produce_an_empty_directory(): void
    {
        config()->set('database.default', 'unconfigured');
        File::partialMock()->shouldReceive('json')->once()->with(resource_path('data/studios.json'))->andReturn([
            ['name' => 'Inactive Studio', 'address' => 'Jaipur', 'phones' => [], 'state' => 'Rajasthan', 'status' => 'inactive'],
        ]);

        $page = $this->get('/locate-a-store')->assertOk()->assertSee('0 locations found')
            ->assertDontSee('Inactive Studio')->assertDontSee('class="studio-card"', false);
        $this->assertSame([], $page->viewData('studios'));
        $this->assertTrue($page->viewData('availableStates')->isEmpty());
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
