<?php

namespace Tests\Feature;

use App\Contracts\Seo\MetadataRepository;
use App\Http\Controllers\SeoAdmin\MetadataController;
use App\Models\User;
use App\Services\Seo\Admin\SeoAdminPages;
use App\Services\Seo\Admin\SeoPublicationCodec;
use App\Services\Seo\Admin\SeoPublisher;
use App\Services\Seo\Admin\SeoStorage;
use App\Services\Seo\SeoMetadata;
use App\Services\Seo\SeoSnapshot;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'unconfigured');
        config()->set('session.driver', 'array');
        DB::shouldReceive('connection')->never();
        $this->app->instance(MetadataRepository::class, new class implements MetadataRepository
        {
            public function snapshot(): SeoSnapshot
            {
                return new SeoSnapshot;
            }
        });
        view()->share('errors', new ViewErrorBag);
    }

    public static function editorData(): array
    {
        $page = new SeoMetadata(
            id: 'home',
            title: 'Detailing Devils – Premium Car & Bike Detailing',
            description: 'Professional car and bike detailing, ceramic coating and paint protection from Detailing Devils.',
            canonical: 'https://www.detailingdevils.com/',
            sitemap: true,
        );
        $fields = app(SeoAdminPages::class)->fields($page);
        $draft = (object) [
            'id' => 42,
            'base_version' => 7,
            'policy_reason' => null,
        ];

        return [
            'page' => $page,
            'fields' => $fields,
            'draft' => $draft,
            'history' => collect([
                (object) [
                    'id' => 41,
                    'kind' => 'published',
                    'created_at' => '2026-11-06 11:24:00',
                    'fields' => json_encode(array_replace($fields, ['title' => 'Detailing Devils – Automotive Detailing'])),
                ],
            ]),
            'state' => (object) ['version' => 7],
            'images' => ['https://www.detailingdevils.com/img/recent-work.webp' => 'Local preview image (fixture)'],
            'canonicals' => [$page],
            'warnings' => [],
        ];
    }

    public function test_editor_preserves_all_existing_form_contracts_and_policy_controls(): void
    {
        foreach (['super_admin', 'admin', 'seo_admin'] as $role) {
            $user = new User(['name' => 'UI preview', 'email' => 'preview@example.test']);
            $user->forceFill(['id' => 1, 'role' => $role]);
            $this->actingAs($user);
            $html = view('admin.seo.edit', self::editorData())->render();
            $xpath = $this->xpath($html);

            foreach (['title', 'description', 'og_title', 'og_description', 'og_type', 'twitter_title', 'twitter_description', 'image', 'canonical', 'robots', 'policy_reason'] as $name) {
                $this->assertSame(1, $xpath->query('//*[@id="metadata-form"]//*[@name="'.$name.'"]')->length, $name);
            }
            $this->assertSame(2, $xpath->query('//*[@id="metadata-form"]//*[@name="sitemap"]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="metadata-form"]//*[@name="expected_version"]')->length);
            $this->assertSame(route('admin.seo.draft', 'home'), $xpath->query('//*[@id="metadata-form"]')->item(0)->getAttribute('action'));
            $this->assertSame(route('admin.seo.publish', 42), $xpath->query('//*[@id="publish-controls"]')->item(0)->getAttribute('action'));
            $this->assertSame(1, $xpath->query('//form[@data-confirm="restore"]')->length);
            $this->assertSame(1, $xpath->query('.//input[@name="acknowledge_warnings"][@required]', $xpath->query('//*[@id="publish-controls"]')->item(0))->length);
            $this->assertSame($role === 'super_admin' ? 2 : 0, $xpath->query('//input[@name="approve_policy"]')->length);
            foreach (['social-settings', 'advanced-settings', 'version-history'] as $id) {
                $this->assertSame(1, $xpath->query('//details[@id="'.$id.'"][not(@open)]')->length);
            }
            $this->assertSame(0, $xpath->query('//dialog[@aria-labelledby="confirmation-title"]')->length);
            $this->assertSame(1, $xpath->query('//script[contains(@src, "/assets/vendor/sweetalert2.min.js")]')->length);
            $this->assertSame(1, $xpath->query('//script[contains(@src, "/assets/js/common/loader.js")]')->length);
            $this->assertSame(1, $xpath->query('//script[contains(@src, "/assets/js/common/notification.js")]')->length);
            $this->assertSame(0, $xpath->query('//*[@id="metadata-findings"]')->length);
            $this->assertStringContainsString('In editor:', $html);
            $this->assertStringNotContainsString('@vite', $html);
        }
    }

    public function test_inventory_uses_dynamic_counts_and_keeps_all_filters_and_pagination(): void
    {
        $user = new User(['name' => 'UI preview', 'email' => 'preview@example.test']);
        $user->forceFill(['id' => 1, 'role' => 'seo_admin']);
        $this->actingAs($user);
        $data = self::editorData();
        $html = view('admin.seo.index', [
            'pages' => [$data['page']],
            'summary' => ['total' => 501, 'published' => 400, 'draft' => 32, 'indexable' => 490, 'sitemap' => 480],
            'statuses' => ['home' => ['draft' => true, 'published' => true]],
            'count' => 501, 'number' => 2, 'lastPage' => 21, 'perPage' => 25,
            'filters' => ['q' => 'Home', 'status' => 'draft'], 'query' => 'Home',
            'state' => $data['state'], 'warnings' => [], 'images' => [],
        ])->render();
        $xpath = $this->xpath($html);

        foreach (['q', 'status', 'indexing', 'sitemap_filter', 'per_page'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
        }
        $this->assertStringContainsString('<strong>501</strong>', $html);
        $this->assertStringContainsString('Showing 26', $html);
        $this->assertStringContainsString('status=draft', $html);
        $this->assertSame(1, $xpath->query('//nav[@aria-label="Inventory pagination"]//a[@aria-current="page"]')->length);
        $this->assertStringNotContainsString('Add Page', $html);
        $this->assertStringNotContainsString('/admin/studios', $html);
        $this->assertSame(
            ['Page', 'URL', 'Status', 'Sitemap', 'Edit'],
            array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//table/thead/tr/th'))),
        );
        $this->assertSame(1, $xpath->query('//select[@name="per_page"]/option[@value="10"]')->length);
        $this->assertSame(1, $xpath->query('//details[@data-filter-panel]/summary/span[contains(., "1 active")]')->length);
        $this->assertSame(1, $xpath->query('//td[@data-label="Status"]//span[contains(@class, "pill")]')->length);
        $this->assertSame(1, $xpath->query('//a[@aria-label="Edit SEO for Home"]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-overview-tab]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-overview-panel][not(@hidden)]')->length);
        $this->assertSame(4, $xpath->query('//*[@id="pages-panel"]//*[contains(concat(" ", normalize-space(@class), " "), " stat ")]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@data-upload-dialog][not(@open)]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-upload-fallback]//form[@action="'.route('admin.seo.images.upload').'#image-library"][@enctype="multipart/form-data"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-upload-form]//input[@name="approve_image"][@required]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-upload-form]//input[@name="_token"]')->length);
    }

    public function test_seo_scripts_are_pushed_once_after_shared_dependencies_and_are_not_global(): void
    {
        $html = view('admin.seo.edit', self::editorData())->render();
        $xpath = $this->xpath($html);
        $scripts = iterator_to_array($xpath->query('//script[@src]'));
        $sources = array_map(fn ($script) => $script->getAttribute('src'), $scripts);

        $this->assertCount(4, $sources);
        $this->assertStringContainsString('/vendor/sweetalert2.min.js', $sources[0]);
        $this->assertStringContainsString('/common/loader.js', $sources[1]);
        $this->assertStringContainsString('/common/notification.js', $sources[2]);
        $this->assertStringContainsString('/admin/workspace.js?v=', $sources[3]);
        $this->assertSame('module', $scripts[3]->getAttribute('type'));
        foreach (array_slice($scripts, 0, 3) as $script) {
            $this->assertTrue($script->hasAttribute('defer'));
        }
        $this->assertStringNotContainsString('workspace.js', view('admin.login')->render());

        $core = file_get_contents(public_path('assets/vendor/sweetalert2.min.js'));
        $this->assertStringContainsString('sweetalert2 v11.26.25', $core);
        $this->assertSame('520341374aa1ee4387d6564e46c0fb0485addc98431ee246cd1be6368fedb1b0', hash('sha256', $core));
        $this->assertStringNotContainsString('createElement("style")', $core);
    }

    public function test_public_homepage_uses_the_same_standalone_script_and_external_styles_as_admin(): void
    {
        $response = $this->get('/')->assertOk();
        $xpath = $this->xpath($response->getContent());

        $this->assertSame(1, $xpath->query('//script[contains(@src, "/vendor/sweetalert2.min.js")]')->length);
        $this->assertSame(1, $xpath->query('//link[contains(@href, "/vendor/sweetalert2.min.css")]')->length);
        $this->assertSame(1, $xpath->query('//script[contains(@src, "/common/notification.js")]')->length);
        $this->assertSame(1, $xpath->query('//script[contains(@src, "/common/loader.js")]')->length);
        $this->assertStringNotContainsString('sweetalert2.all.min.js', $response->getContent());
    }

    public function test_upload_validation_reopens_the_modal_with_old_values_and_no_success_claim(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'image_file' => 'The image must be a JPEG or PNG.',
        ])));
        $this->app['request']->setLaravelSession(session()->driver());
        session()->flashInput(['label' => 'Approved campaign artwork', 'approve_image' => '1']);
        $data = self::editorData();
        $html = view('admin.seo.index', [
            'pages' => [$data['page']],
            'summary' => ['total' => 1, 'published' => 1, 'draft' => 0, 'indexable' => 1, 'sitemap' => 1],
            'statuses' => ['home' => ['draft' => false, 'published' => true]],
            'count' => 1, 'number' => 1, 'lastPage' => 1, 'perPage' => 10,
            'filters' => [], 'query' => '',
            'state' => $data['state'], 'warnings' => [], 'images' => [],
        ])->render();
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//dialog[@data-upload-errors]')->length);
        $this->assertSame(1, $xpath->query('//details[@data-upload-fallback][@open]')->length);
        $this->assertSame('Approved campaign artwork', $xpath->query('//input[@name="label"]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//input[@name="approve_image"][@checked][@required]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="image_file"][@aria-invalid="true"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-upload-validation][@role="alert"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="image-file-error"][@data-error-for="image_file"]')->length);
        $this->assertStringContainsString('The image must be a JPEG or PNG.', $html);
        $this->assertStringNotContainsString('Image approved and safely re-encoded.', $html);
    }

    public function test_editor_combines_live_previews_without_changing_validated_preview_or_image_contracts(): void
    {
        $html = view('admin.seo.edit', self::editorData())->render();
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//*[@data-preview-tabs][@hidden]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-preview-tab]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-preview-panel][not(@hidden)]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-search-title]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-social-title]')->length);
        $this->assertSame(1, $xpath->query('//button[@form="metadata-form"][@formtarget="_blank"][@formaction="'.route('admin.seo.preview', 'home').'"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="'.route('admin.seo.index').'#image-library"][@target="_blank"][@rel="noopener"]')->length);
        $this->assertStringContainsString('Save your draft first.', $html);
        $this->assertStringNotContainsString('Quick Tips', $html);
        $this->assertStringNotContainsString('id="social-preview"', $html);
    }

    public function test_missing_draft_and_relevant_errors_remain_accessible_without_javascript(): void
    {
        $data = self::editorData();
        $data['draft'] = null;
        $data['warnings'] = [['level' => 'warning', 'page' => 'home', 'message' => 'Review the description.']];
        $html = view('admin.seo.edit', $data)->render();
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//button[@disabled][contains(text(), "Publish")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="publish-controls"]')->length);
        $this->assertStringContainsString('Review the description.', $html);
        $this->assertStringContainsString('<noscript>', $html);
    }

    public function test_editor_only_shows_exact_page_and_grouped_findings_without_reclassifying_errors(): void
    {
        $data = self::editorData();
        $data['warnings'] = [
            ['level' => 'warning', 'page' => 'home', 'message' => 'No approved social-sharing image.'],
            ['level' => 'warning', 'page' => 'home, studios.tour', 'message' => 'Duplicate description.'],
            ['level' => 'error', 'page' => 'home, legal.terms', 'message' => 'Conflicting canonical targets.'],
            ['level' => 'error', 'page' => 'home-copy', 'message' => 'Unrelated error.'],
            ['level' => 'warning', 'page' => 'legal.privacy', 'message' => 'Unrelated suggestion.'],
        ];
        $html = view('admin.seo.edit', $data)->render();
        $xpath = $this->xpath($html);
        $this->assertSame(3, $xpath->query('//*[@id="metadata-findings"]//li')->length);
        $this->assertStringContainsString('Optional: choose a social sharing image.', $html);
        $this->assertStringContainsString('Another page uses this description.', $html);
        $this->assertStringContainsString('Conflicting canonical targets.', $html);
        $this->assertSame(1, $xpath->query('//*[@id="metadata-findings"]//*[contains(@class, "critical")]')->length);
        $this->assertStringNotContainsString('Unrelated error.', $html);
        $this->assertStringNotContainsString('Unrelated suggestion.', $html);
        $this->assertStringContainsString('Social Sharing Image', $html);
        $this->assertStringContainsString('Manage Images', $html);
    }

    public function test_inventory_defaults_to_ten_rows_and_preserves_filtering_with_mocked_storage(): void
    {
        $payload = app(SeoPublicationCodec::class)->encode(new SeoSnapshot);
        $state = (object) [
            'origin' => config('seo.origin'),
            'payload' => $payload,
            'payload_hash' => hash('sha256', $payload),
            'version' => 0,
        ];
        $query = Mockery::mock();
        $query->shouldReceive('where', 'whereIn', 'selectRaw', 'groupBy')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($state);
        $query->shouldReceive('get')->andReturn(collect());
        $driver = Mockery::mock();
        $driver->shouldReceive('getDriverName')->andReturn('sqlite');
        $database = Mockery::mock(ConnectionResolverInterface::class);
        $database->shouldReceive('connection')->andReturn($driver);
        $database->shouldReceive('table')->withAnyArgs()->andReturn($query);
        DB::swap($database);

        $inventory = app(SeoAdminPages::class);
        $controller = app(MetadataController::class);
        $get = fn (array $input) => $controller->index(
            Request::create('/admin/seo', 'GET', $input),
            app(SeoStorage::class),
            app(SeoPublisher::class),
            $inventory,
        )->getData();
        $default = $get([]);
        $this->assertSame(10, $default['perPage']);
        $this->assertCount(min(10, $default['count']), $default['pages']);
        $this->assertSame((int) ceil($default['count'] / 10), $default['lastPage']);
        $second = $get(['page' => 2, 'per_page' => 10]);
        $this->assertSame(2, $second['number']);
        $this->assertCount($default['count'] - 10, $second['pages']);
        $this->assertNotSame($default['pages'][0]->id, $second['pages'][0]->id);
        $this->assertSame(25, $get(['per_page' => 25])['perPage']);
        $this->assertSame(1, $get(['q' => 'home'])['count']);
        $this->assertSame(0, $get(['indexing' => 'noindex'])['count']);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
