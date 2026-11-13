<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveStudioRequest;
use App\Models\Studio;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $gallery = $request->routeIs('admin.studios.gallery.index');
        $query = Studio::query()->with('coverImage')->withCount('images');
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
            'gallery' => $gallery,
        ]);
    }

    public function create(): View
    {
        return view('admin.studios.edit', [
            'studio' => new Studio(['phones' => [''], 'status' => 'draft', 'sort_order' => 0]),
            'existingSlugs' => Studio::query()->pluck('slug'),
        ]);
    }

    public function store(SaveStudioRequest $request): RedirectResponse
    {
        try {
            $studio = Studio::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'This slug was just taken. Please try again or choose another.']);
        }

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio created.');
    }

    public function show(Studio $studio): View
    {
        return view('admin.studios.show', ['studio' => $studio->load('images')]);
    }

    public function edit(Studio $studio): View
    {
        return view('admin.studios.edit', ['studio' => $studio]);
    }

    public function update(SaveStudioRequest $request, Studio $studio): RedirectResponse
    {
        DB::transaction(function () use ($request, $studio) {
            $studio = Studio::query()->lockForUpdate()->findOrFail($studio->id);
            $studio->update($request->validated());
        });

        return redirect()->route('admin.studios.edit', $studio)->with('status', 'Studio saved.');
    }
}
