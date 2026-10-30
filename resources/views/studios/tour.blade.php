@extends('layouts.app')

@php
    $requestedModel = request()->query('model');
    $model = is_string($requestedModel) && in_array($requestedModel, ['xclusive', 'fofo', 'prime'], true)
        ? $requestedModel : 'xclusive';
    $modelNames = ['xclusive' => 'X-Clusive', 'fofo' => 'FOFO', 'prime' => 'Prime'];
    $modelName = $modelNames[$model];
    $isXclusive = $model === 'xclusive';
    $xclusiveApplicationUrl = route('xclusive.show', [], false);
    $applicationUrl = $isXclusive ? $xclusiveApplicationUrl : route($model.'.show');
    $otherTourPrices = ['prime' => '15 Lakh', 'fofo' => '35 Lakh', 'xclusive' => '1.5 Crore'];
    $fofoCaptions = [
        [38, 72, 'Foam & wash bay', 'Foam &', 'wash', 'Pre-wash and decontamination in its own glass bay.'],
        [125, 165, 'Dragon PPF bay', 'Dragon', 'PPF', 'A sealed bay for paint protection film.'],
        [215, 292, 'Main detailing bay', 'Main', 'bay', 'Two lanes under full hexagon lighting.'],
        [360, 422, 'Main hall', 'The', 'hall', 'Space to correct, coat and hand over.'],
        [448, 490, 'Office', 'Office', 'cabin', 'Bookings and handovers in a glass cabin.'],
        [540, 584, 'Customer lounge', 'Customer', 'lounge', 'Customers wait in comfort, cars in view.'],
    ];
@endphp

@section('title', $modelName.' Studio Tour | Detailing Devils')
@section('description', 'Walk through a Detailing Devils studio, explore Prime and FOFO franchise models, and choose your application.')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/studio-tour.css') }}">
@endpush

@section('content')
<div class="tour-page" data-model="{{ $model }}" data-frame-count="{{ $isXclusive ? 660 : 800 }}" data-frames-base="{{ asset($isXclusive ? 'frames/studio-tour' : 'frames/fofo-tour') }}/">
    <nav class="tour-page__switcher" aria-label="Choose a studio tour">
        @foreach ($modelNames as $key => $name)
            <a href="{{ route('studios.tour', ['model' => $key]) }}" @if ($model === $key) aria-current="page" @endif>{{ $name }}</a>
        @endforeach
    </nav>
    <section class="tour-page__film" id="tour" aria-label="Studio tour">
        <div class="tour-page__stage">
            <canvas id="tour-canvas" role="img" aria-label="{{ $isXclusive ? 'Walk-through of a Detailing Devils X-Clusive studio: facade, showroom, ceramic coating bay, washing bay, detailing area, PPF bays and the brand wall' : $modelName.' studio tour: wash bay, PPF bay, detailing bay, hall, office and customer lounge' }}"></canvas>
            <div class="tour-page__shade" aria-hidden="true"></div>

            <div class="tour-page__hero" id="tour-hero">
                <span class="tour-page__eyebrow">{{ $modelName }} studio tour <i>/</i> Franchise</span>
                @if ($isXclusive)
                    <h1 class="disp">This could be <span class="acc">your</span> studio</h1>
                    <p>Scroll to walk the floor. Explore the models at the end.</p>
                @elseif ($model === 'fofo')
                    <h1 class="disp">Own it. <span class="acc">Run</span> it.</h1>
                    <p>Franchise owned, franchise operated. Scroll to walk the floor.</p>
                @else
                    <h1 class="disp">Start <span class="acc">your</span> studio</h1>
                    <p>The quickest way into the Devils network.</p>
                    <p class="tour-page__preview">Illustrative preview: FOFO footage, not a Prime studio specification.</p>
                @endif
            </div>

            @if ($isXclusive)
                <div class="tour-page__caption" data-a="40" data-b="92"><span class="tour-page__number"><b>01</b>Showroom</span><h2 class="disp">Front of <span class="acc">house</span></h2><p>The first thing your customers walk into.</p></div>
                <div class="tour-page__caption" data-a="108" data-b="196"><span class="tour-page__number"><b>02</b>Ceramic coating bay</span><h2 class="disp">Sealed for <span class="acc">coating</span></h2><p>An enclosed glass room, so coatings cure dust-free.</p></div>
                <div class="tour-page__caption" data-a="308" data-b="354"><span class="tour-page__number"><b>03</b>Washing bay</span><h2 class="disp">Washing <span class="acc">bay</span></h2><p>Floor drains, pressure washer and hose reels built in.</p></div>
                <div class="tour-page__caption" data-a="392" data-b="442"><span class="tour-page__number"><b>04</b>Detailing area</span><h2 class="disp">Detailing <span class="acc">area</span></h2><p>Polishing and finishing beside the lit booth.</p></div>
                <div class="tour-page__caption" data-a="500" data-b="550"><span class="tour-page__number"><b>05</b>PPF bays</span><h2 class="disp">PPF <span class="acc">bays</span></h2><p>Lift bays for film installs, curtained rooms alongside.</p></div>
            @else
                @foreach ($fofoCaptions as $caption)
                        <div class="tour-page__caption" data-a="{{ $caption[0] }}" data-b="{{ $caption[1] }}"><span class="tour-page__number"><b>{{ sprintf('%02d', $loop->iteration) }}</b>{{ $caption[2] }}</span><h2 class="disp">{{ $caption[3] }} <span class="acc">{{ $caption[4] }}</span></h2><p>{{ $caption[5] }}</p></div>
                @endforeach
            @endif

            <div class="tour-page__progress" id="tour-progress" aria-hidden="true"><b id="tour-progress-bar"></b></div>
            <div class="tour-page__hint" id="tour-hint">Scroll to enter<i></i></div>
            <div class="tour-page__black" id="tour-black" aria-hidden="true"></div>
            <div class="tour-page__end" id="tour-end" data-lenis-prevent>
                <span class="lbl">End of {{ $modelName }} tour</span>
                <h2 class="disp">Your Studio<br>Is <span class="acc">Next</span></h2>
                <p>{{ $isXclusive ? 'Your X-Clusive studio journey starts here. Explore the model and apply.' : ($model === 'prime' ? 'Prime illustrative tour complete: FOFO footage, not a Prime studio specification.' : 'Your FOFO studio journey starts here. Explore the model and apply.') }}</p>
                <div class="tour-page__actions">
                    <a class="cta red" href="#models">Explore Models <i aria-hidden="true">&darr;</i></a>
                    <a class="cta" href="{{ $applicationUrl }}">Apply for {{ $modelName }} <i aria-hidden="true">&rarr;</i></a>
                </div>
                <nav class="tour-page__other-tours" aria-label="Watch another studio tour">
                    <h3>Watch another studio tour</h3>
                    <div class="tour-page__other-grid">
                        @foreach ($otherTourPrices as $otherModel => $price)
                            @continue($otherModel === $model)
                            <a class="tour-page__other-card" href="{{ route('studios.tour', ['model' => $otherModel]) }}">
                                <b>{{ $modelNames[$otherModel] }}</b>
                                <span>&#8377;{{ $price }}@if ($otherModel === 'xclusive') <small>Provisional</small>@endif</span>
                                <i>Start Tour <span aria-hidden="true">&rarr;</span></i>
                            </a>
                        @endforeach
                    </div>
                </nav>
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
                <h3 class="disp">Prime</h3>
                <p class="tour-page__tier-line">The quickest way into the Devils network. Tour footage is illustrative.</p>
                <div class="tour-page__tier-price">
                    <div class="tour-page__amount">&#8377;15<span>Lakh</span></div>
                    <p>+ 18% GST &middot; <b>&#8377;17.7 lakh</b> all-in</p>
                    <div class="tour-page__tier-actions">
                        <a @class(['cta', 'red' => $model === 'prime']) href="{{ route('prime.show') }}">Apply for Prime <i aria-hidden="true">&rarr;</i></a>
                        @if ($model !== 'prime')
                            <a class="tour-page__tier-secondary" href="{{ route('studios.tour', ['model' => 'prime']) }}">Watch Studio Tour <span aria-hidden="true">&rarr;</span></a>
                        @endif
                    </div>
                </div>
            </article>
            <article class="tour-page__tier">
                <span class="tour-page__tier-ghost" aria-hidden="true">02</span>
                <div class="tour-page__tier-kicker"><span class="lbl">Model 02</span><span class="tour-page__tier-tag">Owner-run</span></div>
                <h3 class="disp">FOFO</h3>
                <p class="tour-page__tier-line">Franchise owned, franchise operated. You run the studio.</p>
                <div class="tour-page__tier-price">
                    <div class="tour-page__amount">&#8377;35<span>Lakh</span></div>
                    <p>+ 18% GST &middot; <b>&#8377;41.3 lakh</b> all-in</p>
                    <div class="tour-page__tier-actions">
                        <a @class(['cta', 'red' => $model === 'fofo']) href="{{ route('fofo.show') }}">Apply for FOFO <i aria-hidden="true">&rarr;</i></a>
                        @if ($model !== 'fofo')
                            <a class="tour-page__tier-secondary" href="{{ route('studios.tour', ['model' => 'fofo']) }}">Watch Studio Tour <span aria-hidden="true">&rarr;</span></a>
                        @endif
                    </div>
                </div>
            </article>
            <article class="tour-page__tier tour-page__tier--flagship">
                <span class="tour-page__tier-ghost" aria-hidden="true">03</span>
                <div class="tour-page__tier-kicker"><span class="lbl">Model 03</span><span class="tour-page__tier-tag">Flagship</span></div>
                <h3 class="disp">X-Clusive</h3>
                <p class="tour-page__tier-line">The exclusive flagship format for big-city locations.</p>
                <div class="tour-page__tier-price">
                    <div class="tour-page__amount">&#8377;1.5<span>Crore</span></div>
                    <p>+ 18% GST &middot; <b>&#8377;1.77 Crore</b> all-in</p>
                    <div class="tour-page__tier-actions">
                        @if ($model === 'xclusive')
                            <a class="cta red" href="{{ $xclusiveApplicationUrl }}">Apply for X-Clusive <i aria-hidden="true">&rarr;</i></a>
                        @else
                            <a class="cta" href="{{ route('studios.tour', ['model' => 'xclusive']) }}">Tour X-Clusive <i aria-hidden="true">&rarr;</i></a>
                            <a class="tour-page__tier-secondary" href="{{ $xclusiveApplicationUrl }}">Apply for X-Clusive <span aria-hidden="true">&rarr;</span></a>
                        @endif
                    </div>
                </div>
            </article>
        </div>
        <p class="tour-page__fine">X-Clusive pricing is provisional, subject to commercial approval. All prices are exclusive of applicable GST at 18%. All-in figures add GST to the base price. Site size, territory and inclusions for each model are shared on the call.</p>
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
