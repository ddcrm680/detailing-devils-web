<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.debug', false);
        config()->set('database.default', 'unconfigured');
    }

    #[DataProvider('statuses')]
    public function test_html_errors_preserve_status_headers_and_hide_exception_details(int $status, string $title): void
    {
        $exception = new HttpException($status, 'PRIVATE_EXCEPTION_SENTINEL', null, ['Retry-After' => '60']);
        $response = app(ExceptionHandler::class)->render(Request::create('/missing-page'), $exception);
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('60', $response->headers->get('Retry-After'));
        $html = $response->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame($title, $xpath->query('//h1')->item(0)->textContent);
        if ($status === 503) {
            $this->assertSame('noindex, nofollow', $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content'));
            $this->assertSame(0, $xpath->query('//script|//form|//nav|//link')->length);
            $this->assertSame('maintenance', $xpath->query('//svg[@data-error-illustration]')->item(0)->getAttribute('data-error-illustration'));
            $this->assertStringNotContainsString('PRIVATE_EXCEPTION_SENTINEL', $html);

            return;
        }
        $this->assertSame((string) $status, $xpath->query('//p[@class="error-code"]')->item(0)->textContent);
        $this->assertSame('noindex, nofollow', $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content'));
        $this->assertSame(url('/'), $xpath->query('//a[contains(@class,"error-home")]')->item(0)->getAttribute('href'));
        $this->assertSame(asset('img/logo.png'), $xpath->query('//img')->item(0)->getAttribute('src'));
        $this->assertSame(1, $xpath->query('//header[@id="hdr"]')->length);
        $this->assertSame(1, $xpath->query('//div[@id="menu"]')->length);
        $this->assertSame(1, $xpath->query('//footer')->length);
        $this->assertSame(0, $xpath->query('//form')->length);
        $scripts = $xpath->query('//script');
        $this->assertSame(1, $scripts->length);
        $this->assertSame(asset('assets/js/modules/navigation.js'), $scripts->item(0)->getAttribute('src'));
        $this->assertSame(1, $xpath->query('//noscript/nav[@aria-label="Essential navigation"]')->length);
        $this->assertStringNotContainsString('csrf-token', $html);
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION_SENTINEL', $html);
        $this->assertStringNotContainsString(base_path(), $html);
        $svg = $xpath->query('//svg[@data-error-illustration]');
        $this->assertSame(1, $svg->length);
        $this->assertSame('img', $svg->item(0)->getAttribute('role'));
        $this->assertSame('0 0 560 420', $svg->item(0)->getAttribute('viewbox'));
        $this->assertSame('error-art-title error-art-description', $svg->item(0)->getAttribute('aria-labelledby'));
        $this->assertNotEmpty($xpath->query('//svg/title[@id="error-art-title"]')->item(0)->textContent);
        $this->assertNotEmpty($xpath->query('//svg/desc[@id="error-art-description"]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//svg//image|//svg//script|//svg//foreignobject|//svg//animate')->length);
        $variants = [403 => 'access', 404 => 'route', 419 => 'pass', 429 => 'queue', 500 => 'inspection', 503 => 'maintenance'];
        $this->assertSame($variants[$status] ?? ($status < 500 ? 'client' : 'system'), $svg->item(0)->getAttribute('data-error-illustration'));
    }

    public function test_unknown_illustration_variants_use_the_predefined_safe_fallback(): void
    {
        foreach (['<script>PRIVATE_INPUT</script>', [], null] as $variant) {
            $html = view('errors.illustration', compact('variant'))->render();
            $this->assertStringContainsString('data-error-illustration="system"', $html);
            $this->assertStringNotContainsString('PRIVATE_INPUT', $html);
        }
    }

    #[DataProvider('statuses')]
    public function test_api_and_ajax_errors_stay_json(int $status, string $title): void
    {
        foreach ([Request::create('/api/missing'), Request::create('/missing', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json'])] as $request) {
            $response = app(ExceptionHandler::class)->render($request, new HttpException($status, 'Existing JSON contract'));
            $this->assertSame($status, $response->getStatusCode());
            $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
            $this->assertSame('Existing JSON contract', json_decode($response->getContent(), true)['message']);
            $this->assertStringNotContainsString('<html', $response->getContent());
        }
    }

    public function test_runtime_failures_render_the_safe_500_view_without_session_or_database_access(): void
    {
        $response = app(ExceptionHandler::class)->render(Request::create('/'), new \RuntimeException('PRIVATE_EXCEPTION_SENTINEL'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Something Went Wrong', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION_SENTINEL', $response->getContent());
    }

    public function test_real_missing_page_and_csrf_expiry_use_the_branded_views(): void
    {
        $this->get('/nonexistent-error-page-qa')->assertNotFound()->assertSee('Page Not Found')->assertSee('error-home');
        $response = app(ExceptionHandler::class)->render(Request::create('/franchise/fofo', 'POST'), new TokenMismatchException('PRIVATE_EXCEPTION_SENTINEL'));
        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('Page Expired', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION_SENTINEL', $response->getContent());
    }

    public function test_normal_web_validation_and_json_validation_are_unchanged(): void
    {
        Route::post('/test-only-validation', function (Request $request): void {
            $request->validate(['name' => 'required']);
        })->middleware('web');
        $this->from('/')->post('/test-only-validation')->assertRedirect('/')->assertSessionHasErrors('name');

        $request = Request::create('/franchise/fofo', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $response = app(ExceptionHandler::class)->render($request, ValidationException::withMessages(['name' => 'The name field is required.']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['The name field is required.'], json_decode($response->getContent(), true)['errors']['name']);
    }

    public static function statuses(): array
    {
        return [
            [403, 'Access Denied'],
            [404, 'Page Not Found'],
            [419, 'Page Expired'],
            [429, 'Too Many Requests'],
            [500, 'Something Went Wrong'],
            [503, "We're Fine-Tuning Things"],
            [418, 'Request Could Not Be Completed'],
            [502, 'Service Interrupted'],
            [504, 'Service Interrupted'],
        ];
    }
}
