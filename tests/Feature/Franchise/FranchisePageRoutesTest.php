<?php

namespace Tests\Feature\Franchise;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FranchisePageRoutesTest extends TestCase
{
    public function test_named_page_routes_render_the_existing_forms_at_the_new_urls_without_a_database(): void
    {
        config()->set('database.default', 'unconfigured');

        $this->assertSame(url('/detailing-devils-prime-form'), route('prime.show'));
        $this->assertSame(url('/franchise-owned-franchise-operated-model'), route('fofo.show'));

        $this->get('/detailing-devils-prime-form')->assertOk()
            ->assertViewIs('forms.prime')
            ->assertSee('data-submit-url="'.url('/franchise/prime').'"', false)
            ->assertSee('data-send-otp-url="'.url('/otp/send').'"', false)
            ->assertSee('data-verify-otp-url="'.url('/otp/verify').'"', false);

        $this->get('/franchise-owned-franchise-operated-model')->assertOk()
            ->assertViewIs('forms.fofo')
            ->assertSee('data-franchise-model="fofo"', false)
            ->assertSee('assets/js/common/franchise-form.js')
            ->assertSee('assets/js/modules/fofo.js')
            ->assertSeeInOrder(['assets/js/common/franchise-form.js', 'assets/js/modules/fofo.js'])
            ->assertDontSee('assets/js/modules/xclusive.js')
            ->assertSee('data-submit-url="'.url('/franchise/fofo').'"', false)
            ->assertSee('data-email-validation-url="'.url('/email/validate').'"', false);
    }

    public function test_old_get_page_urls_are_no_longer_registered(): void
    {
        config()->set('database.default', 'unconfigured');

        foreach (['/prime', '/franchise/prime-form', '/fofo', '/franchise/fofo-form'] as $oldUrl) {
            $this->get($oldUrl)->assertNotFound();
        }
    }

    public function test_model_scripts_are_ordered_isolated_and_content_versioned(): void
    {
        config()->set('database.default', 'unconfigured');
        foreach (['fofo', 'xclusive'] as $model) {
            $shared = 'assets/js/common/franchise-form.js';
            $entry = "assets/js/modules/{$model}.js";
            $this->get(route("{$model}.show"))->assertOk()
                ->assertViewIs("forms.{$model}")
                ->assertSeeInOrder([
                    asset($shared).'?v='.substr(hash_file('sha256', public_path($shared)), 0, 12),
                    asset($entry).'?v='.substr(hash_file('sha256', public_path($entry)), 0, 12),
                ], false);
        }
    }

    public function test_submission_and_otp_routes_keep_their_original_post_endpoints(): void
    {
        foreach ([
            'prime.submit' => 'franchise/prime',
            'fofo.submit' => 'franchise/fofo',
            'otp.send' => 'otp/send',
            'otp.verify' => 'otp/verify',
            'email.validate' => 'email/validate',
        ] as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($uri, $route->uri());
            $this->assertSame(['POST'], $route->methods());
            $this->assertSame(url('/'.$uri), route($name));
        }
    }
}
