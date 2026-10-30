<?php

namespace Tests\Feature\Franchise;

use App\Actions\Franchise\SendFranchiseCallAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FranchiseCallingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'unconfigured');
        config()->set('services.fofo.call_enabled', true);
        config()->set('services.xclusive.call_enabled', true);
        config()->set('services.franchise_call.url', 'https://calls.example.test/callback');
        Http::preventStrayRequests();
        Mail::fake();
    }

    #[DataProvider('models')]
    public function test_both_models_use_the_same_url_format_and_timeouts(string $model): void
    {
        Http::fake(function ($request, array $options) {
            $this->assertSame('GET', $request->method());
            $this->assertSame('https://calls.example.test/callback?cust_mob=09876543210', $request->url());
            $this->assertSame(2, $options['connect_timeout']);
            $this->assertSame(4, $options['timeout']);

            return Http::response('ok');
        });
        app(SendFranchiseCallAction::class)($model, '+919876543210');
        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }

    #[DataProvider('models')]
    public function test_model_activation_is_independent(string $model): void
    {
        config()->set("services.{$model}.call_enabled", false);
        app(SendFranchiseCallAction::class)($model, '+919876543210');
        Http::assertNothingSent();
    }

    #[DataProvider('urlEnvironmentSettings')]
    public function test_universal_url_configuration_is_used_by_both_models(string $model, ?string $url): void
    {
        // Evaluate only the services file in a fresh process; never load the real .env.
        $process = new Process([
            PHP_BINARY, '-r',
            'require "vendor/autoload.php"; $services = require "config/services.php"; echo json_encode($services["franchise_call"]["url"]);',
        ], base_path(), [
            'FRANCHISE_CALL_URL' => $url ?? false,
        ]);
        $process->mustRun();
        $resolved = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($url, $resolved);
        config()->set('services.franchise_call.url', $resolved);
        Http::fake();

        app(SendFranchiseCallAction::class)($model, '+919876543210');

        if (! $url) {
            Http::assertNothingSent();
        } else {
            Http::assertSentCount(1);
            Http::assertSent(fn ($request): bool => $request->method() === 'GET'
                && $request->url() === $url.'?cust_mob=09876543210');
        }
        Mail::assertNothingSent();
    }

    public static function urlEnvironmentSettings(): array
    {
        $cases = [];
        foreach (['fofo', 'xclusive'] as $model) {
            $cases[$model.' configured'] = [$model, 'https://calls.example.test/shared'];
            $cases[$model.' unset'] = [$model, null];
            $cases[$model.' empty'] = [$model, ''];
        }

        return $cases;
    }

    #[DataProvider('invalidEndpoints')]
    public function test_missing_or_invalid_https_endpoints_are_skipped(string $model, mixed $url): void
    {
        config()->set('services.franchise_call.url', $url);
        Log::spy();
        app(SendFranchiseCallAction::class)($model, '+919876543210');
        Http::assertNothingSent();
        $label = $model === 'fofo' ? 'FOFO' : 'X-Clusive';
        Log::shouldHaveReceived('warning')->with("{$label} calling callback skipped: HTTPS endpoint is not configured.")->once();
    }

    public static function invalidEndpoints(): array
    {
        $cases = [];
        foreach (['fofo', 'xclusive'] as $model) {
            foreach ([null, '', 'http://calls.example.test/callback', 'https:///missing-host', 'javascript:alert(1)'] as $url) {
                $cases[] = [$model, $url];
            }
        }

        return $cases;
    }

    #[DataProvider('nonCanonicalPhones')]
    public function test_only_the_normalized_indian_legacy_format_can_be_sent(string $model, string $mobile): void
    {
        Log::spy();
        app(SendFranchiseCallAction::class)($model, $mobile);
        Http::assertNothingSent();
        $label = $model === 'fofo' ? 'FOFO' : 'X-Clusive';
        Log::shouldHaveReceived('warning')->with("{$label} calling callback skipped: the mobile number is outside the legacy format.")->once();
    }

    public static function nonCanonicalPhones(): array
    {
        $cases = [];
        foreach (['fofo', 'xclusive'] as $model) {
            foreach (['+12025550123', '919876543210', 'invalid', ''] as $mobile) {
                $cases[] = [$model, $mobile];
            }
        }

        return $cases;
    }

    public static function models(): array
    {
        return [['fofo'], ['xclusive']];
    }
}
