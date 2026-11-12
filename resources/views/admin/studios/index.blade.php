@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', 'Studios')
@section('context', 'Studio management')

@section('content')
    <div class="page-heading">
        <div>
            <h1>Studios</h1>
            <p class="muted">Manage locations and galleries. The public locator still uses its approved JSON list.</p>
        </div>
        <a class="button" href="{{ route('admin.studios.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add New Studio</a>
    </div>
    <section class="panel studio-workspace" aria-label="Studio inventory">
        <form class="studio-filters" action="{{ route('admin.studios.index') }}" method="get">
            <div class="field">
                <label for="q">Search studios</label>
                <input id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Name, location or phone">
            </div>
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
            <div class="actions">
                <button class="button" type="submit">Search</button>
                <a href="{{ route('admin.studios.index') }}" class="button secondary">Reset</a>
            </div>
        </form>
        <p class="muted small">{{ $studios->total() }} matching studios · ordered by display order, then ID</p>
        <div class="studio-list">
            @forelse ($studios as $studio)
                <article class="studio-list-row">
                    <div class="studio-thumbnail">
                        @if ($studio->coverImage)
                            <img src="{{ asset('uploads/studios/'.$studio->coverImage->path) }}" alt="{{ $studio->coverImage->alt_text }}" width="96" height="72" loading="lazy">
                        @else
                            <i class="bi bi-image" aria-label="No cover image"></i>
                        @endif
                    </div>
                    <div class="studio-list-location">
                        <h2><a href="{{ route('admin.studios.edit', $studio) }}">{{ $studio->name }}</a></h2>
                        <p class="muted small">{{ collect([$studio->city, $studio->state])->filter()->join(', ') }}</p>
                        <p class="small">{{ $studio->address }}</p>
                    </div>
                    <div class="small">
                        @foreach ($studio->phones as $phone)
                            <div>{{ $phone }}</div>
                        @endforeach
                    </div>
                    <div>
                        <span class="pill {{ $studio->status === 'active' ? 'green' : 'amber' }}">{{ ucfirst($studio->status) }}</span>
                        <div class="muted small">Order {{ $studio->sort_order }}</div>
                    </div>
                    <div class="actions">
                        <a class="button secondary small-button" href="{{ route('admin.studios.show', $studio) }}" aria-label="View {{ $studio->name }}">View</a>
                        <a class="button small-button" href="{{ route('admin.studios.edit', $studio) }}" aria-label="Edit {{ $studio->name }}">Edit</a>
                    </div>
                </article>
            @empty
                <p class="notice">No studios match these filters. Add a studio or explicitly import the approved locator list.</p>
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
@endsection
