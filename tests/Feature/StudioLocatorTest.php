<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StudioLocatorTest extends TestCase
{
    public function test_locator_renders_all_supplied_studios_without_a_database(): void
    {
        config()->set('database.default', 'unconfigured');

        $studios = File::json(resource_path('data/studios.json'));
        $this->assertCount(63, $studios);
        $this->assertSame(['name', 'address', 'phones', 'state'], array_keys($studios[0]));

        $page = $this->get(route('studios.index'))->assertOk()
            ->assertSee('Studio Locator | Detailing Devils')
            ->assertSee('assets/css/studios.css')
            ->assertSee('assets/js/modules/studios.js')
            ->assertSee('63 locations found')
            ->assertSee('<header', false)
            ->assertSee('<footer', false);

        $this->assertSame(63, substr_count($page->getContent(), 'class="studio-card"'));
        $this->assertSame(63, substr_count($page->getContent(), 'data-state='));
        $page->assertSee('Page 1 of 2')->assertSee('Detailing Devils Head Office');
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
        $this->assertStringContainsString('tel:+919211796769', $html);
        $this->assertStringContainsString('tel:+919555695695', $html);
        $this->assertStringContainsString('(+91) 92117 96769', $html);
        $this->assertStringContainsString('(+91) 95556 95695', $html);
        $this->assertStringNotContainsString('tbl_studio', $html);
    }
}
