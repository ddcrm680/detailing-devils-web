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
<div class="studio-page">
    <section class="studio-hero wrap" aria-labelledby="studio-title">
        <div class="studio-hero__inner">
            <span class="lbl">Detailing Devils / Locations</span>
            <h1 class="disp" id="studio-title">Find your <span class="acc">studio.</span></h1>
            <p>Explore our confirmed studio locations and find the one that works for you.</p>
        </div>
    </section>

    <section class="studio-locator wrap" aria-label="Studio locations">
        <div class="studio-locator__inner">
            <div class="studio-locator__heading">
                <div>
                    <span class="lbl">Find a location</span>
                    <h2>Our studios</h2>
                </div>
                <p class="studio-count" id="studio-count" role="status">{{ count($studios) }} locations found</p>
            </div>

            <form class="studio-filters" id="studio-filters" role="search">
                <div class="studio-filter">
                    <label for="studio-search">Search studios</label>
                    <input id="studio-search" name="q" type="search" maxlength="100" placeholder="Studio name, city or address">
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
                <button type="submit" class="studio-search-button">Find studios <span aria-hidden="true">↗</span></button>
            </form>

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
                            <div class="studio-card__contact">
                                <span>Contact</span>
                                @foreach ($studio['phones'] as $phone)
                                    @php($digits = preg_replace('/[^0-9]/', '', $phone))
                                    @if (preg_match('/^91[6-9][0-9]{9}$/', $digits))
                                        <a href="tel:+{{ $digits }}">{{ $phone }}</a>
                                    @else
                                        <span>{{ $phone }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="studio-card__actions">
                            @if (isset($studio['phones'][0]) && preg_match('/^91[6-9][0-9]{9}$/', preg_replace('/[^0-9]/', '', $studio['phones'][0])))
                                <a href="tel:+{{ preg_replace('/[^0-9]/', '', $studio['phones'][0]) }}" class="studio-action">Call <span aria-hidden="true">↗</span></a>
                            @endif
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($studio['name'].' '.$studio['address']) }}" class="studio-action studio-action--red" target="_blank" rel="noopener noreferrer">Directions <span aria-hidden="true">↗</span></a>
                        </div>
                    </article>
                @endforeach
            </div>

            <nav class="studio-pagination" id="studio-pagination" aria-label="Studio result pages" hidden>
                <button type="button" id="studio-previous" disabled>Previous</button>
                <span id="studio-page">Page 1 of {{ (int) ceil(count($studios) / 50) }}</span>
                <button type="button" id="studio-next">Next</button>
            </nav>
        </div>
    </section>
</div>
@endsection
