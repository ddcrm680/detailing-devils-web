<?php

namespace App\View\Components;

use App\Services\Seo\SeoMetadata;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Pure presentation: no request resolution, repositories, database or session access. */
final class SeoHead extends Component
{
    public function __construct(public SeoMetadata $metadata) {}

    public function render(): View
    {
        return view('components.seo-head');
    }
}
