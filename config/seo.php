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
    // Legacy fallback metadata for existing pages only.
    // New public pages are discovered automatically. Do NOT register new pages here.
    // This array will be retired after the existing metadata publication transition.
    'pages' => [
        'home' => [
            'route' => 'home',
            'title_config' => 'site.seo_title',
            'description_config' => 'site.description',
            'sitemap' => true,
            'priority' => '1.0000',
        ],
        'studios.tour' => [
            'route' => 'studios.tour',
            'title' => 'X-Clusive Studio Tour | Detailing Devils',
            'description' => 'Walk through a Detailing Devils studio, explore Prime and FOFO franchise models, and choose your application.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'studios.index' => [
            'route' => 'studios.index',
            'title' => 'Studio Locator | Detailing Devils',
            'description' => 'Find a Detailing Devils studio by name, city, address or state, with contact and directions details.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'prime.show' => [
            'route' => 'prime.show',
            'title' => 'Franchise Application | Detailing Devils Prime',
            'description' => 'Apply for a Detailing Devils Prime franchise opportunity.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'fofo.show' => [
            'route' => 'fofo.show',
            'title' => 'FOFO Franchise Application | Detailing Devils',
            'description' => 'Explore the Detailing Devils Franchise Owned Franchise Operated model.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'legal.terms' => [
            'route' => 'legal.terms',
            'title' => 'Terms of Use | Detailing Devils',
            'description' => 'Read the Detailing Devils terms of use for this website, including use of the website, ownership, warranties and liability.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'legal.privacy' => [
            'route' => 'legal.privacy',
            'title' => 'Privacy Policy | Detailing Devils',
            'description' => 'Read the Detailing Devils privacy policy on personal information, log data, communications, cookies and security.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'legal.returns' => [
            'route' => 'legal.returns',
            'title' => 'Refund and Returns Policy | Detailing Devils',
            'description' => 'Read the Detailing Devils refund and returns policy, including return eligibility and the return and exchange processes.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'legal.cancellation' => [
            'route' => 'legal.cancellation',
            'title' => 'Cancellation Policy | Detailing Devils',
            'description' => 'Read the Detailing Devils cancellation policy, including cancellation periods, refunds, processed orders and exceptions.',
            'sitemap' => true,
            'priority' => '0.8000',
        ],
        'studios.tour.fofo' => [
            'route' => 'studios.tour',
            'parameters' => ['model' => 'fofo'],
            'title' => 'FOFO Studio Tour | Detailing Devils',
            'description' => 'Walk through a Detailing Devils studio, explore Prime and FOFO franchise models, and choose your application.',
            'sitemap' => true,
            'priority' => '0.6400',
        ],
        'studios.tour.prime' => [
            'route' => 'studios.tour',
            'parameters' => ['model' => 'prime'],
            'title' => 'Prime Studio Tour | Detailing Devils',
            'description' => 'Walk through a Detailing Devils studio, explore Prime and FOFO franchise models, and choose your application.',
            'sitemap' => true,
            'priority' => '0.6400',
        ],
        'xclusive.show' => [
            'route' => 'xclusive.show',
            'title' => 'X-Clusive Franchise Application | Detailing Devils',
            'description' => 'Explore the Detailing Devils X-Clusive franchise model.',
            'sitemap' => true,
            'priority' => '0.6400',
        ],
        'legal.warranty-terms' => [
            'route' => 'legal.warranty-terms',
            'title' => 'Warranty Terms & Conditions | Detailing Devils',
            'description' => 'Read the Detailing Devils warranty terms and conditions, including coverage, maintenance requirements, exclusions and the warranty claim procedure.',
        ],
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
    'intentional_duplicates' => [
        // Same introductory text for each approved Tour model, deliberately preserved.
        'description' => [['studios.tour', 'studios.tour.fofo', 'studios.tour.prime']],
    ],
];
