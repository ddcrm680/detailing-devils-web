@extends('layouts.app')

@section('title', 'Studio Tour | Detailing Devils')
@section('description', 'Walk through a Detailing Devils studio, explore Prime and FOFO franchise models, and choose your application.')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/studio-tour.css') }}">
@endpush

@section('content')
<div class="tour-page" data-frames-base="{{ asset('frames/studio-tour') }}/">
    <section class="tour-page__film" id="tour" aria-label="Studio tour">
        <div class="tour-page__stage">
            <canvas id="tour-canvas" role="img" aria-label="Walk-through of a Detailing Devils studio: facade, showroom, ceramic coating bay, washing bay, detailing area, PPF bays and the brand wall"></canvas>
            <div class="tour-page__shade" aria-hidden="true"></div>

            <div class="tour-page__hero" id="tour-hero">
                <span class="tour-page__eyebrow">Studio tour <i>/</i> Franchise</span>
                <h1 class="disp">This could be <span class="acc">your</span> studio</h1>
                <p>Scroll to walk the floor. Apply at the end.</p>
            </div>

            <div class="tour-page__caption" data-a="40" data-b="92"><span class="tour-page__number"><b>01</b>Showroom</span><h2 class="disp">Front of <span class="acc">house</span></h2><p>The first thing your customers walk into.</p></div>
            <div class="tour-page__caption" data-a="108" data-b="196"><span class="tour-page__number"><b>02</b>Ceramic coating bay</span><h2 class="disp">Sealed for <span class="acc">coating</span></h2><p>An enclosed glass room, so coatings cure dust-free.</p></div>
            <div class="tour-page__caption" data-a="308" data-b="354"><span class="tour-page__number"><b>03</b>Washing bay</span><h2 class="disp">Washing <span class="acc">bay</span></h2><p>Floor drains, pressure washer and hose reels built in.</p></div>
            <div class="tour-page__caption" data-a="392" data-b="442"><span class="tour-page__number"><b>04</b>Detailing area</span><h2 class="disp">Detailing <span class="acc">area</span></h2><p>Polishing and finishing beside the lit booth.</p></div>
            <div class="tour-page__caption" data-a="500" data-b="550"><span class="tour-page__number"><b>05</b>PPF bays</span><h2 class="disp">PPF <span class="acc">bays</span></h2><p>Lift bays for film installs, curtained rooms alongside.</p></div>

            <div class="tour-page__progress" id="tour-progress" aria-hidden="true"><b id="tour-progress-bar"></b></div>
            <div class="tour-page__hint" id="tour-hint">Scroll to enter<i></i></div>
            <div class="tour-page__black" id="tour-black" aria-hidden="true"></div>
            <div class="tour-page__end" id="tour-end">
                <span class="lbl">End of tour</span>
                <h2 class="disp">Your City<br>Is <span class="acc">Next</span></h2>
                <p>Two ways to open a Detailing Devils studio. Explore the models and choose your application.</p>
                <div class="tour-page__actions"><a class="cta red" href="#models">Explore Models <i aria-hidden="true">↓</i></a><a class="cta" href="{{ route('prime.show') }}">Apply for Prime <i aria-hidden="true">→</i></a></div>
                <div class="tour-page__prices"><span>Prime <b>₹15 L</b></span><span>FOFO <b>₹35 L</b></span></div>
            </div>
            <div class="tour-page__loader" id="tour-loader" aria-hidden="true"></div>
        </div>
    </section>

    <section class="tour-page__models wrap" id="models" aria-labelledby="tour-models-title">
        <div class="tour-page__models-head">
            <div><span class="lbl">Investment models</span><h2 class="disp" id="tour-models-title">Choose your <span class="acc">format</span></h2></div>
            <p>Same brand, same process. Pick the scale that fits your city and your capital.</p>
        </div>
        <div class="tour-page__models-grid">
            <article class="tour-page__tier">
                <span class="tour-page__tier-ghost" aria-hidden="true">01</span>
                <div class="tour-page__tier-kicker"><span class="lbl">Model 01</span><span class="tour-page__tier-tag">Entry</span></div>
                <h3 class="disp"><small>Detailing Devils</small>Prime</h3>
                <p class="tour-page__tier-line">The quickest way into the Devils network.</p>
                <div class="tour-page__tier-price"><div class="tour-page__amount">₹15<span>Lakh</span></div><p>+ 18% GST · <b>₹17.7 lakh</b> all-in</p><a class="cta" href="{{ route('prime.show') }}">Apply for Prime <i aria-hidden="true">→</i></a></div>
            </article>
            <article class="tour-page__tier">
                <span class="tour-page__tier-ghost" aria-hidden="true">02</span>
                <div class="tour-page__tier-kicker"><span class="lbl">Model 02</span><span class="tour-page__tier-tag">FOFO</span></div>
                <h3 class="disp"><small>Detailing Devils</small>FOFO Model</h3>
                <p class="tour-page__tier-line">Franchise owned, franchise operated. You run the studio.</p>
                <div class="tour-page__tier-price"><div class="tour-page__amount">₹35<span>Lakh</span></div><p>+ 18% GST · <b>₹41.3 lakh</b> all-in</p><a class="cta red" href="{{ route('fofo.show') }}">Apply for FOFO <i aria-hidden="true">→</i></a></div>
            </article>
        </div>
        <p class="tour-page__fine">All prices are exclusive of applicable GST at 18%. All-in figures add GST to the base price. Site size, territory and inclusions for each model are shared on the call.</p>
    </section>

    <section class="tour-page__closing" aria-labelledby="tour-closing-title">
        <div class="wrap tour-page__closing-inner">
            <h2 class="disp" id="tour-closing-title">Seen the studio?<br><span class="acc">Claim your city.</span></h2>
            <div class="tour-page__actions"><a class="cta red" href="{{ route('prime.show') }}">Apply for Prime <i aria-hidden="true">→</i></a><a class="cta" href="{{ route('fofo.show') }}">Apply for FOFO <i aria-hidden="true">→</i></a><a class="cta" href="#tour" id="tour-replay">Replay Tour <i aria-hidden="true">↺</i></a></div>
        </div>
    </section>
</div>
@endsection

@push('js')
    <script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js" defer></script>
    <script src="{{ asset('assets/js/modules/studio-tour.js') }}" defer></script>
@endpush
