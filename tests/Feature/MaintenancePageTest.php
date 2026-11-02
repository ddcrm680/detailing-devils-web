<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    #[DataProvider('renderModes')]
    public function test_real_maintenance_mode_uses_the_standalone_page_in_isolated_storage(bool $prerender): void
    {
        config()->set('app.debug', false);
        config()->set('app.maintenance.driver', 'file');
        config()->set('database.default', 'unconfigured');
        $originalStorage = $this->app->storagePath();
        $temporaryStorage = sys_get_temp_dir().'/dd-maintenance-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($temporaryStorage.'/framework', 0755, true);
        $this->app->useStoragePath($temporaryStorage);

        try {
            $options = ['--retry' => 120];
            if ($prerender) {
                $options['--render'] = 'errors::503';
            }
            $this->artisan('down', $options)->assertSuccessful();
            $this->assertFileExists($temporaryStorage.'/framework/down');
            $this->assertFileExists($temporaryStorage.'/framework/maintenance.php');
            $payload = json_decode(File::get($temporaryStorage.'/framework/down'), true);
            $this->assertSame($prerender, is_string($payload['template']));

            $response = $this->get('/')->assertStatus(503)->assertHeader('Retry-After', '120');
            $this->assertFalse($response->headers->has('Refresh'));
            $this->assertMaintenanceDocument($response->getContent());
            if ($prerender) {
                $this->assertSame($payload['template'], $response->getContent());
            }
            $this->getJson('/franchise/fofo')->assertStatus(503)->assertHeader('Retry-After', '120')
                ->assertJsonStructure(['message'])->assertDontSee('<html', false);
            $this->artisan('up')->assertSuccessful();
            $this->assertFileDoesNotExist($temporaryStorage.'/framework/down');
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }

    public function test_contacts_and_branding_are_resolved_from_existing_configuration_on_each_render(): void
    {
        config()->set('database.default', 'unconfigured');
        config()->set('site.brand', 'Synthetic QA Brand');
        config()->set('site.phone', '+91 90000 00000');
        config()->set('site.phone_link', '+919000000000');
        config()->set('site.support_email', 'support@example.test');
        $html = view('errors.503')->render();
        $this->assertStringContainsString('Synthetic QA Brand', $html);
        $this->assertStringContainsString('href="tel:+919000000000"', $html);
        $this->assertStringContainsString('+91 90000 00000', $html);
        $this->assertStringContainsString('href="mailto:support@example.test"', $html);
        $this->assertStringNotContainsString('care@detailingdevils.com', $html);
    }

    private function assertMaintenanceDocument(string $html): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame("We're Fine-Tuning Things", $xpath->query('//h1')->item(0)->textContent);
        $this->assertSame('noindex, nofollow', $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content'));
        $this->assertSame(0, $xpath->query('//script|//form|//nav|//link|//meta[@http-equiv="refresh"]')->length);
        $logo = $xpath->query('//img')->item(0);
        $this->assertSame(config('site.brand'), $logo->getAttribute('alt'));
        $this->assertSame(file_get_contents(public_path('img/logo.png')), base64_decode(substr($logo->getAttribute('src'), strlen('data:image/png;base64,'))));
        $this->assertSame(1, $xpath->query('//svg[@data-error-illustration="maintenance"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="tel:'.config('site.phone_link').'"]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="mailto:'.config('site.support_email').'"]')->length);
        $this->assertSame('/', $xpath->query('//a[@class="maintenance-retry"]')->item(0)->getAttribute('href'));
        $this->assertStringNotContainsString('whats-btn', $html);
        $this->assertStringNotContainsString('id="hdr"', $html);
        $this->assertStringNotContainsString(base_path(), $html);
    }

    public static function renderModes(): array
    {
        return [[false], [true]];
    }
}
