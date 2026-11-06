<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class HomepageDesignTest extends TestCase
{
    public function test_homepage_uses_exactly_the_new_121_frame_sequence(): void
    {
        config()->set('database.default', 'unconfigured');
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/window\.DD_FRAMES\s*=\s*(\[.*?\]);/', $html, $match);
        $expected = array_map(
            fn (int $index) => sprintf('frames/ezgif-frame-%03d.jpg', $index),
            range(1, 121),
        );

        $this->assertSame(121, config('site.hero.frames'));
        $this->assertSame($expected, json_decode($match[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertCount(121, glob(public_path('frames/ezgif-frame-*.jpg')));
        foreach ($expected as $path) {
            $this->assertFileExists(public_path($path));
            $dimensions = getimagesize(public_path($path));
            $this->assertSame([1920, 1080], array_slice($dimensions, 0, 2));
        }
        $this->assertSame(
            '4f21cb7f578f2d562734eac835a908e4bbbfef623a5586d1619d03d0f4bcc123',
            hash_file('sha256', public_path($expected[0])),
        );
        $this->assertSame(
            '86f174ec96dd5b738e56d81bfb8f57d334a7d672aaf11af57953ae04f2067f71',
            hash_file('sha256', public_path($expected[120])),
        );
        $this->assertFileExists(public_path('frames/.htaccess'));
    }

    public function test_existing_sections_and_working_navigation_remain_in_place(): void
    {
        config()->set('database.default', 'unconfigured');
        $xpath = $this->homepage();
        $sections = array_map(
            fn ($section) => $section->getAttribute('class'),
            iterator_to_array($xpath->query('//main/section')),
        );

        $this->assertSame([
            'seq', 'intro', 'approach', 'stmt wrap', 'nums', 'svc', 'ends',
            'cards wrap', 'lineup', 'fr', 'stsec wrap', 'final',
        ], $sections);
        $this->assertSame(1, $xpath->query('//header[contains(@class, "home-header")]')->length);
        $this->assertSame(1, $xpath->query('//section[@id="studio-tour"]')->length);
        $this->assertSame(1, $xpath->query('//section[@id="franchise"]//a[@href="#contact"]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="form" and @action="'.route('enquiry.store').'"]//input[@name="_token"]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="menu"]//a[@href="'.route('studios.index').'"]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="menu"]//a[contains(@href, "/check-warranty")]')->length);
        $this->assertSame(1, $xpath->query('//footer//a[@href="'.route('legal.privacy').'"]')->length);
    }

    public function test_studio_tour_cards_use_model_variants_and_the_canonical_main_url_without_pricing(): void
    {
        config()->set('database.default', 'unconfigured');
        $xpath = $this->homepage();
        $section = $xpath->query('//section[@id="studio-tour"]')->item(0);
        $cards = $xpath->query('.//article', $section);
        $expected = [
            ['prime', 'Prime', 'Entry', 'The quickest way into the Devils network.'],
            ['fofo', 'FOFO', 'Owner-run', 'Franchise owned, franchise operated. You run the studio.'],
            ['xclusive', 'X-Clusive', 'Flagship', 'The exclusive flagship format for big-city locations.'],
        ];

        $this->assertSame(3, $cards->length);
        foreach ($expected as $index => [$model, $name, $badge, $description]) {
            $card = $cards->item($index);
            $link = $xpath->query('.//a', $card)->item(0);
            $this->assertSame($name, $xpath->evaluate('normalize-space(.//h3)', $card));
            $this->assertSame($badge, $xpath->evaluate('normalize-space(.//span[@class="tag"])', $card));
            $this->assertSame($description, $xpath->evaluate('normalize-space(.//p[@class="line"])', $card));
            $parameters = $model === 'xclusive' ? [] : ['model' => $model];
            $this->assertSame(route('studios.tour', $parameters), $link->getAttribute('href'));
            $this->assertSame($name.' Studio Tour →', preg_replace('/\s+/', ' ', trim($link->textContent)));
            $this->assertSame('noopener', $link->getAttribute('rel'));
        }
        foreach (['₹', 'GST', 'Lakh', 'Crore', 'all-in'] as $pricing) {
            $this->assertStringNotContainsString($pricing, $section->textContent);
        }
        $this->assertSame(0, $xpath->query('.//*[@class="price" or @class="gst" or @class="amt"]', $section)->length);
        $this->assertSame('franchise', $xpath->evaluate('string(preceding-sibling::section[1]/@id)', $section));
        $this->assertSame('contact', $xpath->evaluate('string(following-sibling::section[1]/@id)', $section));

        $response = $this->get(route('studios.tour'))
            ->assertOk()
            ->assertSee('data-model="xclusive"', false);
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $tour = new DOMXPath($document);
        $this->assertSame(
            'https://www.detailingdevils.com/studio-tour',
            $tour->evaluate('string(/html/head/link[@rel="canonical"]/@href)'),
        );
    }

    private function homepage(): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$this->get('/')->assertOk()->getContent());

        return new DOMXPath($document);
    }
}
