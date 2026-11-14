@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/studio-detail.css') }}">
@endpush

@section('content')
    @php
        $location = collect([$studio->city, $studio->state, $country])->filter()->unique()->join(', ');
        $primaryPhone = $studio->phones[0] ?? null;
        $primaryDigits = preg_replace('/[^0-9]/', '', $primaryPhone ?? '');
        $primaryCall = preg_replace('/[^+0-9]/', '', $primaryPhone ?? '');
        $cover = $photos->first();
    @endphp
    <div class="studio-detail">
        <div class="studio-detail__wrap wrap">
            <nav class="studio-detail__breadcrumbs" aria-label="Breadcrumb">
                <ol>
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('studios.index') }}">Our studios</a></li>
                    <li aria-current="page">{{ $studio->name }}</li>
                </ol>
            </nav>

            <section class="studio-detail__overview" aria-labelledby="studio-name">
                <div class="studio-detail__media">
                    @if ($cover)
                        <a class="studio-detail__cover" href="{{ asset(ltrim($cover->public_path, '/')) }}" target="_blank" rel="noopener" aria-label="Open studio cover photo">
                            <img src="{{ asset(ltrim($cover->public_path, '/')) }}" alt="{{ $cover->alt_text ?: $studio->name.' studio photo' }}" width="{{ $cover->width }}" height="{{ $cover->height }}" fetchpriority="high">
                        </a>
                    @else
                        <div class="studio-detail__empty">
                            <img src="{{ asset('img/logo.png') }}" alt="" width="1600" height="377">
                            <span class="studio-detail__empty-icon bi bi-geo-alt" aria-hidden="true"></span>
                            <p>Studio photos are not available.</p>
                        </div>
                    @endif
                    @if ($photos->count() > 1)
                        <div class="studio-detail__gallery" aria-label="Studio photos">
                            @foreach ($photos->skip(1) as $photo)
                                <a href="{{ asset(ltrim($photo->public_path, '/')) }}" target="_blank" rel="noopener" aria-label="Open studio photo {{ $loop->iteration + 1 }}">
                                    <img src="{{ asset(ltrim($photo->public_path, '/')) }}" alt="{{ $photo->alt_text ?: $studio->name.' studio photo '.($loop->iteration + 1) }}" width="{{ $photo->width }}" height="{{ $photo->height }}" loading="lazy" decoding="async">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="studio-detail__information">
                    @if ($location !== '')
                        <p class="studio-detail__location"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $location }}</p>
                    @endif
                    <h1 id="studio-name">{{ $studio->name }}</h1>
                    <div class="studio-detail__contact">
                        <div class="studio-detail__address">
                            <h2>Visit the studio</h2>
                            <address>{{ $studio->address }}</address>
                        </div>
                        @if ($studio->phones)
                            <div>
                                <h2>Get in touch</h2>
                                <ul class="studio-detail__phones" aria-label="Studio phone numbers">
                                    @foreach ($studio->phones as $phone)
                                        @php($digits = preg_replace('/[^0-9]/', '', $phone))
                                        <li>
                                            @if (preg_match('/^[0-9]{7,15}$/', $digits))
                                                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $phone) }}"><i class="bi bi-telephone" aria-hidden="true"></i>{{ $phone }}</a>
                                            @else
                                                <span>{{ $phone }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="studio-detail__actions">
                        @if ($primaryPhone && preg_match('/^[0-9]{7,15}$/', $primaryDigits))
                            <a class="studio-detail__button studio-detail__button--red" href="tel:{{ $primaryCall }}"><i class="bi bi-telephone" aria-hidden="true"></i> Call studio</a>
                        @endif
                        <a class="studio-detail__button" href="{{ $studio->directions_url }}" target="_blank" rel="noopener noreferrer">Get directions <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                    <a class="studio-detail__back" href="{{ route('studios.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to all studios</a>
                </div>
            </section>
        </div>
    </div>
@endsection
