@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', $studio->name)
@section('context', 'View studio')

@section('content')
    <div class="page-heading">
        <div><h1>{{ $studio->name }}</h1><p class="muted">Private CMS preview · no public studio detail URL exists yet.</p></div>
        <div class="actions">
            <a class="button secondary" href="{{ route('admin.studios.index') }}">All studios</a>
            <a class="button" href="{{ route('admin.studios.edit', $studio) }}">Edit studio</a>
        </div>
    </div>
    <section class="panel form-stack studio-workspace">
        <h2>Location and contact</h2>
        <p>{{ $studio->address }}</p>
        <p>{{ collect([$studio->city, $studio->state, $studio->country_code])->filter()->join(', ') }}</p>
        @foreach ($studio->phones as $phone)
            <p>{{ $phone }}{{ $loop->first ? ' · Primary' : '' }}</p>
        @endforeach
        <p><span class="pill">{{ ucfirst($studio->status) }}</span> · Order {{ $studio->sort_order }} · Slug: {{ $studio->slug }}</p>
        @if ($studio->map_url)
            <p><a href="{{ $studio->map_url }}" target="_blank" rel="noopener noreferrer">Open Google Maps ↗</a></p>
        @endif
        @if ($studio->latitude !== null)
            <p class="muted small">Coordinates: {{ $studio->latitude }}, {{ $studio->longitude }}</p>
        @endif
        <div class="studio-gallery">
            @foreach ($studio->images as $image)
                <figure class="studio-gallery-card">
                    <img src="{{ asset('uploads/studios/'.$image->path) }}" alt="{{ $image->alt_text }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="lazy">
                    <figcaption>{{ $image->alt_text }}{{ $image->is_cover ? ' · Cover' : '' }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endsection
