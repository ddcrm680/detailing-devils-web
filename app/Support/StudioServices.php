<?php

namespace App\Support;

final class StudioServices
{
    /** Stable selection IDs reuse the existing website's centrally maintained content. */
    public static function all(): array
    {
        $images = [
            'ceramic' => 'svc-ceramic.webp',
            'ppf' => 'svc-ppf.webp',
            'correction' => 'svc-correction.webp',
            'interior' => 'svc-interior.webp',
            'window' => 'svc-window.webp',
            'wheel' => 'svc-wheel.webp',
        ];
        $catalogue = [];
        foreach ($images as $id => $image) {
            $service = collect(config('site.services', []))->firstWhere('image', $image);
            if ($service) {
                $catalogue[$id] = [
                    'name' => $service['title'],
                    'description' => $service['text'],
                    'image' => 'img/'.$service['image'],
                    'alt' => $service['alt'],
                    'url' => route('home').'#services',
                ];
            }
        }

        return $catalogue;
    }
}
