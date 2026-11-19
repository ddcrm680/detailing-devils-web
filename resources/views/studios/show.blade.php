@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/vendor/glide.core.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/studio-detail.css') }}">
@endpush

@push('js')
    @if ($photos->count() > 1)
        <script src="{{ asset('assets/vendor/glide.min.js') }}" defer></script>
    @endif
    <script src="{{ asset('assets/js/modules/studio-detail.js') }}" defer></script>
@endpush

@section('content')
    @php
        $pageUrl = route('studios.show', ['slug' => $studio->slug]);
        $location = collect([$studio->city, $studio->state, $country])->filter()->unique()->join(', ');
        $phones = collect(is_array($studio->phones) ? $studio->phones : [])
            ->filter(fn ($phone) => is_string($phone) && preg_match('/^[0-9]{7,15}$/', preg_replace('/[^0-9]/', '', $phone)))
            ->map(fn ($phone) => ['number' => $phone, 'tel' => '+'.preg_replace('/[^0-9]/', '', $phone)])
            ->values();
        $primaryCall = $phones->first()['tel'] ?? null;
        $cover = $photos->firstWhere('is_cover', true);
        $desktopPhotos = ($cover && $photos->count() > 1 ? $photos->skip(1) : $photos)->take(4)->values();
        $selectedServices = collect(\App\Support\StudioServices::all())->only(is_array($studio->services) ? $studio->services : []);
        $savedWeek = is_array($studio->opening_hours) ? $studio->opening_hours : [];
        $week = collect(\App\Models\Studio::WEEKDAYS)->map(function ($label, $day) use ($savedWeek) {
            $row = is_array($savedWeek[$day] ?? null) ? $savedWeek[$day] : [];
            $closed = in_array($row['closed'] ?? false, [true, 1, '1'], true);
            $opens = $row['opens'] ?? null;
            $closes = $row['closes'] ?? null;
            $valid = is_string($opens)
                && is_string($closes)
                && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $opens)
                && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $closes)
                && $opens < $closes;
            $text = $closed ? 'Closed' : ($valid ? \Carbon\Carbon::createFromFormat('!H:i', $opens)->format('g:i a').' - '.\Carbon\Carbon::createFromFormat('!H:i', $closes)->format('g:i a') : 'Not provided');

            return compact('label', 'closed', 'valid', 'text');
        });
        $hasHours = $week->contains(fn ($day) => $day['closed'] || $day['valid']);
        $headingBrand = str_starts_with($studio->name, 'Detailing Devils') ? 'Detailing Devils' : '';
        $headingTail = $headingBrand !== '' ? substr($studio->name, strlen($headingBrand)) : '';
        $photoData = $photos->map(fn ($photo) => ['url' => asset(ltrim($photo->public_path, '/')), 'alt' => $photo->alt_text ?: $studio->name.' studio photo', 'width' => $photo->width, 'height' => $photo->height])->values();
    @endphp
    <div class="studio-detail" data-studio-detail>

        {{-- Studio-specific cover and contact actions. --}}
        <section class="hero {{ $cover ? '' : 'hero-without-photo' }}" aria-labelledby="studio-name">
            @if ($cover)
                <div class="hero-image"><img src="{{ asset(ltrim($cover->public_path, '/')) }}" alt="{{ $cover->alt_text ?: $studio->name.' studio photo' }}" width="{{ $cover->width }}" height="{{ $cover->height }}" fetchpriority="high"></div>
            @else
                <div class="hero-fallback"><img src="{{ asset('img/logo.png') }}" alt="" width="1600" height="377"><span class="sr-only">Studio photos are not available.</span></div>
            @endif
            <div class="sd-container hero-content">
                <nav class="breadcrumbs micro" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('studios.index') }}">Studios</a><span>/</span><span aria-current="page">{{ $studio->name }}</span>
                </nav>
                <div class="hero-copy">
                    <p class="eyebrow"><span class="red-square" aria-hidden="true"></span><span>{{ $location }}</span></p>
                    <h1 id="studio-name">@if ($headingTail !== '')<span class="hero-brand">{{ $headingBrand }}</span><span class="hero-city">{{ $headingTail }}</span>@else{{ $studio->name }}@endif</h1>
                    <p class="hero-description">Exceptional care.<br>A little closer to home.</p>
                    <div class="button-row hero-actions">
                        @if ($primaryCall)<a class="button button-red studio-detail__button--red" href="tel:{{ $primaryCall }}"><i class="icon bi bi-telephone" aria-hidden="true"></i>Call Studio</a>@endif
                        <a class="button button-outline" href="{{ $studio->directions_url }}" target="_blank" rel="noopener noreferrer" data-directions>Get Directions <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                </div>
                @if ($photos->isNotEmpty())
                    <button class="hero-gallery-link" type="button" data-open-gallery><span class="gallery-count micro">{{ $photos->count() }} {{ $photos->count() === 1 ? 'photo' : 'photos' }}</span><span>View Gallery</span><i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></button>
                @endif
                <span class="hero-coordinate micro" aria-hidden="true">Built around the details.</span>
            </div>
        </section>

        <div class="contact-strip">
            <div class="sd-container contact-grid contact-grid--{{ 1 + (int) $phones->isNotEmpty() + (int) $hasHours }}">
                <div class="contact-item"><i class="icon bi bi-geo-alt" aria-hidden="true"></i><div><span class="micro">Find your studio</span><address>{{ $studio->address }}</address></div></div>
                @if ($phones->isNotEmpty())
                    <div class="contact-item"><i class="icon bi bi-telephone" aria-hidden="true"></i><div><span class="micro">Let's talk</span><div class="phone-list">
                        @foreach ($phones as $phone)
                            <a href="tel:{{ $phone['tel'] }}">{{ $phone['number'] }}</a>
                        @endforeach
                    </div></div></div>
                @endif
                @if ($hasHours)<div class="contact-item"><i class="icon bi bi-clock" aria-hidden="true"></i><div><span class="micro">Plan ahead</span><a href="{{ $pageUrl }}#opening-hours">See opening hours <span class="inline-arrow" aria-hidden="true">&#8599;</span></a></div></div>@endif
            </div>
        </div>

        <section class="intro sd-container section" aria-labelledby="intro-title">
            <div class="section-side">
                <p class="eyebrow"><span class="section-number">01 /</span> Your local studio</p>
                <h2 id="intro-title">For the love <br>of your drive<span class="accent">.</span></h2>
            </div>
            <div class="intro-copy">
                <p>Welcome to <strong>{{ $studio->name }}</strong>@if ($studio->city) in {{ $studio->city }}@endif. Get in touch with the team to discuss care for your vehicle and plan your visit.</p>
                <p class="muted">From the everyday commute to the Sunday drive, let's find the right care for your car or bike.</p>
                <a class="text-link" href="{{ $pageUrl }}#services">Explore services at this studio <i class="icon bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </section>

        @if ($photos->isNotEmpty())
            <section class="gallery-section sd-container section" id="gallery" aria-labelledby="gallery-title">
                <div class="section-heading"><div><p class="eyebrow">The space. The craft.</p><h2 id="gallery-title">Take a closer look<span class="accent">.</span></h2></div><button class="text-link" type="button" data-open-gallery>View {{ $photos->count() === 1 ? 'photo' : 'all '.$photos->count().' photos' }} <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></button></div>
                <div class="gallery-grid" data-count="{{ $desktopPhotos->count() }}">
                    @foreach ($desktopPhotos as $photo)
                        @include('studios.partials.photo', ['photoIndex' => $photos->search(fn ($item) => $item->id === $photo->id)])
                    @endforeach
                </div>
                <div class="mobile-gallery glide" role="region" aria-label="Studio photo gallery" aria-roledescription="carousel" data-mobile-gallery>
                    <div class="glide__track" data-glide-el="track"><ul class="glide__slides" data-mobile-slides>
                        @foreach ($photos as $photo)<li class="glide__slide" aria-label="Photo {{ $loop->iteration }} of {{ $photos->count() }}">@include('studios.partials.photo', ['photoIndex' => $loop->index])</li>@endforeach
                    </ul></div>
                    @if ($photos->count() > 1)
                        <div class="gallery-controls"><span class="micro" data-gallery-position aria-live="polite">1 / {{ $photos->count() }}</span><div class="button-row"><button class="icon-button gallery-previous" type="button" aria-label="Previous gallery photo" data-gallery-previous><i class="icon bi bi-arrow-right" aria-hidden="true"></i></button><button class="icon-button" type="button" aria-label="Next gallery photo" data-gallery-next><i class="icon bi bi-arrow-right" aria-hidden="true"></i></button></div></div>
                    @endif
                </div>
            </section>
        @endif

        {{-- Shared catalogue, filtered by this studio's saved selections. --}}
        <section class="services-section section" id="services" aria-labelledby="services-title">
            <div class="sd-container">
                <div class="section-heading">
                    <div><p class="eyebrow"><span class="section-number">03 /</span> Our services</p><h2 id="services-title">Care with a purpose<span class="accent">.</span></h2></div>
                    @if ($selectedServices->isNotEmpty())<p class="section-caption">Restore the finish. Protect the feeling.<br>Explore the services selected for this studio.</p>@endif
                </div>
                @if ($selectedServices->isNotEmpty())
                    <div class="service-grid">
                        @foreach ($selectedServices as $service)
                            <article class="service-card"><div class="service-image {{ is_file(public_path($service['image'])) ? '' : 'service-image--fallback' }}">
                                @if (is_file(public_path($service['image'])))<img src="{{ asset($service['image']) }}" alt="{{ $service['alt'] }}" data-service-image data-fallback="{{ asset('img/logo.png') }}" width="1200" height="912" loading="lazy">@else<img src="{{ asset('img/logo.png') }}" alt="Detailing Devils" width="1600" height="377" loading="lazy">@endif
                                <span class="service-number micro">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </div><div class="service-copy"><h3>{{ $service['name'] }}</h3><p>{{ $service['description'] }}</p></div></article>
                        @endforeach
                    </div>
                @endif
                <div class="services-actions"><a class="button button-outline" href="{{ route('home') }}#services">Explore All Services <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
            </div>
        </section>

        <section class="visit-section sd-container section" id="visit" aria-labelledby="visit-title">
            <div class="section-heading">
                <div><p class="eyebrow"><span class="section-number">04 /</span> Plan your visit</p><h2 id="visit-title">Good care starts here<span class="accent">.</span></h2></div>
                <a class="text-link" href="{{ $studio->directions_url }}" target="_blank" rel="noopener noreferrer" data-directions>Get Directions <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="visit-grid {{ $hasHours ? '' : 'visit-grid--no-hours' }}">
                <div class="location-card">
                    <!-- Useful no-embed fallback. This illustration is not a geographic map. -->
                    <div class="map-fallback">
                        <svg class="map-roads" viewBox="0 0 800 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                            <g class="map-blocks"><path d="M0 0h165v105H0zm195 0h155v105H195zm185 0h170v105H380zm200 0h220v105H580zM0 140h165v105H0zm195 0h155v105H195zm185 0h170v105H380zm200 0h220v105H580zM0 280h165v120H0zm195 0h155v120H195zm185 0h170v120H380zm200 0h220v120H580z"/></g>
                            <path class="map-route" d="M-30 337H280V123h310V-20"/>
                            <path class="map-road" d="m-20 10 870 440M-20 40l870 440"/>
                        </svg>
                        <span class="map-label micro">Illustrative location · {{ $studio->city ?: $studio->name }}</span>
                        <div class="map-pin" aria-hidden="true"><i class="icon bi bi-geo-alt" aria-hidden="true"></i></div>
                        <div class="map-message"><span class="micro">Map preview unavailable</span><p>We'll point you<br>in the right direction.</p><a class="button button-light" href="{{ $studio->directions_url }}" target="_blank" rel="noopener noreferrer" data-directions>Open Google Maps <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
                    </div>
                    <div class="location-address"><i class="icon bi bi-geo-alt" aria-hidden="true"></i><div><h3>{{ $studio->name }}</h3><address>{{ $studio->address }}</address></div></div>
                </div>
                @if ($hasHours)
                    <section class="hours-card" id="opening-hours" aria-labelledby="hours-title">
                        <div class="hours-heading"><h3 id="hours-title">Opening hours</h3><i class="icon bi bi-clock" aria-hidden="true"></i></div>
                        <ul class="hours-list">
                            @foreach ($week as $day)
                                <li @class(['is-closed' => $day['closed']])><span class="hours-day">{{ $day['label'] }}</span><span class="hours-time">{{ $day['text'] }}</span></li>
                            @endforeach
                        </ul>
                        @if ($primaryCall)
                            <div class="hours-contact"><a href="tel:{{ $primaryCall }}">Call before you visit <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
                        @endif
                    </section>
                @endif
            </div>
        </section>

        {{-- Shared brand content. --}}
        <section class="about-section section" id="about" aria-labelledby="about-title">
            <div class="sd-container about-grid">
                <div class="about-image">
                    <img src="{{ asset('img/inspect.webp') }}" alt="A detailer carefully inspecting a vehicle's paintwork" width="1408" height="768" loading="lazy">
                    <span class="micro">Inspect. Refine. Protect.</span>
                </div>
                <div class="about-copy">
                    <p class="eyebrow"><span class="section-number">05 /</span> About Detailing Devils</p>
                    <h2 id="about-title">It's all in<br>the <span class="accent">details.</span></h2>
                    <p>We see the early starts, the long drives and the pride you take in your vehicle.</p>
                    <p class="muted">From careful paint correction to ceramic coatings and paint protection film, Detailing Devils brings a thoughtful approach to car and bike care. It begins with understanding your vehicle. It ends with the details.</p>
                    <a class="text-link" href="{{ route('home') }}#approach">Meet Detailing Devils <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>

        <section class="why-section sd-container section" aria-labelledby="why-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow"><span class="section-number">06 /</span> Why choose Detailing Devils</p>
                    <h2 id="why-title">A different kind of attention<span class="accent">.</span></h2>
                </div>
            </div>
            <div class="why-grid">
                <article class="why-card">
                    <i class="icon bi bi-bounding-box-circles" aria-hidden="true"></i>
                    <span class="micro">01</span>
                    <h3>Careful from the start.</h3>
                    <p>We look closely at the finish before choosing the right approach for your vehicle.</p>
                </article>
                <article class="why-card">
                    <i class="icon bi bi-shield-check" aria-hidden="true"></i>
                    <span class="micro">02</span>
                    <h3>Protection that fits.</h3>
                    <p>Considered coating and film options, guided by how you drive and what you need.</p>
                </article>
                <article class="why-card">
                    <i class="icon bi bi-chat-dots" aria-hidden="true"></i>
                    <span class="micro">03</span>
                    <h3>People you can talk to.</h3>
                    <p>A local team to discuss the details, answer your questions and help you plan.</p>
                </article>
            </div>
        </section>

        <section class="final-cta" id="enquire" aria-labelledby="enquire-title">
            <div class="sd-container cta-grid">
                <div>
                    <p class="eyebrow">Your next great drive starts here.</p>
                    <h2 id="enquire-title">Your car.<br>Our obsession<span>.</span></h2>
                </div>
                <div class="cta-contact">
                    <p>Let's find the right care for your vehicle.</p>
                    <p class="cta-studio">{{ $studio->name }}</p>
                    @if ($phones->isNotEmpty())
                        <div class="cta-phones">
                            @foreach ($phones as $phone)
                                <a class="cta-phone" href="tel:{{ $phone['tel'] }}">{{ $phone['number'] }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="button-row cta-actions">
                        <a class="button button-on-red" href="{{ route('home') }}#contact">Send Enquiry <i class="icon bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </section>

        @if ($photos->isNotEmpty())
            <dialog class="photo-viewer" aria-labelledby="photo-viewer-title">
                <div class="viewer-header">
                    <h2 id="photo-viewer-title">Inside {{ $studio->name }}</h2>
                    <button class="icon-button" type="button" data-close-viewer aria-label="Close photo viewer"><i class="icon bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="viewer-stage">
                    <button class="icon-button viewer-previous" type="button" @if ($photos->count() < 2) hidden @endif data-previous aria-label="Previous photo"><i class="icon bi bi-arrow-right" aria-hidden="true"></i></button>
                    <img src="{{ asset(ltrim($photos->first()->public_path, '/')) }}" alt="{{ $photos->first()->alt_text ?: $studio->name }}" width="{{ $photos->first()->width }}" height="{{ $photos->first()->height }}" data-viewer-image>
                    <button class="icon-button viewer-next" type="button" @if ($photos->count() < 2) hidden @endif data-next aria-label="Next photo"><i class="icon bi bi-arrow-right" aria-hidden="true"></i></button>
                </div>
                <div class="viewer-footer">
                    <p data-viewer-caption></p>
                    <span class="micro" data-viewer-count aria-live="polite"></span>
                </div>
            </dialog>

            <script type="application/json" data-studio-photos>@json($photoData)</script>
        @endif
    </div>
@endsection
