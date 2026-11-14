<?php

namespace App\Http\Controllers;

use App\Models\Studio;
use App\Services\Seo\StudioMetadataProvider;
use Giggsey\Locale\Locale;
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

    public function show(string $slug, StudioMetadataProvider $metadata): View
    {
        $studio = Studio::query()
            ->where('status', 'active')
            ->where('slug', $slug)
            ->with('images')
            ->firstOrFail();
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
