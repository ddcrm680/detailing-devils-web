<?php

namespace App\Services\Seo;

use App\Contracts\Seo\ContentMetadataProvider;
use App\Models\Studio;
use Illuminate\Support\Str;

/** Active Studio CMS content joins the existing SEO inventory and sitemap. */
final class StudioMetadataProvider implements ContentMetadataProvider
{
    public function pages(): iterable
    {
        foreach (Studio::query()->where('status', 'active')->with('coverImage')->orderBy('id')->get() as $studio) {
            yield $this->forStudio($studio);
        }
    }

    public function forStudio(Studio $studio): ContentMetadata
    {
        $urls = new SeoUrl;
        $canonical = $urls->canonical(route('studios.show', ['slug' => $studio->slug], false));
        $location = collect([$studio->city, $studio->state])->filter()->unique()->join(', ');
        $description = 'Find '.$studio->name.($location !== '' ? ' in '.$location : '').'. View the address, phone numbers and directions to this Detailing Devils studio.';
        $image = $studio->coverImage?->public_path;

        return new ContentMetadata(
            id: 'studio.'.$studio->id,
            type: 'studio',
            routeName: 'studios.show',
            routeParameters: ['slug' => $studio->slug],
            title: $studio->name,
            description: Str::limit(preg_replace('/\s+/u', ' ', $description), 165),
            published: $studio->status === 'active',
            sitemap: true,
            modifiedAt: $studio->updated_at?->toDateTimeImmutable(),
            image: $image,
            structuredData: [
                JsonLd::localBusiness(
                    $studio->name,
                    $canonical,
                    address: array_filter([
                        'streetAddress' => $studio->address,
                        'addressLocality' => $studio->city,
                        'addressRegion' => $studio->state,
                        'addressCountry' => $studio->country_code,
                    ], fn ($value) => filled($value)),
                    phones: $studio->phones ?? [],
                    coordinates: $studio->latitude !== null && $studio->longitude !== null
                        ? ['latitude' => (float) $studio->latitude, 'longitude' => (float) $studio->longitude] : null,
                    mapUrl: $studio->directions_url,
                    images: $image ? [$urls->canonical($image)] : [],
                ),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => $urls->canonical('/')],
                    ['name' => 'Studio Locator', 'url' => $urls->canonical(route('studios.index', [], false))],
                    ['name' => $studio->name, 'url' => $canonical],
                ]),
            ],
        );
    }
}
