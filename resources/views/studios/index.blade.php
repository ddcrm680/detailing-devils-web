@extends('layouts.app')

@section('title', 'Studio Locator | Detailing Devils')
@section('description', 'Find a Detailing Devils studio by name, city, address or state, with contact and directions details.')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/studios.css') }}">
@endpush

@push('js')
    <script src="{{ asset('assets/js/modules/studios.js') }}" defer></script>
@endpush

@section('content')
@php
    $stateCounts = collect($studios)->countBy('state')->sortDesc();
    $popularLocations = collect(['Delhi', 'Noida', 'Gurugram', 'Jaipur', 'Mumbai', 'Dehradun'])
        ->mapWithKeys(fn ($place) => [$place => collect($studios)->filter(
            fn ($studio) => stripos($studio['name'].' '.$studio['address'].' '.$studio['state'].' '.implode(' ', $studio['phones']), $place) !== false
        )->count()])
        ->filter();
@endphp
<div class="studio-page">
    <section class="studio-hero wrap" aria-labelledby="studio-title">
        <div class="studio-hero__inner">
            <span class="lbl">Find a location</span>
            <h1 class="disp" id="studio-title">Our studios</h1>
            <p class="studio-hero__lead">Premium car care. Now closer to you.</p>
            <p class="studio-hero__description">Explore our {{ count($studios) }} studio locations across India and find the one that works for you.</p>
        </div>
    </section>

    <section class="studio-locator wrap" aria-label="Studio locations">
        <div class="studio-locator__inner">
            <form class="studio-filters" id="studio-filters" role="search">
                <div class="studio-filter studio-filter--search">
                    <label for="studio-search">Search studios</label>
                    <div class="studio-filter__control">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input id="studio-search" name="q" type="search" maxlength="100" placeholder="Studio name, city or address">
                    </div>
                </div>
                <div class="studio-filter">
                    <label for="studio-state">State or region</label>
                    <select id="studio-state" name="state">
                        <option value="">All states</option>
                        @foreach ($availableStates as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="studio-search-button">Find studios <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            </form>

            <div class="studio-popular" id="studio-popular" aria-label="Popular locations">
                <span class="studio-popular__label"><i class="bi bi-geo-alt" aria-hidden="true"></i> Popular locations</span>
                <div class="studio-popular__chips">
                    @foreach ($popularLocations as $place => $locationCount)
                        <button type="button" class="studio-chip" data-studio-search="{{ $place }}">{{ $place }} <span>({{ $locationCount }})</span></button>
                    @endforeach
                </div>
            </div>

            <div class="studio-directory" id="studio-directory">
                <div class="studio-directory__main">
                    <div class="studio-directory__heading">
                        <div>
                            <span class="lbl">Explore the network</span>
                            <h2>Studio directory</h2>
                        </div>
                        <p class="studio-count" id="studio-count" role="status">{{ count($studios) }} locations found</p>
                    </div>

                    <div class="studio-empty" id="studio-empty" hidden>
                        <span class="lbl">No locations to show</span>
                        <h3>No studios match your search.</h3>
                        <p>Try another studio name, city, address or state.</p>
                    </div>

                    <div class="studio-results" id="studio-results">
                        @foreach ($studios as $studio)
                            <article class="studio-card" data-search="{{ $studio['name'].' '.$studio['address'].' '.$studio['state'].' '.implode(' ', $studio['phones']) }}" data-state="{{ $studio['state'] }}">
                                <div class="studio-card__index" aria-hidden="true">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                                <div class="studio-card__details">
                                    <span class="studio-card__state">{{ $studio['state'] }}</span>
                                    <h3>{{ $studio['name'] }}</h3>
                                    <address>{{ $studio['address'] }}</address>
                                    <div class="studio-card__contact" aria-label="Studio contact numbers">
                                        @foreach ($studio['phones'] as $phone)
                                            @php($digits = preg_replace('/[^0-9]/', '', $phone))
                                            @if (preg_match('/^91[6-9][0-9]{9}$/', $digits))
                                                <a href="tel:+{{ $digits }}" class="studio-phone"><i class="bi bi-telephone" aria-hidden="true"></i>{{ $phone }}</a>
                                            @else
                                                <span class="studio-phone studio-phone--unavailable">{{ $phone }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <div class="studio-card__actions">
                                    @if (isset($studio['phones'][0]) && preg_match('/^91[6-9][0-9]{9}$/', preg_replace('/[^0-9]/', '', $studio['phones'][0])))
                                        <a href="tel:+{{ preg_replace('/[^0-9]/', '', $studio['phones'][0]) }}" class="studio-action"><i class="bi bi-telephone" aria-hidden="true"></i> Call</a>
                                    @endif
                                    <a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($studio['name'].' '.$studio['address']) }}" class="studio-action studio-action--red" target="_blank" rel="noopener noreferrer">Directions <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                </div>

                <aside class="studio-presence" aria-labelledby="studio-presence-title">
                    <div class="studio-presence__heading">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <div><span class="lbl">Our presence</span><h2 id="studio-presence-title">Across India</h2></div>
                    </div>
                    <div class="studio-presence__art" aria-hidden="true"><span>India</span></div>
                    <div class="studio-presence__list-heading"><span>Studios by state</span><button type="button" id="studio-view-all">View all</button></div>
                    <div class="studio-presence__states" id="studio-presence-states">
                        @foreach ($stateCounts as $stateName => $stateCount)
                            <button type="button" data-studio-state="{{ $stateName }}"><span>{{ $stateName }}</span><span>{{ $stateCount }}</span></button>
                        @endforeach
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
@endsection
