@extends('admin.layouts.app')

@section('context', 'Metadata preview')

@section('metadata')
    <x-seo-head :metadata="$preview" />
@endsection

@section('content')
    <span class="eyebrow">PRIVATE PREVIEW</span>
    <h1>Preview page metadata</h1>
    <p class="muted">This document renders the actual shared SEO head. Search and social networks may display different snippets. Preview responses are always noindex.</p>
    <section class="search-preview panel">
        <span class="muted url">{{ $page->canonical }}</span>
        <h2>{{ $page->title }}</h2>
        <p>{{ $page->description }}</p>
    </section>
    <div class="two-column">
        <section class="panel">
            <span class="eyebrow">OPEN GRAPH</span>
            <h2>{{ $page->ogTitle ?? $page->title }}</h2>
            <p>{{ $page->ogDescription ?? $page->description }}</p>
        </section>
        <section class="panel">
            <span class="eyebrow">TWITTER / X</span>
            <h2>{{ $page->twitterTitle ?? $page->ogTitle ?? $page->title }}</h2>
            <p>{{ $page->twitterDescription ?? $page->ogDescription ?? $page->description }}</p>
        </section>
    </div>
    @if ($page->image)
        @php
            $imagePath = parse_url($page->image, PHP_URL_PATH);
            $imageSource = str_starts_with($imagePath, '/uploads/images/seo/')
                ? route('admin.seo.images.show', pathinfo($imagePath, PATHINFO_FILENAME))
                : asset(ltrim($imagePath, '/'));
        @endphp
        <img class="sharing-preview" src="{{ $imageSource }}" alt="Selected social-sharing artwork">
    @else
        <p class="notice">No sharing image selected. A text-only card will be used.</p>
    @endif
    <p class="small muted">Planned public robots: {{ $page->robots }} · Sitemap: {{ $page->sitemap && $page->indexable() ? 'included' : 'excluded' }}</p>
    <a class="button secondary" href="{{ route('admin.seo.edit', $page->id) }}">Return to editor</a>
    <details class="panel">
        <summary>Metadata findings</summary>
        <ul class="findings">
        @foreach ($warnings as $warning)
            <li>
                <strong>{{ $warning['page'] }}</strong><span>{{ $warning['message'] }}</span>
            </li>
        @endforeach
    </ul>
    </details>
@endsection
