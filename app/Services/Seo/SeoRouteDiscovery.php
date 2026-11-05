<?php

namespace App\Services\Seo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteAction;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Facades\Route as Routes;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Serializers\Native;
use Laravel\SerializableClosure\Serializers\Signed;
use Laravel\SerializableClosure\Support\SelfReference;
use Laravel\SerializableClosure\UnsignedSerializableClosure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/** Inspects route declarations only; never executes a page, middleware or controller. */
final class SeoRouteDiscovery
{
    public function id(Route $route): string
    {
        return $this->name($route) ?? 'route.'.substr(hash('sha256', $route->uri()), 0, 32);
    }

    public function publicRoute(Route $route, bool $declaredPage = false): bool
    {
        $hint = $route->defaults['_seo'] ?? [];
        $controller = ltrim($route->getControllerClass() ?? '', '\\');
        if (
            ! in_array('GET', $route->methods(), true) || $route->isFallback
            || $controller === RedirectController::class
            || in_array('api', $route->gatherMiddleware(), true)
            || (is_array($hint) && ($hint['public'] ?? null) === false)
            || ($controller === ViewController::class
            && (($route->defaults['status'] ?? 200) !== 200
            || ! app('view')->exists($route->defaults['view'] ?? '')))
            || ! preg_match('/^[A-Za-z0-9_.:-]{1,200}$/', $this->id($route))
        ) {
            return false;
        }
        if (
            $route->getDomain() !== null
            && strtolower($route->getDomain()) !== parse_url(config('seo.origin'), PHP_URL_HOST)
        ) {
            return false;
        }
        foreach (config('seo.discovery.exclude', []) as $pattern) {
            if (
                Str::is($pattern, strtolower(trim($route->uri(), '/')))
                || Str::is($pattern, strtolower($route->getName() ?? ''))
            ) {
                return false;
            }
        }
        foreach (app('router')->resolveMiddleware(
            $route->gatherMiddleware(),
            $route->excludedMiddleware()
        ) as $middleware) {
            $class = ltrim(explode(':', $middleware, 2)[0], '\\');
            foreach ([$class, ...(class_exists($class) ? array_values(class_parents($class)) : [])] as $type) {
                if (
                    str_starts_with($type, 'Illuminate\\Auth\\Middleware\\')
                    || str_starts_with($type, 'Laravel\\Sanctum\\')
                    || $type === ValidateSignature::class
                ) {
                    return false;
                }
            }
        }
        foreach ($route->gatherMiddleware() as $middleware) {
            $name = explode(':', $middleware, 2)[0];
            if (
                in_array(
                    $name,
                    [
                        'auth',
                        'auth.basic',
                        'auth.session',
                        'guest',
                        'can',
                        'verified',
                        'signed',
                        'password.confirm',
                    ],
                    true
                )
                || (! in_array($name, ['web', 'throttle', 'bindings', 'cache.headers'], true) && ! $declaredPage)
            ) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(array $providedRoutes = []): array
    {
        $definitions = [];
        foreach (Routes::getRoutes() as $route) {
            $hint = $route->defaults['_seo'] ?? [];
            $declared = is_array($hint) && ($hint['public'] ?? false) === true;
            if (
                in_array($route->getName(), $providedRoutes, true)
                || $route->parameterNames() !== []
                || ! $this->publicRoute($route, $declared)
                || (! $declared && ! $this->rendersView($route))
            ) {
                continue;
            }
            $label = Str::headline(basename(trim($route->uri(), '/')) ?: 'Home');
            $definition = array_replace(
                [
                    'title' => $label.' | '.config('site.brand', 'Detailing Devils'),
                    'description' => 'Explore '.$label.' at '.config('site.brand', 'Detailing Devils').'.',
                    'robots' => config('seo.defaults.robots', 'index, follow'),
                    'sitemap' => true,
                ],
                is_array($hint)
                ? array_intersect_key(
                    $hint,
                    array_flip([
                        'title',
                        'description',
                        'robots',
                        'sitemap',
                        'og_type',
                        'image',
                        'og_title',
                        'og_description',
                        'twitter_title',
                        'twitter_description',
                    ])
                )
                : []
            );
            $definition['sitemap'] = (bool) $definition['sitemap'] && ! str_contains(
                $definition['robots'],
                'noindex'
            );
            if ($this->name($route) !== null) {
                $definition['route'] = $route->getName();
            } else {
                $definition['path'] = '/'.ltrim($route->uri(), '/');
            }
            $id = $this->id($route);
            $definitions[$id] = $definition;
            foreach (is_array($hint) ? ($hint['variants'] ?? []) : [] as $suffix => $parameters) {
                $variantId = $id.'.'.$suffix;
                $definitions[$variantId] = array_replace(
                    $definition,
                    ['route' => $route->getName(), 'parameters' => $parameters]
                );
            }
        }

        return $definitions;
    }

    private function name(Route $route): ?string
    {
        $name = $route->getName();

        return $name !== null && ! str_starts_with($name, 'generated::') ? $name : null;
    }

    private function rendersView(Route $route): bool
    {
        if (ltrim($route->getControllerClass() ?? '', '\\') === ViewController::class) {
            return true;
        }
        try {
            $action = $route->getAction('uses');
            if (RouteAction::containsSerializedClosure($route->getAction())) {
                // Match Laravel's allowlist when inspecting a trusted compiled route cache.
                $action = unserialize(
                    $action,
                    [
                        'allowed_classes' => [
                            SerializableClosure::class,
                            UnsignedSerializableClosure::class,
                            Native::class,
                            Signed::class,
                            SelfReference::class,
                        ],
                    ]
                )->getClosure();
            }
            if ($action instanceof Closure) {
                $reflection = new ReflectionFunction($action);
            } else {
                [$controller, $method] = Str::parseCallback($action, '__invoke');
                $reflection = new ReflectionMethod($controller, $method);
            }
            $type = $reflection->getReturnType();

            return $type instanceof ReflectionNamedType && is_a($type->getName(), View::class, true);
        } catch (Throwable) {
            return false;
        }
    }
}
