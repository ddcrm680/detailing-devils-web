<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(): View
    {
        $studios = File::json(resource_path('data/studios.json'));

        return view('studios.index', [
            'studios' => $studios,
            'availableStates' => collect($studios)->pluck('state')->unique()->sort()->values(),
        ]);
    }
}
