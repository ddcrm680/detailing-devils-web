<?php

// Public URL/indexing policy is deployment-controlled, never taken from request Host.
return [
    'origin' => 'https://www.detailingdevils.com',
    'locale' => 'en',
    'defaults' => [
        'robots' => 'index, follow',
        'og_type' => 'website',
        // No approved social-sharing card yet. Do not substitute an arbitrary photo.
        'image' => null,
    ],
    // Public HTML routes are discovered without dispatching requests. Ambiguous actions
    // can declare ->defaults('_seo', ['public' => true]); internal pages can opt out.
    'discovery' => [
        'exclude' => [
            'admin*',
            'seo-admin*',
            'auth*',
            'login*',
            'logout*',
            'register*',
            'password*',
            'verification*',
            'api*',
            'internal*',
            'deploy*',
            'up',
            'health*',
            'locations*',
            'email*',
            'otp*',
            'sanctum*',
            '_*',
            'telescope*',
            'horizon*',
            'storage*',
            '*.json',
            '*.xml',
            '*.txt',
        ],
    ],
    'templates' => [
        'service' => ['title' => '{title} | {brand}', 'og_type' => 'website'],
        'studio' => ['title' => '{title} | {brand}', 'og_type' => 'website'],
        'franchise' => ['title' => '{title} | {brand}', 'og_type' => 'website'],
        'article' => ['title' => '{title} | {brand}', 'og_type' => 'article'],
        'location' => ['title' => '{title} | {brand}', 'og_type' => 'website'],
    ],
];
