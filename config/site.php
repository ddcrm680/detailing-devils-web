<?php

/*
|--------------------------------------------------------------------------
| Detailing Devils — website content
|--------------------------------------------------------------------------
| Edit the text below to change it on the website. Save the file and refresh
| the page. (On a live server, run "php artisan config:clear" after editing.)
| Images live in public/img and the banner frames in public/frames.
*/

return [

    'brand' => 'Detailing Devils',
    'description' => "Paint correction, ceramic coating and paint protection film from India's largest car detailing network.",

    // Contact details (header, contact section and footer)
    'phone' => '+91 9555 695 695',
    'phone_link' => '+919555695695',          // digits only, used for tap-to-call
    'email' => 'info@detailingdevils.com',      // also receives new enquiries
    'address' => 'B-24, Pushpanjali Enclave, Pitampura, Delhi 110034',

    // Social links (header and footer). Replace '#' with your page links.
    'social' => [
        'Instagram' => '#',
        'Facebook' => '#',
        'YouTube' => '#',
    ],

    // Home page banner
    'hero' => [
        'tagline' => ["We don't just clean cars", 'We protect them', '145+ studios'],
        'line1' => 'For Those Who',
        'line2' => 'Refuse Dull',
        'subline' => "India's car detailing and paint protection studio network",
        'frames' => 240,                              // number of files in public/frames
        'frame_pattern' => 'frames/ezgif-frame-%03d.jpg', // file name pattern (%03d = 001, 002 …)
    ],

    // Numbers row
    'numbers' => [
        ['value' => '145+', 'label' => 'Studios across India'],
        ['value' => '3', 'label' => 'Stages on every car'],
        ['value' => '10H', 'label' => 'Coating hardness'],
        ['value' => '6', 'label' => 'Core services'],
    ],

    // Services (list + image). Images are in public/img
    'services' => [
        ['title' => 'Ceramic Coating', 'text' => 'A hard, glass-like layer bonded to the clear coat. Water beads off, dirt struggles to stick and the gloss stays deep between washes.', 'meta' => 'Up to 10H · 2 to 3 days', 'image' => 'svc-ceramic.webp', 'alt' => 'Detailer applying ceramic coating to black paint'],
        ['title' => 'Paint Protection Film', 'text' => "Clear, self-healing film that takes the stone chips, scratches and bird droppings so your paint doesn't. Gloss, satin and colour options.", 'meta' => 'Front end · full body · 3 to 5 days', 'image' => 'svc-ppf.webp', 'alt' => 'Installer fitting paint protection film to a front wing'],
        ['title' => 'Paint Correction', 'text' => 'Machine polishing to remove swirls, wash marks, oxidation and light scratches. We measure first and remove only what is safe.', 'meta' => '1 to 3 step · 1 to 2 days', 'image' => 'svc-correction.webp', 'alt' => 'Machine polishing black paint to remove swirls'],
        ['title' => 'Interior Detailing', 'text' => 'Steam, extraction and leather care for seats, carpets, roof lining and trim. Odours are treated at the source.', 'meta' => 'Leather · fabric · same day', 'image' => 'svc-interior.webp', 'alt' => 'Steam cleaning a black leather seat'],
        ['title' => 'Window Film', 'text' => 'Heat and UV rejecting films that keep the cabin cooler, protect the interior and stay within legal visibility limits.', 'meta' => 'Heat rejection · same day', 'image' => 'svc-window.webp', 'alt' => 'Installer fitting window film to a black car'],
        ['title' => 'Wheel & Trim', 'text' => 'Wheel faces and barrels deep-cleaned and coated, calipers detailed and exterior trim restored to a rich, even finish.', 'meta' => 'Wheels off · coated · 1 day', 'image' => 'svc-wheel.webp', 'alt' => 'Detailer brushing a black wheel with yellow calipers'],
    ],

    // Franchise steps
    'franchise_steps' => [
        ['title' => 'Apply', 'text' => 'Tell us about yourself and the city you have in mind.'],
        ['title' => 'Site & Setup', 'text' => 'We help choose the location and fit out the studio to brand standard.'],
        ['title' => 'Training', 'text' => 'Your team learns the three-stage process hands-on.'],
        ['title' => 'Launch', 'text' => 'Open with launch marketing and ongoing support from the central team.'],
    ],

    'footer_about' => 'Car detailing, ceramic coating and paint protection film. 145+ studios across India.',

];
