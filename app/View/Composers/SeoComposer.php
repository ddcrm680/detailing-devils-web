<?php

namespace App\View\Composers;

use App\Services\Seo\SeoResolver;
use Illuminate\View\View;

final class SeoComposer
{
    public function __construct(private SeoResolver $resolver) {}

    public function compose(View $view): void
    {
        $view->with(
            'seo',
            $this->resolver->forRequest(request(), $view->getData()['seoContent'] ?? null)
        );
    }
}
