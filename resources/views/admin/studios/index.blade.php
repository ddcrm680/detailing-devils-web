@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', $gallery ? 'Studio Gallery' : 'Studios')
@section('context', $gallery ? 'Studio Gallery' : 'Studio management')

@section('content')
    <div class="studio-admin">
        @php
            $activeFilterCount = count(array_filter(
                array_intersect_key($filters, array_flip(['state', 'status'])),
                fn ($value) => $value !== null && $value !== '',
            ));
        @endphp
        <div class="page-heading">
            <div>
                <h1>{{ $gallery ? 'Studio Gallery' : 'All Studios' }} <span class="pill neutral">{{ $studios->total() }} Studios</span></h1>
            </div>
            @unless ($gallery)
                <a class="button" href="{{ route('admin.studios.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add New Studio</a>
            @endunless
        </div>
        <section class="panel studio-workspace" aria-label="Studio inventory">
            <form class="inventory-filters studio-filters" action="{{ route($gallery ? 'admin.studios.gallery.index' : 'admin.studios.index') }}" method="get">
                <div class="field search-field">
                    <label for="q">Search studios</label>
                    <input id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Search studios...">
                </div>
                <details class="inventory-filter-controls" data-filter-panel open>
                    <summary>
                        <i class="bi bi-sliders" aria-hidden="true"></i> Filters
                        @if ($activeFilterCount)
                            <span class="pill amber">{{ $activeFilterCount }} active</span>
                        @endif
                    </summary>
                    <div class="filter-fields">
                        <div class="field">
                            <label for="state">State</label>
                            <select id="state" name="state">
                                <option value="">All states</option>
                                @foreach ($states as $state)
                                    <option @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All statuses</option>
                                @foreach (\App\Models\Studio::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </details>
                <div class="filter-actions">
                    <button class="button" type="submit">Search</button>
                    <a href="{{ route($gallery ? 'admin.studios.gallery.index' : 'admin.studios.index') }}" class="button secondary">Reset</a>
                </div>
            </form>
    <div class="studio-list-head" aria-hidden="true"><span>Studio</span><span>Location</span><span>Photos</span><span>Status</span><span>Order</span><span>Actions</span></div>
            <div class="studio-list">
                @forelse ($studios as $studio)
                    <article class="studio-list-row">
                        <div class="studio-thumbnail">
                            @if ($studio->coverImage)
                                <img src="{{ asset('uploads/studios/'.$studio->coverImage->path) }}" alt="{{ $studio->coverImage->alt_text }}" width="48" height="48" loading="lazy">
                            @else
                                <i class="bi bi-image" aria-label="No cover image"></i>
                            @endif
                        </div>
                        <div class="studio-list-location">
                            <h2><a href="{{ route($gallery ? 'admin.studios.gallery.show' : 'admin.studios.edit', $studio) }}">{{ $studio->name }}</a></h2>

                            @unless ($gallery)
                                <p class="small" title="{{ $studio->address }}">{{ $studio->address }}</p>
                            @endunless
                        </div>
                        <div class="studio-list-place small">{{ collect([$studio->city, $studio->state])->filter()->join(', ') }}</div>
                        <div class="studio-list-contacts small"><i class="bi bi-images" aria-hidden="true"></i> {{ $studio->images_count }}<span class="studio-mobile-only"> photos</span></div>
                        <div class="studio-list-status"><span class="pill {{ match ($studio->status) { 'active' => 'green', 'draft' => 'amber', default => 'neutral' } }}">{{ ucfirst($studio->status) }}</span></div>
                        <div class="studio-list-order small"><span class="studio-mobile-only">Order </span>{{ $studio->sort_order }}</div>
                        <div class="actions studio-list-actions">
                            @if ($gallery)
                                <a class="button secondary" href="{{ route('admin.studios.gallery.show', $studio) }}">Manage Gallery<span class="sr-only"> for {{ $studio->name }}</span></a>
                            @else
                                <a class="edit-button" href="{{ route('admin.studios.show', $studio) }}" aria-label="View {{ $studio->name }}" title="View studio"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                <a class="edit-button" href="{{ route('admin.studios.edit', $studio) }}" aria-label="Edit {{ $studio->name }}" title="Edit studio"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="notice">No studios found.</p>
                @endforelse
            </div>
            <nav class="pagination-bar" aria-label="Studio pages">
                <span class="muted small">Page {{ $studios->currentPage() }} of {{ $studios->lastPage() }}</span>
                <div class="actions">
                    @if ($studios->previousPageUrl())
                        <a class="button secondary" href="{{ $studios->previousPageUrl() }}">Previous</a>
                    @endif
                    @if ($studios->nextPageUrl())
                        <a class="button secondary" href="{{ $studios->nextPageUrl() }}">Next</a>
                    @endif
                </div>
            </nav>
        </section>
    </div>
@endsection
