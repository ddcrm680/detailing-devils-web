<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStudioRequest;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Studio::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Studio::query()->with('coverImage');
        if ($term = $filters['q'] ?? '') {
            $query->where(function ($query) use ($term) {
                foreach (['name', 'address', 'city', 'state', 'phones', 'slug'] as $column) {
                    $query->orWhere($column, 'like', '%'.$term.'%');
                }
            });
        }
        foreach (['state', 'status'] as $field) {
            if ($filters[$field] ?? '') {
                $query->where($field, $filters[$field]);
            }
        }

        return view('admin.studios.index', [
            'studios' => $query->orderBy('sort_order')->orderBy('id')->paginate(15)->withQueryString(),
            'states' => Studio::query()->select('state')->distinct()->orderBy('state')->pluck('state'),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.studios.edit', [
            'studio' => new Studio(['phones' => [''], 'status' => 'draft', 'sort_order' => 0]),
        ]);
    }

    public function store(SaveStudioRequest $request): RedirectResponse
    {
        $studio = Studio::create($request->validated());

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio created. The public locator is unchanged in Phase 1.');
    }

    public function show(Studio $studio): View
    {
        return view('admin.studios.show', ['studio' => $studio->load('images')]);
    }

    public function edit(Studio $studio): View
    {
        return view('admin.studios.edit', ['studio' => $studio->load('images')]);
    }

    public function update(SaveStudioRequest $request, Studio $studio): RedirectResponse
    {
        DB::transaction(function () use ($request, $studio) {
            $studio = Studio::query()->lockForUpdate()->findOrFail($studio->id);
            $studio->update($request->validated());
        });

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio saved. The public locator is unchanged in Phase 1.');
    }
}
