<?php

namespace Tests\Feature;

use App\Contracts\Seo\ContentMetadataProvider;
use App\Contracts\Seo\MetadataRepository;
use App\Models\User;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoMedia;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\Admin\SitemapArtifact;
use App\Services\Seo\ContentMetadata;
use App\Services\Seo\DatabaseMetadataRepository;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoSitemap;
use App\Services\Seo\SeoSnapshot;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SeoAdminTest extends TestCase
{
    private string $directory;

    private ?string $cookie = null;

    private string $token = '';

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        $this->directory = sys_get_temp_dir().'/dd-seo-admin-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        copy(resource_path('seo/sitemap.xml'), $this->directory.'/sitemap.xml');
        mkdir($this->directory.'/sessions', 0700);
        config()->set('session.driver', 'file');
        config()->set('session.files', $this->directory.'/sessions');
        // Exercise Laravel's own CSRF middleware with its unit-test bypass disabled only here.
        $this->app->bind(
            PreventRequestForgery::class,
            fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests()
                {
                    return false;
                }

                protected function hasValidOrigin($request)
                {
                    return false;
                }
            }
        );
        config()->set('app.url', 'http://localhost');
        app('url')->forceRootUrl('http://localhost');
        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'foreign_key_constraints' => true,
            ]
        );
        config()->set('seo-admin.sitemap_path', $this->directory.'/sitemap.xml');
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_11_02_000000_add_role_to_users_table.php',
            '2026_11_02_000001_create_website_seo_store.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $xml = file_get_contents($this->directory.'/sitemap.xml');
        DB::table('seo_metadata')->insert([
            'id' => 1,
            'origin' => config('seo.origin'),
            'version' => 0,
            'payload' => app(SeoPublicationCodec::class)->encode(new SeoSnapshot),
            'payload_hash' => hash('sha256', app(SeoPublicationCodec::class)->encode(new SeoSnapshot)),
            'sitemap_xml' => $xml,
            'sitemap_hash' => hash('sha256', $xml),
            'updated_at' => now(),
        ]);
        $this->administrator = new User([
            'name' => 'Synthetic SEO Admin',
            'email' => 'seo@example.test',
            'password' => 'Synthetic QA password only!',
        ]);
        $this->administrator->forceFill(['role' => 'seo_admin'])->save();
        Storage::fake('fofo_images');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        // Only the uniquely allocated test directory is removed; never repository uploads.
        if (
            isset($this->directory)
            && str_starts_with($this->directory, sys_get_temp_dir().'/dd-seo-admin-')
        ) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    public function test_dashboard_is_inaccessible_before_provisioning_and_public_pages_keep_their_fallback(): void
    {
        DB::table('seo_metadata')->delete();
        $this->get('/admin/seo')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>'.e(config('site.seo_title')).'</title>', false);
    }

    public function test_untrusted_hosts_and_invalid_publications_cannot_expose_admin(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.debug', false);
        $this->get('https://untrusted.wasmer.app/admin/login')->assertNotFound();
        DB::table('seo_metadata')->update(['origin' => 'https://other.invalid']);
        $this->get('https://www.detailingdevils.com/admin/login')->assertNotFound();
        $this->get('https://detailing-devils-web.wasmer.app/admin/login')->assertNotFound();
    }

    public function test_guest_has_no_edit_publish_image_or_registration_access(): void
    {
        $this->visit('/admin/login')->assertOk();
        foreach ([
            '/admin',
            '/admin/seo',
            '/admin/seo/pages/home',
            '/admin/seo/images/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
        ] as $path) {
            $this->visit($path)->assertRedirect(route('admin.login'));
        }
        foreach ([
            '/admin/seo/pages/home/draft',
            '/admin/seo/pages/home/preview',
            '/admin/seo/revisions/1/publish',
            '/admin/seo/revisions/1/rollback',
            '/admin/seo/images',
            '/admin/seo/images/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa/remove',
        ] as $path) {
            $this->send($path)->assertRedirect(route('admin.login'));
        }
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_admin_namespace_has_unique_destinations_and_no_legacy_editor_routes(): void
    {
        foreach ([
            'admin.index' => 'admin',
            'admin.login' => 'admin/login',
            'admin.logout' => 'admin/logout',
            'admin.seo.index' => 'admin/seo',
            'admin.seo.edit' => 'admin/seo/pages/{page}',
            'admin.seo.draft' => 'admin/seo/pages/{page}/draft',
            'admin.seo.preview' => 'admin/seo/pages/{page}/preview',
            'admin.seo.images.upload' => 'admin/seo/images',
            'admin.seo.publish' => 'admin/seo/revisions/{revision}/publish',
            'admin.seo.rollback' => 'admin/seo/revisions/{revision}/rollback',
        ] as $name => $uri) {
            $this->assertSame($uri, Route::getRoutes()->getByName($name)->uri());
        }
        $names = array_filter(array_map(fn ($route) => $route->getName(), Route::getRoutes()->getRoutes()));
        $this->assertCount(count(array_unique($names)), $names);
        foreach (['/seo-admin', '/seo-admin/login', '/seo-admin/pages/home'] as $legacy) {
            $this->get($legacy)->assertNotFound();
        }
        $this->login();
        $this->visit('/admin')->assertRedirect(route('admin.seo.index'));
        $this->visit('/admin/login')->assertRedirect(route('admin.seo.index'));
        $this->visit('/admin/seo/pages/home')
            ->assertOk()
            ->assertSee(
                route('admin.seo.draft', 'home'),
                false
            )
            ->assertDontSee(
                '/seo-admin/pages/',
                false
            );
    }

    public function test_public_sitemap_uses_published_policy_after_admin_logout_without_authentication(): void
    {
        $this->administrator->forceFill(['role' => 'super_admin'])->save();
        $this->login();
        $fields = $this->fields('fofo.show');
        $fields['robots'] = 'noindex, follow';
        $fields['sitemap'] = false;
        $fields['policy_reason'] = 'Synthetic public sitemap authorization independence';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft(), policy: true)->assertRedirect();
        $this->send('/admin/logout')->assertRedirect(route('admin.login'));
        Route::view('/qa-new-public-guide', 'layouts.app')->name('qa.public-guide');
        Route::getRoutes()->refreshNameLookups();
        app()->forgetInstance(SeoResolver::class);
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(
                '/qa-new-public-guide</loc>',
                false
            )
            ->assertDontSee(
                '/franchise-owned-franchise-operated-model</loc>',
                false
            )
            ->assertDontSee(
                '/admin',
                false
            );
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertSame([], app('router')->gatherRouteMiddleware(Route::getRoutes()->getByName('sitemap')));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }

    public function test_secure_password_login_and_logout_reuse_the_standard_web_session(): void
    {
        $this->assertTrue(Hash::check('Synthetic QA password only!', $this->administrator->password));
        $this->assertNotSame('Synthetic QA password only!', $this->administrator->password);
        $first = $this->visit('/admin/login');
        $cookie = $first->getCookie(config('session.cookie'), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(config('session.same_site'), $cookie->getSameSite());
        $this->assertSame(config('session.path'), $cookie->getPath());
        $oldCookie = $this->cookie;
        $oldSessionId = $this->app['request']->session()->getId();
        $this->send('/admin/login', ['email' => 'seo@example.test', 'password' => 'Synthetic QA password only!'])
            ->assertRedirect(route('admin.seo.index'));
        $this->assertNotSame($oldCookie, $this->cookie);
        $this->assertNotSame($oldSessionId, $this->app['request']->session()->getId());
        $this->visit('/admin/seo')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader(
                'X-Robots-Tag',
                'noindex, nofollow'
            );
        $this->send('/admin/logout')->assertRedirect(route('admin.login'));
        $this->visit('/admin/seo')->assertRedirect(route('admin.login'));
        $this->assertSame('file', config('session.driver'));
        $this->assertSame($this->directory.'/sessions', config('session.files'));
        $this->assertSame('sqlite', config('database.default'));
    }

    public function test_csrf_is_enforced_for_login_and_authenticated_mutations_even_in_tests(): void
    {
        $this->visit('/admin/login')->assertOk();
        $this->post('/admin/login', ['email' => 'seo@example.test', 'password' => 'Synthetic QA password only!'])
            ->assertStatus(419);
        $this->login();
        $this->post('/admin/seo/pages/home/draft', $this->fields('home'))->assertStatus(419);
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_login_is_rate_limited_and_does_not_enumerate_users(): void
    {
        $this->visit('/admin/login');
        for ($i = 0; $i < 5; $i++) {
            $this->send(
                '/admin/login',
                ['email' => 'unknown@example.test', 'password' => 'Wrong synthetic password']
            )
                ->assertRedirect();
        }
        $this->send(
            '/admin/login',
            ['email' => 'unknown@example.test', 'password' => 'Wrong synthetic password']
        )
            ->assertStatus(429)
            ->assertHeader(
                'Retry-After',
                '60'
            );
    }

    public function test_revoked_seo_authorization_blocks_editing_and_publishing(): void
    {
        $this->login();
        User::query()
            ->whereKey($this->administrator->id)
            ->update(['role' => null]);
        $this->visit('/admin')->assertForbidden();
        $this->visit('/admin/seo')->assertForbidden();
        $this->send('/admin/seo/pages/home/draft', $this->fields('home'))->assertForbidden();
        foreach ([
            '/admin/seo/pages/home/preview',
            '/admin/seo/revisions/1/publish',
            '/admin/seo/revisions/1/rollback',
            '/admin/seo/images',
            '/admin/seo/images/aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa/remove',
        ] as $path) {
            $this->send($path)->assertForbidden();
        }
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_explicit_role_permissions_are_small_and_denied_by_default(): void
    {
        foreach ([null, 'unknown', 'super_admin', 'admin', 'seo_admin'] as $index => $role) {
            $user = new User([
                'name' => 'Synthetic Role QA',
                'email' => 'role'.$index.'@example.test',
                'password' => 'Synthetic role password!',
            ]);
            $user->forceFill(['role' => $role])->save();
            $this->assertSame(in_array($role, User::SEO_ROLES, true), Gate::forUser($user)->allows('manage-seo'));
            $this->assertSame($role === 'super_admin', Gate::forUser($user)->allows('publish-seo-policy'));
        }
    }

    public function test_admin_can_publish_routine_metadata_but_cannot_publish_policy(): void
    {
        $this->administrator->forceFill(['role' => 'admin'])->save();
        $this->login();
        $fields = $this->fields('home');
        $fields['title'] = 'Synthetic routine Admin publication';
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertSame(1, $this->version());
        $this->assertPolicyDenied();
    }

    public function test_dedicated_seo_admin_cannot_approve_policy_even_with_a_forged_confirmation(): void
    {
        $this->login();
        $this->assertPolicyDenied();
    }

    private function assertPolicyDenied(): void
    {
        $fields = $this->fields('fofo.show');
        $fields['robots'] = 'noindex, follow';
        $fields['sitemap'] = false;
        $fields['policy_reason'] = 'Synthetic unprivileged policy proposal';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $version = $this->version();
        $this->publish($this->latestDraft(), policy: true)->assertForbidden();
        $this->assertSame($version, $this->version());
    }

    public function test_inventory_search_and_private_preview_reuse_existing_engine_without_writes(): void
    {
        $this->login();
        $result = $this->visit('/admin/seo?q=xclusive')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($result->getContent());
        $inventory = (new \DOMXPath($document))->evaluate('string(//table/tbody)');
        $this->assertStringContainsString('xclusive.show', $inventory);
        $this->assertStringNotContainsString('fofo.show', $inventory);
        $fields = $this->fields('fofo.show');
        $fields['title'] = 'Synthetic SEO preview & identity';
        $this->send('/admin/seo/pages/fofo.show/preview', $fields)
            ->assertOk()
            ->assertSee(
                '<title>Synthetic SEO preview &amp; identity</title>',
                false
            )
            ->assertSee(
                'content="noindex, nofollow"',
                false
            );
        $this->assertSame(0, DB::table('seo_revisions')->count());
        $this->assertSame(0, $this->version());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_drafts_never_change_public_metadata_but_publishing_does_without_deployment(): void
    {
        $this->login();
        $fields = $this->fields('home');
        $fields['title'] = 'Synthetic published SEO title';
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        $draft = $this->latestDraft();
        app()->forgetInstance(SeoResolver::class);
        $this->get('/')
            ->assertOk()
            ->assertDontSee('<title>Synthetic published SEO title</title>', false);
        $this->visit('/admin/seo/pages/home')
            ->assertOk()
            ->assertSee('Draft');
        $this->publish($draft)->assertRedirect();
        $this->assertSame(1, $this->version());
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Synthetic published SEO title</title>', false);
        $this->assertSame('Detailing Devils', config('site.brand'));
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'published')
            ->count());
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'baseline')
            ->count());
        $this->artisan('seo:sitemap', ['--check' => true, '--output' => $this->directory.'/sitemap.xml'])
            ->assertSuccessful();
    }

    public function test_stale_drafts_and_double_publish_never_overwrite_newer_state(): void
    {
        $this->login();
        $this->send('/admin/seo/pages/home/draft', $this->fields('home'))->assertRedirect();
        $first = $this->latestDraft();
        $this->send('/admin/seo/pages/fofo.show/draft', $this->fields('fofo.show'))->assertRedirect();
        $second = $this->latestDraft();
        $this->publish($first)->assertRedirect();
        $this->publish($first, expected: 0)->assertStatus(409);
        $this->publish($second, expected: 1)->assertStatus(409);
        $this->assertSame(1, $this->version());
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'published')
            ->count());
    }

    public function test_rollback_creates_an_audited_revision_and_restores_exact_original_copy(): void
    {
        $this->login();
        $original = $this->fields('fofo.show');
        $changed = $original;
        $changed['title'] = 'Synthetic changed title';
        $this->send('/admin/seo/pages/fofo.show/draft', $changed)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $baseline = DB::table('seo_revisions')
            ->where('kind', 'baseline')
            ->value('id');
        $this->send(
            '/admin/seo/revisions/'.$baseline.'/rollback',
            ['expected_version' => 1, 'acknowledge_warnings' => 1]
        )
            ->assertRedirect();
        $this->assertSame(2, $this->version());
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                '<title>'.e($original['title']).'</title>',
                false
            );
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'rollback')
            ->count());
    }

    public function test_html_and_arbitrary_urls_are_rejected_without_weakening_escaping(): void
    {
        $this->login();
        foreach ([
            'title' => '<script>bad()</script>',
            'canonical' => 'https://evil.invalid/',
            'image' => 'javascript:bad()',
        ] as $key => $bad) {
            $fields = $this->fields('home');
            $fields[$key] = $bad;
            $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
            $this->assertAdminError($key);
        }
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_super_admin_policy_requires_explicit_confirmation_and_a_meaningful_reason(): void
    {
        $this->administrator->forceFill(['role' => 'super_admin'])->save();
        $this->login();
        $fields = $this->fields('fofo.show');
        $fields['robots'] = 'noindex, follow';
        $fields['sitemap'] = false;
        $fields['policy_reason'] = '';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $draft = $this->latestDraft();
        $this->publish($draft)->assertRedirect();
        $this->assertAdminError('publication');
        $this->publish($draft, policy: true)->assertRedirect();
        $this->assertAdminError('publication');
        $this->assertSame(0, $this->version());
        $this->send(
            '/admin/seo/revisions/'.$draft.'/publish',
            [
                'expected_version' => 0,
                'acknowledge_warnings' => 1,
                'policy_reason' => 'Synthetic authorized indexing decision',
            ]
        )
            ->assertRedirect();
        $this->assertAdminError('publication');
        $this->send(
            '/admin/seo/revisions/'.$draft.'/publish',
            [
                'expected_version' => 0,
                'acknowledge_warnings' => 1,
                'approve_policy' => 1,
                'policy_reason' => 'Synthetic authorized indexing decision',
            ]
        )
            ->assertRedirect();
        $this->assertSame(1, $this->version());
        $xml = $this->sitemapXml();
        $this->assertStringNotContainsString('/franchise-owned-franchise-operated-model</loc>', $xml);
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'name="robots" content="noindex, follow"',
                false
            );
    }

    public function test_unapproved_sitemap_pages_and_canonical_conflicts_cannot_publish(): void
    {
        $this->login();
        $fields = $this->fields('legal.warranty-terms');
        $fields['sitemap'] = true;
        $this->send('/admin/seo/pages/legal.warranty-terms/draft', $fields)->assertRedirect();
        $this->assertAdminError('sitemap');
        $fields = $this->fields('fofo.show');
        $fields['canonical'] = config('seo.origin').'/';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->assertAdminError('sitemap');
        $this->assertSame(0, $this->version());
    }

    public function test_policy_rollback_requires_super_admin_and_confirmation_and_restores_the_sitemap(): void
    {
        $this->administrator->forceFill(['role' => 'super_admin'])->save();
        $this->login();
        $originalXml = $this->sitemapXml();
        $fields = $this->fields('fofo.show');
        $fields['robots'] = 'noindex, follow';
        $fields['sitemap'] = false;
        $fields['policy_reason'] = 'Synthetic approved indexing decision';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft(), policy: true)->assertRedirect();
        $baseline = DB::table('seo_revisions')
            ->where('kind', 'baseline')
            ->value('id');
        $changedXml = $this->sitemapXml();
        $this->assertNotSame($originalXml, $changedXml);
        $input = [
            'expected_version' => 1,
            'acknowledge_warnings' => 1,
            'approve_policy' => 1,
            'policy_reason' => 'Synthetic approved indexing restoration',
        ];
        foreach (['admin', 'seo_admin'] as $role) {
            $this->administrator->forceFill(['role' => $role])->save();
            $this->send('/admin/seo/revisions/'.$baseline.'/rollback', $input)->assertForbidden();
            $this->assertSame(1, $this->version());
            $this->assertSame($changedXml, $this->sitemapXml());
        }
        $this->administrator->forceFill(['role' => 'super_admin'])->save();
        $this->send(
            '/admin/seo/revisions/'.$baseline.'/rollback',
            array_replace($input, ['approve_policy' => 0])
        )
            ->assertRedirect();
        $this->assertAdminError('publication');
        $this->send(
            '/admin/seo/revisions/'.$baseline.'/rollback',
            array_replace($input, ['policy_reason' => 'short'])
        )
            ->assertRedirect();
        $this->assertAdminError('publication');
        $this->send('/admin/seo/revisions/'.$baseline.'/rollback', $input)->assertRedirect();
        $this->assertSame(2, $this->version());
        $restoredXml = $this->sitemapXml();
        $this->assertSame(app(SeoSitemap::class)->urls($originalXml), app(SeoSitemap::class)->urls($restoredXml));
        $this->assertSame(hash('sha256', $restoredXml), DB::table('seo_metadata')->value('sitemap_hash'));
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'name="robots" content="index, follow"',
                false
            );
    }

    public function test_empty_publication_preserves_legacy_blade_metadata_without_configuration_switches(): void
    {
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                '<title>FOFO Franchise Application | Detailing Devils</title>',
                false
            );
        $this->assertSame(0, $this->version());
        $this->assertSame(0, DB::table('seo_revisions')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_duplicate_copy_is_reported_and_publication_requires_warning_acknowledgement(): void
    {
        $this->login();
        $fields = $this->fields('fofo.show');
        $fields['title'] = $this->fields('prime.show')['title'];
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $draft = $this->latestDraft();
        $this->send('/admin/seo/revisions/'.$draft.'/publish', ['expected_version' => 0])->assertRedirect();
        $this->assertAdminError('acknowledge_warnings');
        $this->send('/admin/seo/pages/fofo.show/preview', $fields)
            ->assertOk()
            ->assertSee('Duplicate title.');
        $this->visit('/admin/seo/pages/fofo.show')
            ->assertOk()
            ->assertSee('Duplicate title.');
    }

    public function test_images_are_validated_reencoded_selected_and_transported_to_the_reader(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Synthetic approved artwork',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('qa.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $image = app(SeoMedia::class)->manifests()[0] ?? null;
        $this->assertNotNull($image);
        $this->assertSame(
            'image/jpeg',
            getimagesizefromstring(Storage::disk('fofo_images')->get('seo/'.basename($image->path)))['mime']
        );
        $fields = $this->fields('fofo.show');
        $fields['image'] = config('seo.origin').$image->path;
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->send('/admin/logout')->assertRedirect(route('admin.login'));
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'property="og:image" content="'.$fields['image'].'"',
                false
            );
        $this->visit('/admin/seo')->assertRedirect(route('admin.login'));
        Http::assertNothingSent();
    }

    public function test_unused_image_removal_is_confirmed_versioned_and_audited(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Unused synthetic image',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('unused.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $image = app(SeoMedia::class)->manifests()[0];
        $id = pathinfo($image->path, PATHINFO_FILENAME);
        $this->send('/admin/seo/images/'.$id.'/remove', ['expected_version' => 1])->assertRedirect();
        $this->assertAdminError('confirm_removal');
        $this->send('/admin/seo/images/'.$id.'/remove', ['expected_version' => 0, 'confirm_removal' => 1])
            ->assertStatus(409);
        $this->send('/admin/seo/images/'.$id.'/remove', ['expected_version' => 1, 'confirm_removal' => 1])
            ->assertRedirect();
        $this->assertSame(2, $this->version());
        $this->assertCount(0, app(SeoMedia::class)->manifests());
        Storage::disk('fofo_images')->assertMissing('seo/'.$id.'.jpg');
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'image-removed')
            ->count());
    }

    public function test_image_replacement_page_removal_and_rollback_preserve_historical_files(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Original image',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('original.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $first = app(SeoMedia::class)->manifests()[0];
        $fields = $this->fields('fofo.show');
        $fields['image'] = config('seo.origin').$first->path;
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $firstPublication = DB::table('seo_revisions')
            ->where('kind', 'published')
            ->value('id');
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Replacement image',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('replacement.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $second = app(SeoMedia::class)->manifests()[1];
        $fields = $this->fields('fofo.show');
        $fields['image'] = config('seo.origin').$second->path;
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'property="og:image" content="'.$fields['image'].'"',
                false
            );
        $id = pathinfo($first->path, PATHINFO_FILENAME);
        $this->send(
            '/admin/seo/images/'.$id.'/remove',
            ['expected_version' => $this->version(), 'confirm_removal' => 1]
        )
            ->assertRedirect();
        $this->assertAdminError('image');
        Storage::disk('fofo_images')->assertExists('seo/'.$id.'.jpg');
        $fields = $this->fields('fofo.show');
        $fields['image'] = null;
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertDontSee(
                'property="og:image"',
                false
            );
        $this->send(
            '/admin/seo/revisions/'.$firstPublication.'/rollback',
            ['expected_version' => $this->version(), 'acknowledge_warnings' => 1]
        )
            ->assertRedirect();
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'property="og:image" content="'.config('seo.origin').$first->path.'"',
                false
            );
    }

    public function test_failed_image_removal_restores_bytes_and_library_state(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Removal failure fixture',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('unused.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $image = app(SeoMedia::class)->manifests()[0];
        $id = pathinfo($image->path, PATHINFO_FILENAME);
        $before = Storage::disk('fofo_images')->get('seo/'.$id.'.jpg');
        DB::unprepared("CREATE TRIGGER fail_media_removal BEFORE UPDATE ON seo_metadata BEGIN SELECT RAISE(ABORT, 'Synthetic failure'); END");
        $this->send('/admin/seo/images/'.$id.'/remove', ['expected_version' => 1, 'confirm_removal' => 1])
            ->assertStatus(503)
            ->assertDontSee('Synthetic failure');
        $this->assertSame(1, $this->version());
        $this->assertCount(1, app(SeoMedia::class)->manifests());
        $this->assertSame($before, Storage::disk('fofo_images')->get('seo/'.$id.'.jpg'));
    }

    public function test_upload_storage_failures_are_safe_and_do_not_create_publications(): void
    {
        $this->login();
        $disk = \Mockery::mock(Storage::disk('fofo_images'))->makePartial();
        $disk->shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('Private synthetic storage detail'));
        $disk->shouldReceive('delete')
            ->once()
            ->andReturnTrue();
        Storage::shouldReceive('disk')->with('fofo_images')->andReturn($disk);
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Failed storage fixture',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('unused.png', 1200, 630),
            ]
        )
            ->assertStatus(503)
            ->assertDontSee('Private synthetic storage detail');
        $this->assertCount(0, app(SeoMedia::class)->manifests());
        $this->assertSame(0, $this->version());
        $this->assertSame(0, DB::table('seo_revisions')->count());
    }

    public function test_configured_content_provider_tracks_real_fixture_records_without_persisting_content_in_seo(): void
    {
        Route::view('/qa-provider-record/{slug}', 'layouts.app')->name('qa.provider');
        Route::getRoutes()->refreshNameLookups();
        config()->set('seo.content_providers', [SeoFixtureContentProvider::class]);
        SeoFixtureContentProvider::$records = [
            new ContentMetadata(
                'article:one',
                'article',
                'qa.provider',
                ['slug' => 'published'],
                'Published fixture',
                published: true,
                sitemap: true
            ),
            new ContentMetadata(
                'article:draft',
                'article',
                'qa.provider',
                ['slug' => 'draft'],
                'Unpublished fixture',
                sitemap: true
            ),
            new ContentMetadata(
                'article:noindex',
                'article',
                'qa.provider',
                ['slug' => 'noindex'],
                'Noindex fixture',
                published: true,
                indexable: false,
                sitemap: true
            ),
        ];
        app()->forgetInstance(SeoResolver::class);
        $xml = $this->sitemapXml();
        $this->assertStringContainsString('/qa-provider-record/published</loc>', $xml);
        $this->assertStringNotContainsString('/qa-provider-record/draft</loc>', $xml);
        $this->assertStringNotContainsString('/qa-provider-record/noindex</loc>', $xml);
        $this->login();
        $this->visit('/admin/seo/pages/article:one')
            ->assertOk()
            ->assertSee('Published fixture');
        $invalidAlias = $this->fields('fofo.show');
        $invalidAlias['canonical'] = config('seo.origin').'/qa-provider-record/draft';
        $invalidAlias['sitemap'] = false;
        $this->send('/admin/seo/pages/fofo.show/draft', $invalidAlias)->assertRedirect();
        $this->assertAdminError('canonical');
        $pages = app(SeoAdminPages::class);
        $fields = $pages->fields($pages->page('article:one')) + ['expected_version' => $this->version()];
        $fields['description'] = 'Complete synthetic content description';
        $this->send('/admin/seo/pages/article:one/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertArrayNotHasKey('content', json_decode(DB::table('seo_metadata')->value('payload'), true));
        SeoFixtureContentProvider::$records[0] = new ContentMetadata(
            'article:one',
            'article',
            'qa.provider',
            ['slug' => 'renamed'],
            'Published fixture',
            published: true,
            sitemap: true
        );
        $renamed = $this->sitemapXml();
        $this->assertStringContainsString('/qa-provider-record/renamed</loc>', $renamed);
        $this->assertStringNotContainsString('/qa-provider-record/published</loc>', $renamed);
        SeoFixtureContentProvider::$records = [];
        $this->assertStringNotContainsString('/qa-provider-record/renamed</loc>', $this->sitemapXml());
        $this->visit('/admin/seo/pages/article:one')->assertNotFound();
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'published')
            ->count());
    }

    public function test_invalid_or_changed_images_are_not_published(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Rejected',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'),
            ]
        )
            ->assertRedirect();
        $this->assertAdminError('image_file');
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Small',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('small.png', 20, 20),
            ]
        )
            ->assertRedirect();
        $this->assertAdminError('image_file');
        $this->assertCount(0, app(SeoMedia::class)->manifests());
    }

    public function test_artifact_or_database_failure_rolls_back_publication_and_preserves_draft(): void
    {
        $this->login();
        $fields = $this->fields('home');
        $fields['title'] = 'Uncommitted synthetic publication';
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        $draft = $this->latestDraft();
        $before = hash_file('sha256', $this->directory.'/sitemap.xml');
        DB::unprepared("CREATE TRIGGER fail_publication BEFORE UPDATE ON seo_metadata BEGIN SELECT RAISE(ABORT, 'Synthetic failure'); END");
        $this->publish($draft)
            ->assertStatus(503)
            ->assertDontSee('Synthetic failure');
        $this->assertSame(0, $this->version());
        $this->assertSame($before, hash_file('sha256', $this->directory.'/sitemap.xml'));
        $this->assertSame(0, DB::table('seo_revisions')
            ->where('kind', 'published')
            ->count());
        $this->assertSame('draft', DB::table('seo_revisions')
            ->where('id', $draft)
            ->value('kind'));
    }

    public function test_storage_failure_falls_back_without_breaking_errors_or_persisting_to_crm(): void
    {
        DB::purge('sqlite');
        config()->set('database.connections.sqlite.database', '/missing/qa-not-a-real-database.sqlite');
        $this->get('/')
            ->assertOk()
            ->assertSee(e(config('site.seo_title')), false);
        $this->get('/missing-private-qa-page')
            ->assertNotFound()
            ->assertDontSee('property="og:title"', false);
        $this->get('/admin/login')->assertNotFound();
    }

    public function test_default_connection_and_standard_user_guard_are_reused_without_implicit_authorization(): void
    {
        app(SeoStorage::class)->assertConfigured();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertNull(config('database.connections.seo'));
        $this->assertNull(config('auth.guards.seo'));
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $regular = User::query()->create([
            'name' => 'Synthetic Regular User',
            'email' => 'regular@example.test',
            'password' => 'Synthetic test password!',
            'role' => 'super_admin',
        ]);
        $this->assertNull($regular->fresh()->role);
        $this->actingAs($regular, 'web')
            ->get('/admin/seo')
            ->assertForbidden();
    }

    public function test_interrupted_artifact_can_be_checked_and_explicitly_reconciled(): void
    {
        file_put_contents($this->directory.'/sitemap.xml', '<invalid/>');
        $this->artisan('seo:sitemap', ['--check' => true, '--output' => $this->directory.'/sitemap.xml'])
            ->assertFailed();
        $this->artisan(
            'seo:sitemap',
            ['--restore-published' => true, '--output' => $this->directory.'/sitemap.xml']
        )
            ->assertSuccessful();
        $this->assertSame(
            DB::table('seo_metadata')->value('sitemap_hash'),
            hash_file('sha256', $this->directory.'/sitemap.xml')
        );
    }

    public function test_discovered_page_is_listed_editable_and_publishable_after_deployment_sitemap_generation(): void
    {
        Route::view('/qa-new-public-page', 'layouts.app')->middleware('web')->name('qa.new-public');
        Route::getRoutes()->refreshNameLookups();
        app()->forgetInstance(SeoResolver::class);
        $this->login();
        $this->visit('/admin/seo?q=qa.new-public')
            ->assertOk()
            ->assertSee('1 matching pages')
            ->assertSee('qa.new-public');
        $this->visit('/admin/seo/pages/qa.new-public')
            ->assertOk()
            ->assertSee('Qa New Public Page | Detailing Devils');
        $this->artisan('seo:sitemap', ['--output' => $this->directory.'/sitemap.xml'])->assertSuccessful();
        $generated = file_get_contents($this->directory.'/sitemap.xml');
        $this->assertStringContainsString('/qa-new-public-page</loc>', $generated);
        $this->assertNotSame(DB::table('seo_metadata')->value('sitemap_hash'), hash('sha256', $generated));
        $this->artisan(
            'seo:sitemap',
            ['--restore-published' => true, '--output' => $this->directory.'/sitemap.xml']
        )
            ->assertFailed();
        $this->assertSame($generated, file_get_contents($this->directory.'/sitemap.xml'));
        $fields = $this->fields('qa.new-public');
        $fields['title'] = 'Synthetic published discovered page';
        $this->send('/admin/seo/pages/qa.new-public/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertSame(1, $this->version());
        $this->get('/qa-new-public-page')
            ->assertOk()
            ->assertSee(
                '<title>Synthetic published discovered page</title>',
                false
            );
        $this->assertSame(
            DB::table('seo_metadata')->value('sitemap_hash'),
            hash_file('sha256', $this->directory.'/sitemap.xml')
        );
    }

    public function test_validation_errors_and_old_values_survive_the_standard_session_redirect(): void
    {
        $this->login();
        $this->visit('/admin/seo/pages/home');
        $fields = $this->fields('home');
        $fields['title'] = '<script>Synthetic rejected text</script>';
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        $this->visit('/admin/seo/pages/home')
            ->assertOk()
            ->assertSee('Use plain text without HTML or control characters.')
            ->assertSee(
                e($fields['title']),
                false
            )
            ->assertDontSee(
                $fields['title'],
                false
            );
    }

    public function test_shared_reader_refreshes_after_publication_without_a_persistent_metadata_cache(): void
    {
        $this->login();
        $this->get('/')->assertOk();
        $fields = $this->fields('home');
        $fields['title'] = 'Synthetic version-aware title';
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [$query->connectionName, $query->sql];
        });
        app()->forgetInstance(SeoResolver::class);
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Synthetic version-aware title</title>', false);
        $queries = [];
        app()->forgetInstance(SeoResolver::class);
        $this->get('/')->assertOk();
        $this->assertCount(1, $queries);
        $this->assertSame('sqlite', $queries[0][0]);
        $this->assertStringNotContainsString('select "payload"', $queries[0][1]);
    }

    public function test_canonical_alias_requires_explicit_policy_approval_and_never_enters_sitemap(): void
    {
        $this->administrator->forceFill(['role' => 'super_admin'])->save();
        $this->login();
        $fields = $this->fields('fofo.show');
        $fields['canonical'] = $this->fields('prime.show')['canonical'];
        $fields['sitemap'] = false;
        $fields['policy_reason'] = 'Synthetic explicit canonical policy decision';
        $this->send('/admin/seo/pages/fofo.show/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft(), policy: true)->assertRedirect();
        $this->get('/franchise-owned-franchise-operated-model')
            ->assertOk()
            ->assertSee(
                'rel="canonical" href="'.$fields['canonical'].'"',
                false
            );
        $this->assertStringNotContainsString(
            '/franchise-owned-franchise-operated-model</loc>',
            $this->sitemapXml()
        );
    }

    public function test_changed_uploaded_bytes_block_publication(): void
    {
        $this->login();
        $this->send(
            '/admin/seo/images',
            [
                'label' => 'Synthetic immutable artwork',
                'approve_image' => 1,
                'image_file' => UploadedFile::fake()->image('qa.png', 1200, 630),
            ]
        )
            ->assertRedirect();
        $image = app(SeoMedia::class)->manifests()[0] ?? null;
        $fields = $this->fields('home');
        $fields['image'] = config('seo.origin').$image->path;
        $this->send('/admin/seo/pages/home/draft', $fields)->assertRedirect();
        Storage::disk('fofo_images')->put('seo/'.basename($image->path), 'Synthetic tampering');
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertAdminError('image');
        $this->assertSame(1, $this->version());
    }

    public function test_publication_and_dynamic_sitemap_do_not_require_a_writable_artifact(): void
    {
        $this->login();
        $this->send('/admin/seo/pages/home/draft', $this->fields('home'))->assertRedirect();
        $mock = \Mockery::mock(SitemapArtifact::class);
        $mock->shouldNotReceive('replace');
        $this->app->instance(SitemapArtifact::class, $mock);
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertSame(1, $this->version());
        $this->assertSame(1, DB::table('seo_revisions')
            ->where('kind', 'published')
            ->count());
        $this->assertStringContainsString('<urlset', $this->sitemapXml());
    }

    public function test_inventory_can_search_five_hundred_registered_pages_without_enquiry_queries(): void
    {
        Route::view('/qa-seo-pages/{id}', 'layouts.app')->name('qa.seo.pages');
        Route::getRoutes()->refreshNameLookups();
        $definitions = config('seo.pages');
        for ($i = 1; $i <= 500; $i++) {
            $definitions['qa.page.'.$i] = [
                'route' => 'qa.seo.pages',
                'parameters' => ['id' => $i],
                'title' => 'QA page '.$i,
                'description' => 'Synthetic description '.$i,
                'sitemap' => false,
            ];
        }
        config()->set('seo.pages', $definitions);
        $this->login();
        $this->visit('/admin/seo?q=qa.page.500')
            ->assertOk()
            ->assertSee('qa.page.500')
            ->assertSee('1 matching pages');
        $result = $this->visit('/admin/seo?q=qa.page.&per_page=100&page=5')->assertOk();
        $this->assertCount(100, $result->viewData('pages'));
        $this->assertSame(500, $result->viewData('count'));
        $this->assertSame(5, $result->viewData('lastPage'));
        $result->assertSee('per_page=100', false)->assertSee('q=qa.page.', false);
        $this->assertSame('sqlite', config('database.default'));
    }

    public function test_admin_shell_reuses_identity_and_has_only_implemented_module_navigation(): void
    {
        $this->app->bind(Vite::class, fn () => throw new \LogicException('Admin must not depend on Vite.'));
        $this->visit('/admin/login')
            ->assertViewIs('admin.login')
            ->assertDontSee('id="admin-sidebar"', false);
        $this->login();
        $this->visit('/admin/seo')
            ->assertViewIs('admin.seo.index')
            ->assertSee('SEO overview')
            ->assertSee('Synthetic SEO Admin')
            ->assertSee('SEO Admin')
            ->assertSee(
                'id="admin-sidebar"',
                false
            )
            ->assertSee(
                'aria-current="page"',
                false
            )
            ->assertDontSee('/admin/studios')
            ->assertDontSee('/admin/blogs');
        $this->visit('/admin/seo/pages/home')
            ->assertViewIs('admin.seo.edit')
            ->assertSee(
                'data-draft-form',
                false
            )
            ->assertSee(
                'data-save-state',
                false
            )
            ->assertSee('Search preview')
            ->assertSee(
                '/assets/css/admin.css?v=',
                false
            )
            ->assertSee(
                '/assets/js/admin/workspace.js?v=',
                false
            )
            ->assertDontSee('/build/')
            ->assertDontSee('@vite');
    }

    public function test_inventory_workflow_filters_and_summary_reflect_real_drafts_and_publication(): void
    {
        Route::view('/qa-filter-noindex', 'layouts.app')
            ->name('qa.filter.noindex')
            ->defaults(
                '_seo',
                ['robots' => 'noindex, follow']
            );
        Route::getRoutes()->refreshNameLookups();
        $this->login();
        $this->send('/admin/seo/pages/fofo.show/draft', $this->fields('fofo.show'))->assertRedirect();
        $draft = $this->latestDraft();
        $response = $this->visit('/admin/seo?status=draft')->assertOk();
        $this->assertSame(['fofo.show'], array_map(fn ($page) => $page->id, $response->viewData('pages')));
        $this->assertSame(1, $response->viewData('summary')['draft']);
        $this->assertSame(0, $response->viewData('summary')['published']);
        $this->publish($draft)->assertRedirect();
        $response = $this->visit('/admin/seo?status=published')->assertOk();
        $this->assertSame(['fofo.show'], array_map(fn ($page) => $page->id, $response->viewData('pages')));
        $this->assertSame(0, $response->viewData('summary')['draft']);
        $this->assertSame(1, $response->viewData('summary')['published']);
        foreach (['indexing=noindex', 'sitemap=excluded'] as $filter) {
            $response = $this->visit('/admin/seo?'.$filter)->assertOk();
            $this->assertNotEmpty($response->viewData('pages'));
            foreach ($response->viewData('pages') as $page) {
                $this->assertFalse(
                    $filter === 'indexing=noindex'
                        ? $page->indexable()
                        : $page->sitemap && $page->indexable()
                );
            }
        }
        $response = $this->visit('/admin/seo?status=unknown');
        $response->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_offline_provisioning_creates_one_hashed_authorized_user(): void
    {
        DB::table('users')->delete();
        DB::table('seo_metadata')->delete();
        $this->artisan('seo:provision', ['email' => 'owner@example.test'])
            ->expectsQuestion(
                'New administrator password (at least 8 characters)',
                'qa-only8'
            )
            ->expectsQuestion(
                'Confirm password',
                'qa-only8'
            )
            ->assertSuccessful();
        $this->assertSame(1, User::query()->count());
        $this->assertTrue(Hash::check('qa-only8', User::query()->first()->password));
        $this->assertSame('seo_admin', User::query()->first()->role);
        $this->assertTrue(User::query()
            ->first()
            ->canManageSeo());
        $this->artisan('seo:provision', ['email' => 'second@example.test'])->assertFailed();
        $this->assertSame(1, User::query()->count());
    }

    public function test_local_provisioning_rejects_short_and_mismatched_passwords_without_creating_accounts(): void
    {
        DB::table('users')->delete();
        DB::table('seo_metadata')->delete();
        $this->artisan('seo:provision', ['email' => 'owner@example.test'])
            ->expectsQuestion(
                'New administrator password (at least 8 characters)',
                'short'
            )
            ->assertFailed();
        $this->artisan('seo:provision', ['email' => 'owner@example.test'])
            ->expectsQuestion(
                'New administrator password (at least 8 characters)',
                'qa-only8'
            )
            ->expectsQuestion(
                'Confirm password',
                'different'
            )
            ->assertFailed();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, DB::table('seo_metadata')->count());
    }

    public function test_production_provisioning_keeps_the_fourteen_character_minimum(): void
    {
        DB::table('users')->delete();
        DB::table('seo_metadata')->delete();
        $this->app['env'] = 'production';
        $this->artisan('seo:provision', ['email' => 'owner@example.test'])
            ->expectsQuestion(
                'New administrator password (at least 14 characters)',
                'qa-only8'
            )
            ->assertFailed();
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, DB::table('seo_metadata')->count());
    }

    public function test_store_integrity_failure_falls_back_and_never_exposes_an_admin_interface(): void
    {
        DB::table('seo_metadata')->update(['payload' => '{corrupt']);
        $this->get('/')
            ->assertOk()
            ->assertSee(e(config('site.seo_title')), false)
            ->assertDontSee('{corrupt');
        $this->get('/admin/login')->assertNotFound();
    }

    public function test_obsolete_export_files_cannot_block_or_replace_the_dynamic_sitemap(): void
    {
        $this->login();
        $this->send('/admin/seo/pages/home/draft', $this->fields('home'))->assertRedirect();
        file_put_contents($this->directory.'/sitemap.xml', '<unreviewed-change/>');
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertSame('<unreviewed-change/>', file_get_contents($this->directory.'/sitemap.xml'));
        $this->assertStringNotContainsString('unreviewed-change', $this->sitemapXml());
        $this->assertSame(1, $this->version());
    }

    public function test_existing_typed_content_adapters_appear_in_the_editor_and_accept_overrides(): void
    {
        Route::view('/qa-future-service', 'layouts.app')->name('qa.future.service');
        Route::getRoutes()->refreshNameLookups();
        $content = new ContentMetadata(
            'service:qa',
            'service',
            'qa.future.service',
            [],
            'Synthetic future service',
            '',
            published: true,
            sitemap: true
        );
        $database = app(DatabaseMetadataRepository::class);
        $this->app->instance(
            MetadataRepository::class,
            new class($database, $content) implements MetadataRepository
            {
                public function __construct(private $database, private $content) {}

                public function snapshot(): SeoSnapshot
                {
                    $snapshot = $this->database->snapshot();

                    return new SeoSnapshot($snapshot->overrides, [$this->content], $snapshot->images);
                }
            }
        );
        app()->forgetInstance(SeoResolver::class);
        $this->login();
        $this->visit('/admin/seo/pages/service:qa')
            ->assertOk()
            ->assertSee('Synthetic future service');
        $pages = app(SeoAdminPages::class);
        $fields = $pages->fields($pages->page('service:qa')) + ['expected_version' => 0];
        $fields['title'] = 'Independent metadata for a future content record';
        $fields['description'] = 'Synthetic complete editorial description';
        $this->send('/admin/seo/pages/service:qa/draft', $fields)->assertRedirect();
        $this->publish($this->latestDraft())->assertRedirect();
        $this->assertSame($fields['title'], app(SeoResolver::class)->inventory()[13]->title);
        $this->assertStringContainsString('/qa-future-service</loc>', $this->sitemapXml());
    }

    public function test_https_admin_cookie_is_secure_and_protection_headers_are_present(): void
    {
        config()->set('session.secure', true);
        app('url')->forceRootUrl('https://localhost');
        $response = $this->get('https://localhost/admin/login')
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'X-Frame-Options',
                'SAMEORIGIN'
            );
        $this->assertTrue($response->getCookie(config('session.cookie'), false)->isSecure());
        $this->assertStringContainsString(
            "script-src 'self'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_production_admin_requires_https_trusted_origin_and_debug_disabled(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.debug', false);
        foreach (['www.detailingdevils.com', 'detailing-devils-web.wasmer.app'] as $host) {
            $origin = 'https://'.$host;
            config()->set('app.url', $origin);
            app('url')->forceRootUrl($origin);
            $this->get('http://'.$host.'/admin/login')->assertNotFound();
            $this->get($origin.'/admin/login')->assertOk();
            $this->get($origin.'/admin/seo')->assertRedirect($origin.'/admin/login');
        }
        foreach ([
            'https://detailingdevils.com',
            'https://www.staging.detailingdevils.com',
            'https://www.detailingdevils.com.evil.example',
            'https://detailing-devils-web.wasmer.app.evil.example',
            'https://detailing-devils-web.wasmer.app:8443',
        ] as $origin) {
            $this->get($origin.'/admin/login')->assertNotFound();
        }
        config()->set('app.debug', true);
        $this->get('https://www.detailingdevils.com/admin/login')->assertNotFound();
        $this->get('https://detailing-devils-web.wasmer.app/admin/login')->assertNotFound();
        $this->assertSame('https://www.detailingdevils.com', config('seo.origin'));
    }

    public function test_production_admin_login_and_logout_work_on_both_trusted_hosts(): void
    {
        $this->app['env'] = 'production';
        config()->set('app.debug', false);
        foreach (['https://www.detailingdevils.com', 'https://detailing-devils-web.wasmer.app'] as $origin) {
            config()->set('app.url', $origin);
            app('url')->forceRootUrl($origin);
            $this->visit($origin.'/admin/login')->assertOk();
            $this->send($origin.'/admin/login', [
                'email' => 'seo@example.test',
                'password' => 'Synthetic QA password only!',
            ])->assertRedirect($origin.'/admin/seo');
            $this->assertAuthenticatedAs($this->administrator, 'web');
            $this->visit($origin.'/admin/seo')->assertOk();
            $this->send($origin.'/admin/logout')->assertRedirect($origin.'/admin/login');
            $this->assertGuest('web');
        }
    }

    private function assertAdminError(string $key): void
    {
        // Assert validation feedback in the standard Laravel web session.
        $errors = $this->app['request']->session()->get('errors');
        if (is_array($errors)) {
            $this->assertArrayHasKey($key, $errors['default']['messages'] ?? []);
        } else {
            $this->assertInstanceOf(ViewErrorBag::class, $errors);
            $this->assertTrue($errors->getBag('default')->has($key), 'Expected admin validation error: '.$key);
        }
    }

    private function sitemapXml(): string
    {
        app()->forgetInstance(SeoResolver::class);

        return $this->get('/sitemap.xml')
            ->assertOk()
            ->getContent();
    }

    private function login(): void
    {
        $this->visit('/admin/login')->assertOk();
        $this->send('/admin/login', ['email' => 'seo@example.test', 'password' => 'Synthetic QA password only!'])
            ->assertRedirect(route('admin.seo.index'));
        $this->visit('/admin/seo')->assertOk();
    }

    private function visit(string $path)
    {
        $this->newRequest();
        $response = $this->get($path);
        $this->remember($response);
        if (preg_match('/name="_token" value="([^"]+)"/', $response->getContent(), $match)) {
            $this->token = $match[1];
        }

        return $response;
    }

    private function send(string $path, array $fields = [])
    {
        $this->newRequest();
        $response = $this->post($path, ['_token' => $this->token] + $fields);
        $this->remember($response);

        return $response;
    }

    private function newRequest(): void
    {
        // A new PHP request uses a new session/guard instance; production uses standard web middleware.
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        app('redirect')->setSession(app('session.store'));
    }

    private function remember($response): void
    {
        if ($cookie = $response->getCookie(config('session.cookie'), false)) {
            $this->cookie = $cookie->getValue();
            $this->withUnencryptedCookie(config('session.cookie'), $this->cookie);
        }
    }

    private function fields(string $id): array
    {
        return app(SeoAdminPages::class)->fields(
            app(SeoAdminPages::class)->base()->forPage($id)
        ) + ['expected_version' => $this->version()];
    }

    private function latestDraft(): int
    {
        return (int) DB::table('seo_revisions')
            ->where('kind', 'draft')
            ->max('id');
    }

    private function version(): int
    {
        return (int) DB::table('seo_metadata')->value('version');
    }

    private function publish(int $id, ?int $expected = null, bool $policy = false)
    {
        return $this->send(
            '/admin/seo/revisions/'.$id.'/publish',
            [
                'expected_version' => $expected ?? $this->version(),
                'acknowledge_warnings' => 1,
                'approve_policy' => (int) $policy,
            ]
        );
    }
}

final class SeoFixtureContentProvider implements ContentMetadataProvider
{
    public static array $records = [];

    public function pages(): iterable
    {
        yield from self::$records;
    }
}
