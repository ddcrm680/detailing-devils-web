@extends('admin.layouts.app')

@if ($studio->images->count() > 1)
    @pushOnce('styles', 'studio-preview-glide-styles')
        <link rel="stylesheet" href="{{ asset('assets/vendor/glide.core.min.css') }}">
    @endPushOnce
    @pushOnce('scripts', 'studio-preview-glide-script')
        <script defer src="{{ asset('assets/vendor/glide.min.js') }}"></script>
    @endPushOnce
@endif

@include('admin.studios.assets')

@section('title', $studio->name)
@section('context', 'Studio Preview')

@section('content')
    <div class="studio-admin">
        <div class="page-heading">
            <h1>{{ $studio->name }} <span class="pill {{ $studio->status === 'active' ? 'green' : 'neutral' }}">{{ ucfirst($studio->status) }}</span></h1>
            <div class="actions">
                <a class="button secondary" href="{{ route('admin.studios.edit', $studio) }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit Studio</a>
                <a class="button" href="{{ route('admin.studios.gallery.show', $studio) }}"><i class="bi bi-images" aria-hidden="true"></i> Manage Gallery</a>
            </div>
        </div>
        @php
            $photos = $studio->images->sortByDesc('is_cover')->values();
            $cover = $photos->first();
        @endphp
        <section class="panel studio-preview studio-workspace">
            <div class="studio-preview-media">
                @if ($cover)
                    <div class="studio-preview-stage glide" data-studio-slider tabindex="0" role="region" aria-label="Studio photos">
                        <div class="glide__track" data-glide-el="track">
                            <ul class="glide__slides">
                                @foreach ($photos as $photo)
                                    <li class="glide__slide" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                                        <img class="studio-preview-hero" src="{{ asset('uploads/studios/'.$photo->path) }}" alt="{{ $photo->alt_text }}" width="{{ $photo->width }}" height="{{ $photo->height }}">
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <button class="studio-slider-arrow previous" type="button" data-photo-step="-1" aria-label="Previous photo" title="Previous photo" hidden><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <button class="studio-slider-arrow next" type="button" data-photo-step="1" aria-label="Next photo" title="Next photo" hidden><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <div class="studio-preview-thumbnails" aria-label="Studio photos">
                        @foreach ($photos as $image)
                            <button type="button" data-studio-photo="{{ asset('uploads/studios/'.$image->path) }}" data-photo-alt="{{ $image->alt_text }}" aria-label="View photo {{ $loop->iteration }}" aria-pressed="{{ $image->id === $cover->id ? 'true' : 'false' }}">
                                <img src="{{ asset('uploads/studios/'.$image->path) }}" alt="" width="64" height="48" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="studio-preview-empty"><i class="bi bi-images" aria-hidden="true"></i><span>No photos added yet.</span></div>
                @endif
            </div>
            <div class="studio-preview-information">
                <h2>Location and contact</h2>
                <dl>
                    <div><dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Address</dt><dd>{{ $studio->address }}</dd></div>
                    <div><dt>Location</dt><dd>{{ collect([$studio->city, $studio->state, $studio->country_code])->filter()->join(', ') }}</dd></div>
                    <div><dt><i class="bi bi-telephone" aria-hidden="true"></i> Phone</dt><dd>@foreach ($studio->phones as $phone)<span>{{ $phone }}{{ $loop->first ? ' - Primary' : '' }}</span>@endforeach</dd></div>
                    <div><dt>Status</dt><dd><span class="pill {{ $studio->status === 'active' ? 'green' : 'neutral' }}">{{ ucfirst($studio->status) }}</span></dd></div>
                    <div><dt>Display Order</dt><dd>{{ $studio->sort_order }}</dd></div>
                    <div><dt>Slug</dt><dd>{{ $studio->slug }}</dd></div>
                    @if ($studio->latitude !== null)
                        <div><dt>Coordinates</dt><dd>{{ $studio->latitude }}, {{ $studio->longitude }}</dd></div>
                    @endif
                </dl>
                <a class="button secondary" href="{{ $studio->directions_url }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-map" aria-hidden="true"></i> View on Google Maps</a>
            </div>
        </section>
    </div>
@endsection
