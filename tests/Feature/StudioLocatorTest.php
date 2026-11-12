<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Models\Studio;
use App\Services\Seo\EmptyMetadataRepository;
use App\Services\Seo\SeoOverride;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudioLocatorTest extends TestCase
{
    private int $fixtureNumber = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]);
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        (require database_path('migrations/2026_11_12_000000_create_studio_cms_tables.php'))->up();
        $this->app->instance(MetadataRepository::class, new EmptyMetadataRepository);
    }

    private function createStudio(array $fields = []): Studio
    {
        return Studio::create(array_replace([
            'name' => 'Test Studio', 'slug' => 'fixture-'.++$this->fixtureNumber,
            'address' => 'Example Road, Delhi', 'state' => 'Delhi',
            'phones' => ['(+91) 95556 95695'], 'map_url' => null, 'sort_order' => 0,
        ], $fields));
    }

    private function approvedReference(): array
    {
        return json_decode(file_get_contents(resource_path('data/studios.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_locator_preserves_all_approved_records_contacts_order_and_card_markup(): void
    {
        $studios = $this->approvedReference();
        foreach ($studios as $position => $studio) {
            $this->createStudio($studio + ['sort_order' => $position + 1]);
        }

        $this->assertNotEmpty($studios);
        foreach (['name', 'address', 'phones', 'state'] as $field) {
            $this->assertArrayHasKey($field, $studios[0]);
        }
        $studios = collect($studios)->whereStrict('status', 'active')->values()->all();
        $this->assertTrue(File::exists(public_path('img/studio-locator.png')));

        $page = $this->get(route('studios.index'))->assertOk()
            ->assertSee('<title>Locate A Store | Detailing Devils</title>', false)
            ->assertSee('assets/css/studios.css')
            ->assertSee('assets/js/modules/studios.js')
            ->assertSee(count($studios).' locations found')
            ->assertSee('<header', false)
            ->assertSee('<footer', false);

        $this->assertSame(count($studios), substr_count($page->getContent(), 'class="studio-card"'));
        $this->assertSame(count($studios), substr_count($page->getContent(), 'data-state='));
        $this->assertSame(collect($studios)->pluck('state')->unique()->count() + 1, substr_count($page->getContent(), '<option value='));
        $models = $page->viewData('studios');
        $this->assertInstanceOf(Collection::class, $models);
        $this->assertInstanceOf(Studio::class, $models->first());
        $this->assertSame(array_column($studios, 'name'), $models->pluck('name')->all());
        $this->assertSame(array_column($studios, 'phones'), $models->pluck('phones')->all());
        $this->assertSame(
            array_sum(array_map(fn ($studio) => count($studio['phones']), $studios)),
            substr_count($page->getContent(), 'class="studio-phone"')
        );
        $expected = array_map(fn ($studio) => $studio + ['directions_url' => trim($studio['map_url'])], $studios);
        $reference = view('studios.index', [
            'studios' => $expected,
            'availableStates' => collect($studios)->pluck('state')->unique()->sort()->values(),
        ])->render();
        $cards = function (string $html): array {
            $document = new DOMDocument;
            @$document->loadHTML($html);

            return array_map(
                fn ($node) => $document->saveHTML($node),
                iterator_to_array((new DOMXPath($document))->query('//article[@class="studio-card"]'))
            );
        };
        $this->assertSame($cards($reference), $cards($page->getContent()));
        $this->assertStringNotContainsString('studio-stats', $page->getContent());
        $page->assertDontSee('studio-pagination')->assertDontSee('Page 1 of 2')
            ->assertDontSee('studio-presence')->assertDontSee('Studios by state')
            ->assertSee('id="studio-state"', false);
    }

    public function test_locator_preserves_multiple_contacts_states_and_map_search_links(): void
    {
        $studios = collect($this->approvedReference())
            ->map(fn ($studio) => array_replace($studio, ['status' => 'active']))->all();
        // Exercise the fallback even when every supplied studio has a map URL.
        $studios[0]['map_url'] = '';
        foreach ($studios as $position => $studio) {
            $this->createStudio($studio + ['sort_order' => $position + 1]);
        }

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
        $this->createStudio($studio);

        $page = $this->get(route('studios.index'))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($page->getContent());
        $links = (new DOMXPath($document))->query('//a[contains(@class, "studio-action--red")]');

        $this->assertSame(1, $links->length);
        $this->assertSame($expectedUrl ?? 'https://www.google.com/maps/search/?api=1&query=Test%20Studio%20Example%20Road%2C%20Delhi', $links->item(0)->getAttribute('href'));
        $this->assertSame('_blank', $links->item(0)->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
        $this->assertSame(
            1,
            (new DOMXPath($document))->query('//a[contains(@class, "studio-action")][@href="tel:+919555695695"]')->length
        );
    }

    #[DataProvider('studioStatuses')]
    public function test_cms_status_controls_public_cards_counts_states_and_chips(array $status, bool $visible): void
    {
        $studios = [
            ['name' => 'Visible Noida Studio', 'address' => 'Example Road', 'phones' => [], 'state' => 'Uttar Pradesh', 'status' => 'active'],
            ['name' => 'Candidate Jaipur Studio', 'address' => 'Noida Candidate Road', 'phones' => [], 'state' => 'Rajasthan'] + $status,
        ];
        foreach ($studios as $studio) {
            $this->createStudio($studio);
        }

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
            'draft' => [['status' => 'draft'], false],
            'missing' => [[], false],
        ];
    }

    public function test_all_inactive_studios_produce_an_empty_directory(): void
    {
        $this->createStudio(['name' => 'Inactive Studio', 'state' => 'Rajasthan', 'status' => 'inactive']);

        $page = $this->get('/locate-a-store')->assertOk()->assertSee('0 locations found')
            ->assertDontSee('Inactive Studio')->assertDontSee('class="studio-card"', false);
        $this->assertInstanceOf(Collection::class, $page->viewData('studios'));
        $this->assertTrue($page->viewData('studios')->isEmpty());
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
        ];
    }

    public function test_database_query_is_active_only_ordered_and_never_reads_json_or_gallery(): void
    {
        $late = $this->createStudio(['name' => 'Late', 'status' => 'active', 'sort_order' => 10]);
        $first = $this->createStudio(['name' => 'First', 'status' => 'active', 'sort_order' => 1]);
        $tied = $this->createStudio(['name' => 'Tied', 'status' => 'active', 'sort_order' => 1]);
        $this->createStudio(['name' => 'Hidden', 'status' => 'inactive']);
        $this->createStudio(['name' => 'Unpublished', 'status' => 'draft']);
        File::partialMock()->shouldReceive('json')->never();
        DB::enableQueryLog();

        $response = $this->get('/locate-a-store')->assertOk();
        $this->assertSame([$first->name, $tied->name, $late->name], $response->viewData('studios')->pluck('name')->all());
        $queries = DB::getQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('from "studios" where "status" = ?', $queries[0]['query']);
        $this->assertStringContainsString('order by "sort_order" asc, "id" asc', $queries[0]['query']);
        $this->assertSame(['active'], $queries[0]['bindings']);
        $this->assertStringNotContainsString('studio_images', $queries[0]['query']);
    }

    public function test_new_active_studios_and_phone_order_are_visible_on_the_next_request(): void
    {
        $this->get('/locate-a-store')->assertOk()->assertSee('0 locations found');
        $studio = $this->createStudio([
            'name' => 'New approved studio', 'status' => 'active',
            'phones' => ['(+91) 98765 43210', '(+91) 99887 76655'],
        ]);
        $page = $this->get('/locate-a-store')->assertOk()->assertSee('New approved studio');
        $this->assertSame($studio->phones, $page->viewData('studios')[0]['phones']);
        $document = new DOMDocument;
        @$document->loadHTML($page->getContent());
        $calls = (new DOMXPath($document))->query('//a[@class="studio-action"]');
        $this->assertSame('tel:+919876543210', $calls->item(0)->getAttribute('href'));
    }

    public function test_directions_accessor_supports_model_and_array_access_without_appending_or_mutation(): void
    {
        $studio = new Studio([
            'name' => 'Test Studio', 'address' => 'Example Road, Delhi',
            'phones' => ['(+91) 95556 95695'], 'map_url' => 'https://maps.app.goo.gl/Example123',
        ]);
        $attributes = $studio->getAttributes();
        $this->assertSame('https://maps.app.goo.gl/Example123', $studio->directions_url);
        $this->assertSame($studio->directions_url, $studio['directions_url']);
        $this->assertSame(['(+91) 95556 95695'], $studio['phones']);
        $this->assertSame([], $studio->getAppends());
        $this->assertArrayNotHasKey('directions_url', $studio->toArray());
        $this->assertSame($attributes, $studio->getAttributes());
    }

    public function test_database_failure_uses_existing_error_handling_without_json_fallback(): void
    {
        config()->set('app.debug', false);
        File::partialMock()->shouldReceive('json')->never();
        DB::purge('sqlite');
        $this->get('/locate-a-store')->assertStatus(500)
            ->assertDontSee('SQLSTATE')->assertDontSee('class="studio-card"', false);
    }

    public function test_published_metadata_and_sitemap_remain_independent_of_physical_studio_records(): void
    {
        $this->mock(MetadataRepository::class)->shouldReceive('snapshot')->andReturn(new SeoSnapshot([
            new SeoOverride('studios.index', [
                'title' => 'Studio Locator | Detailing Devils',
                'description' => 'Published locator description.',
            ], true),
        ]));
        $this->app->forgetInstance(SeoResolver::class);
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->createStudio(['name' => 'New Noida Studio', 'status' => 'active']);
        $html = $this->get('/locate-a-store?state=Delhi&utm_source=fixture')->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('/html/head/title')->length);
        $this->assertSame('Studio Locator | Detailing Devils', $xpath->evaluate('string(/html/head/title)'));
        $this->assertSame('Published locator description.', $xpath->evaluate('string(/html/head/meta[@name="description"]/@content)'));
        $this->assertSame('https://www.detailingdevils.com/locate-a-store', $xpath->evaluate('string(/html/head/link[@rel="canonical"]/@href)'));
        $this->assertSame('index, follow', $xpath->evaluate('string(/html/head/meta[@name="robots"]/@content)'));
        foreach (['og:title', 'og:description', 'og:url'] as $field) {
            $this->assertSame(1, $xpath->query('/html/head/meta[@property="'.$field.'"]')->length);
        }
        foreach (['twitter:card', 'twitter:title', 'twitter:description'] as $field) {
            $this->assertSame(1, $xpath->query('/html/head/meta[@name="'.$field.'"]')->length);
        }
        $after = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertSame($sitemap, $after);
        $this->assertSame(
            app(SeoSitemap::class)->urls(file_get_contents(base_path('tests/Fixtures/seo/sitemap.xml'))),
            app(SeoSitemap::class)->urls($after)
        );
    }
}
