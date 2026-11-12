<?php

namespace App\Http\Controllers;

use App\Models\Studio;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $studios = Studio::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'address', 'state', 'phones', 'map_url']);

        return view('studios.index', [
            'studios' => $studios,
            'availableStates' => $studios->pluck('state')->unique()->sort()->values(),
        ]);
    }
}
