<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $studios = File::json(resource_path('data/studios.json'));
        foreach ($studios as &$studio) {
            $studio['directions_url'] = $this->directionsUrl($studio);
        }
        unset($studio);

        return view('studios.index', [
            'studios' => $studios,
            'availableStates' => collect($studios)->pluck('state')->unique()->sort()->values(),
        ]);
    }

    private function directionsUrl(array $studio): string
    {
        $url = is_string($studio['map_url'] ?? null) ? trim($studio['map_url']) : '';
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $googleMaps = in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
            || (in_array($host, ['google.com', 'www.google.com'], true) && preg_match('~^/maps(?:/|$)~', $path))
            || ($host === 'goo.gl' && str_starts_with($path, '/maps/'));

        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && $googleMaps
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && ! str_contains($url, '\\')) {
            return $url;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($studio['name'].' '.$studio['address']);
    }
}
