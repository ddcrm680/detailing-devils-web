@extends('admin.layouts.app')

@include('admin.studios.assets')

@section('title', 'Gallery · '.$studio->name)
@section('context', 'Studio Gallery')

@section('content')
    <div class="studio-admin">
        <div class="page-heading">
            <div>
                <a class="small" href="{{ route('admin.studios.gallery.index') }}">← Studio Gallery</a>
                <h1>{{ $studio->name }}</h1>
                <p class="muted">{{ collect([$studio->city, $studio->state])->filter()->join(', ') }}</p>
            </div>
            <a class="button secondary" href="{{ route('admin.studios.edit', $studio) }}">Edit studio details</a>
        </div>
        @include('admin.studios.gallery')
    </div>
@endsection
