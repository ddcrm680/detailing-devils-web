<?php

namespace App\View\Composers;

use App\Services\Seo\SeoResolver;
use Illuminate\View\View;

final class SeoComposer
{
    public function __construct(private SeoResolver $resolver) {}

    public function compose(View $view): void
    {
        $legacy = [];
        foreach (['title', 'description'] as $field) {
            $section = app('view')->getSection($field);
            if (is_string($section) && trim($section) !== '') {
                // Laravel escapes inline @section values; decode once before the shared head escapes them.
                $legacy[$field] = html_entity_decode(trim($section), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $view->with(
            'seo',
            $this->resolver->forRequest(request(), $view->getData()['seoContent'] ?? null, $legacy)
        );
    }
}
