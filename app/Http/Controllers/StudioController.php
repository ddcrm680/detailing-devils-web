<?php

namespace App\Http\Controllers;

use App\Models\ContentUrl;
use App\Models\Studio;
use App\Services\Seo\StudioMetadataProvider;
use App\Services\StudioUrls;
use Giggsey\Locale\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $studios = Studio::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['slug', 'name', 'address', 'state', 'phones', 'map_url']);

        return view('studios.index', [
            'studios' => $studios,
            'availableStates' => $studios->pluck('state')->unique()->sort()->values(),
        ]);
    }

    public function show(string $slug, StudioMetadataProvider $metadata): View|RedirectResponse
    {
        $studio = Studio::query()
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with('images')
            ->first();
        if (! $studio) {
            $owner = ContentUrl::query()->where('path', StudioUrls::path($slug))->where('content_type', ContentUrl::STUDIO)->first();
            $current = $owner ? Studio::query()->whereKey($owner->content_id)->where('status', 'active')->first() : null;
            abort_unless($current && $current->slug !== $slug, 404);

            return redirect()->to(rtrim(config('seo.origin'), '/').route('studios.show', ['slug' => $current->slug], false), 301)
                ->header('Cache-Control', 'no-store, max-age=0');
        }
        $photos = $studio->images->filter(fn ($photo) => $photo->public_path !== null)
            ->sortByDesc('is_cover')->values();
        $studio->setRelation('coverImage', $photos->firstWhere('is_cover', true));

        return view('studios.show', [
            'studio' => $studio,
            'photos' => $photos,
            'country' => Locale::getAllCountriesForLocale('en')[$studio->country_code] ?? null,
            'seoContent' => $metadata->forStudio($studio),
        ]);
    }
}
