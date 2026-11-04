@extends('admin.layouts.app')

@section('title', 'Publication unavailable')

@section('context', 'SEO availability')

@section('content')
    <section class="panel login-panel">
        <span class="eyebrow">PUBLICATION PAUSED</span>
        <h1>{{ $heading ?? 'Your draft is safe.' }}</h1>
        <p>{{ $message ?? 'Publication could not complete. No automatic retry was made. Ask the website owner to review storage health before trying again.' }}</p>
        <a class="button secondary" href="{{ route('admin.seo.index') }}">Return to inventory</a>
    </section>
@endsection
