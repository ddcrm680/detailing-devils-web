<?php

namespace Tests\Feature;

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
}
