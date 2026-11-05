<?php

namespace App\Services\Seo;

use App\Contracts\Seo\MetadataRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class SeoResolver
{
    private ?SeoSnapshot $snapshot = null;

    private array $repositoryWarnings = [];

    private ?array $definitions = null;

    public function __construct(private MetadataRepository $repository, private SeoUrl $urls) {}

    public function publicationAvailable(): bool
    {
        $this->snapshot();

        return $this->repositoryWarnings === [];
    }

    private function definitions(): array
    {
        return $this->definitions ??= app(SeoRouteDiscovery::class)->definitions(
            array_map(fn ($content) => $content->routeName, $this->snapshot()->content),
        );
    }

    private function snapshot(): SeoSnapshot
    {
        if ($this->snapshot === null) {
            try {
                $this->snapshot = $this->repository->snapshot();
            } catch (Throwable $exception) {
                // Public pages keep their code-managed metadata if an optional store fails.
                $this->repositoryWarnings[] = 'Publication store unavailable; using code-managed metadata.';
                Log::warning('SEO publication store unavailable.', ['exception' => $exception::class]);
                $this->snapshot = new SeoSnapshot;
            }
        }

        return $this->snapshot;
    }

    public function forRequest(Request $request, ?ContentMetadata $content = null, array $legacy = []): SeoMetadata
    {
        $route = $request->route();
        $id = $route ? app(SeoRouteDiscovery::class)->id($route) : 'unregistered';
        $variants = $route ? ($route->defaults['_seo']['variants'] ?? []) : [];
        foreach ($variants as $suffix => $parameters) {
            $matches = $parameters !== [];
            foreach ($parameters as $name => $value) {
                $matches = $matches && $request->query($name) === $value;
            }
            if ($matches) {
                $id .= '.'.$suffix;
                break;
            }
        }
        if ($content !== null) {
            if ($content->routeName !== $request->route()?->getName()) {
                throw new InvalidArgumentException('SEO content must match the rendered route.');
            }

            return $this->forPage($content->id, $content);
        }

        return $this->forPage($id, fallbackPath: $request->getPathInfo(), legacy: $legacy);
    }

    public function forPage(
        string $id,
        ?ContentMetadata $content = null,
        string $fallbackPath = '/',
        array $legacy = []
    ): SeoMetadata {
        // Dot-containing stable IDs are literal registry keys, not configuration paths.
        $definition = $this->definitions()[$id]
            ?? ($content
                ? (config('seo.route_defaults', [])[$content->routeName] ?? [])
                : []);
        $brand = (string) config('site.brand', 'Detailing Devils');
        $data = array_replace(
            [
                'title' => $brand,
                'description' => (string) config('site.description', ''),
                'canonical' => $this->urls->canonical($fallbackPath),
                'sitemap' => false,
            ],
            config('seo.defaults', []),
            $definition
        );
        foreach (['title', 'description'] as $field) {
            if (isset($definition[$field.'_config'])) {
                $data[$field] = config($definition[$field.'_config'], $data[$field]);
            }
        }
        if ($content === null && isset($definition['route'])) {
            $data['canonical'] = $this->urls->canonical(route(
                $definition['route'],
                $definition['parameters'] ?? [],
                false
            ));
        } elseif ($content === null && isset($definition['path'])) {
            $data['canonical'] = $this->urls->canonical($definition['path']);
        }
        $nodes = [];
        if ($id === 'home') {
            $home = $this->urls->canonical('/');
            $nodes = [JsonLd::organization($brand, $home), JsonLd::website($brand, $home)];
        }
        if ($content !== null) {
            if ($id !== $content->id || isset(config('seo.pages', [])[$id])) {
                throw new InvalidArgumentException('Content needs a unique stable SEO identity.');
            }
            $template = config('seo.templates', [])[$content->type];
            $data = array_replace(
                $data,
                $template,
                [
                    'title' => strtr($template['title'], ['{title}' => $content->title, '{brand}' => $brand]),
                    'description' => trim($content->description) !== ''
                                        ? $content->description
                                        : ($template['description'] ?? $data['description']),
                    'canonical' => $this->urls->canonical(route(
                        $content->routeName,
                        $content->routeParameters,
                        false
                    )),
                    'robots' => $content->indexable && $content->published ? 'index, follow' : 'noindex, follow',
                    'sitemap' => $content->sitemap,
                    'image' => $content->image,
                ]
            );
            $nodes = $content->structuredData;
        }
        // Existing page sections remain compatible; an authorized published override still wins.
        $data = array_replace($data, array_intersect_key($legacy, array_flip(['title', 'description'])));
        $base = $data;
        $snapshot = $this->snapshot();
        $warnings = $this->repositoryWarnings;
        if ($content && ! $content->indexable) {
            $warnings[] = 'Content source requires noindex; SEO overrides cannot enable indexing.';
        }
        if ($definition === [] && $content === null) {
            $warnings[] = 'Unregistered page: brand fallback; excluded from sitemap.';
        }
        $locale = $content?->locale ?? config('seo.locale', 'en');
        $explicitDescription = false;
        foreach ($snapshot->overrides as $override) {
            if ($override->published && $override->pageId === $id && $override->locale === $locale) {
                $data = array_replace($data, $override->fields);
                $explicitDescription = isset($override->fields['description'])
                    && trim($override->fields['description']) !== '';
            }
        }
        if ($content && trim($content->description) === '' && ! $explicitDescription) {
            $warnings[] = 'Content description missing; using route/site fallback.';
        }
        try {
            return $this->metadata(
                $id,
                $data,
                $content,
                $nodes,
                $warnings,
                $locale,
                $this->urls->canonical($data['canonical']) !== $base['canonical']
            );
        } catch (InvalidArgumentException $exception) {
            if ($data === $base) {
                throw $exception;
            }
            // Reject an invalid publication atomically; do not partially publish unsafe fields.
            $warnings[] = 'Invalid published override rejected; using base metadata.';

            return $this->metadata($id, $base, $content, $nodes, $warnings, $locale);
        }
    }

    private function metadata(
        string $id,
        array $data,
        ?ContentMetadata $content,
        array $nodes,
        array $warnings,
        string $locale,
        bool $canonicalAliasApproved = false
    ): SeoMetadata {
        return new SeoMetadata(
            id: $id,
            title: $data['title'],
            description: $data['description'],
            canonical: $this->urls->canonical($data['canonical']),
            robots: ($content && (! $content->published || ! $content->indexable))
                ? 'noindex, follow'
                : $data['robots'],
            ogType: $data['og_type'],
            image: $this->urls->image($data['image'] ?? null, $this->snapshot()->images),
            ogTitle: $data['og_title'] ?? null,
            ogDescription: $data['og_description'] ?? null,
            twitterTitle: $data['twitter_title'] ?? null,
            twitterDescription: $data['twitter_description'] ?? null,
            published: $content?->published ?? true,
            sitemap: $data['sitemap'],
            priority: $data['priority'] ?? '0.5000',
            locale: $locale,
            modifiedAt: $content?->modifiedAt,
            structuredData: $nodes,
            warnings: $warnings,
            canonicalAliasApproved: $canonicalAliasApproved,
        );
    }

    /** @return list<SeoMetadata> Includes ineligible records so diagnostics can explain exclusions. */
    public function inventory(): array
    {
        $pages = [];
        foreach (array_keys($this->definitions()) as $id) {
            $pages[] = $this->forPage($id);
        }
        foreach ($this->snapshot()->content as $content) {
            $route = app('router')->getRoutes()->getByName($content->routeName);
            if ($route && app(SeoRouteDiscovery::class)->publicRoute($route, true)) {
                $pages[] = $this->forPage($content->id, $content);
            }
        }

        return $pages;
    }
}
