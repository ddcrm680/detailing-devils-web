@extends('layouts.app')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}">
@endpush

@section('content')
<!-- 1. SCROLL BANNER -->
  <section class="seq" id="seq" aria-label="Intro">
    <div class="stage">
      <canvas id="cv" aria-hidden="true"></canvas>
      <div class="dim"></div>
      <div class="shade"></div>
      <div class="htxt top" id="htop">
        <span class="hred">{!! collect($site['hero']['tagline'])->map(fn ($t) => e($t))->implode(' <i>|</i> ') !!}</span>
        <h1 class="disp">{{ $site['hero']['line1'] }}<br>{{ $site['hero']['line2'] }}</h1>
      </div>
      <div class="htxt bot" id="hbot"><p class="hsub">{{ $site['hero']['subline'] }}</p></div>
      <div class="hint" id="hint">Scroll<i></i></div>
      <div class="meter" aria-hidden="true"><span id="fnum">001 / {{ sprintf('%03d', $site['hero']['frames']) }}</span><div class="bar"><b id="fbar"></b></div></div>
      <div class="loader" id="loader"></div>
    </div>
  </section>

  <!-- 2. INTRO -->
  <section class="intro">
    <div class="wrap">
      <span class="lbl">Trusted with every marque</span>
      <h2 class="disp">Our approach to every <span class="acc">car</span></h2>
    </div>
    <div class="marq" aria-label="Car brands we work on">
      <div class="tr">
        <span>McLaren</span><span>Mercedes-AMG</span><span>Porsche</span><span>Lamborghini</span><span>Ferrari</span><span>Bentley</span><span>Rolls-Royce</span><span>Land Rover</span><span>BMW M</span><span>Audi Sport</span><span>Aston Martin</span><span>Maserati</span>
        <span aria-hidden="true">McLaren</span><span aria-hidden="true">Mercedes-AMG</span><span aria-hidden="true">Porsche</span><span aria-hidden="true">Lamborghini</span><span aria-hidden="true">Ferrari</span><span aria-hidden="true">Bentley</span><span aria-hidden="true">Rolls-Royce</span><span aria-hidden="true">Land Rover</span><span aria-hidden="true">BMW M</span><span aria-hidden="true">Audi Sport</span><span aria-hidden="true">Aston Martin</span><span aria-hidden="true">Maserati</span>
      </div>
    </div>
    <div class="wrap">
      <p class="sub">Every step is deliberate and every product has a purpose. We match the finish to your car, how you drive it and the standard you expect.</p>
      <a class="cta" href="#contact">Book Your Detail <i>→</i></a>
    </div>
  </section>

  <!-- 3. APPROACH -->
  <section class="approach" id="approach">
    <div class="head wrap">
      <h2 class="disp">Inspect<br>Correct<br><span class="acc">Protect</span></h2>
      <span class="lbl">Three stages, in this order, on every car</span>
    </div>
    <div class="h-pin" id="hpin">
      <div class="h-track" id="htrack">
        <article class="slide">
          <figure class="ph"><img src="img/inspect.webp" style="object-position:66% 50%" alt="Detailer checking black paint with an inspection light and a paint depth gauge" loading="lazy"><figcaption><span>Inspection</span><span>Paint &amp; lights</span></figcaption></figure>
          <div><span class="n">01 / 03</span><h3 class="disp">Inspect</h3><p>Every job starts under inspection lights. We measure paint depth and map every swirl, scratch and water spot before any product touches the car.</p><div class="spec">Paint depth gauge · swirl lights · written report</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
        <article class="slide">
          <figure class="ph"><img src="img/correct.webp" style="object-position:40% 50%" alt="Detailer machine-polishing black paint" loading="lazy"><figcaption><span>Correction</span><span>Machine polish</span></figcaption></figure>
          <div><span class="n">02 / 03</span><h3 class="disp">Correct</h3><p>Multi-step machine polishing removes defects layer by layer, taking off only what the paint can safely spare. Real gloss comes from a flat, clean surface.</p><div class="spec">1 to 3 step polish · measured removal</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
        <article class="slide">
          <figure class="ph"><img src="img/protect.webp" alt="Installer fitting paint protection film to a front wing" loading="lazy"><figcaption><span>Protection</span><span>Ceramic · PPF</span></figcaption></figure>
          <div><span class="n">03 / 03</span><h3 class="disp">Protect</h3><p>Ceramic coating or paint protection film locks the finish in, so it keeps beading water and resisting dust, sun and stone chips long after you drive out.</p><div class="spec">Ceramic · graphene · PPF · aftercare</div><a class="cta" href="#contact">Start Your Detail <i>→</i></a></div>
        </article>
      </div>
    </div>
  </section>

  <!-- 4. STATEMENT -->
  <section class="stmt wrap">
    <figure class="ph"><img src="img/statement.webp" alt="Mirror-finish black paint reflecting studio lights beside a red tail light" loading="lazy"><figcaption><span>Finish</span><span>Deep gloss</span></figcaption></figure>
    <div>
      <span class="lbl">The Devils standard</span>
      <h2 class="disp" style="margin-top:18px">A car should turn <span class="acc">heads</span> before it moves. Every panel, every edge, every reflection is considered.</h2>
      <p>From paint correction and ceramic coating to PPF, interiors and window film, each service sharpens your car's character without changing what makes it yours.</p>
      <a class="cta" href="#contact">Start Your Detail <i>→</i></a>
    </div>
  </section>

  <!-- 5. NUMBERS -->
  <section class="nums" aria-label="Detailing Devils in numbers">
    @foreach($site['numbers'] as $num)
    <div><b>{{ $num['value'] }}</b><span class="lbl">{{ $num['label'] }}</span></div>
    @endforeach
  </section>

  <!-- 6. SERVICES -->
  <section class="svc" id="services">
    <div class="wrap">
      <div class="top"><h2 class="disp">Services</h2><span class="lbl">Same process and products at every studio</span></div>
      <div class="svc-in">
        <ul class="svc-list" id="svcList"></ul>
        <div class="svc-vis" id="svcVis"></div>
      </div>
    </div>
  </section>

  <!-- 7. DULL ENDS HERE -->
  <section class="ends" id="work">
    <div class="ends-stage">
      <div class="lanes" aria-hidden="true"></div>
      <div class="ends-txt">
        <span class="word" id="dull">Dull</span>
        <div class="fin">
          <h2 class="disp">Ends <span class="acc">Here</span></h2>
          <p>Finishes that show taste, care and attention to detail. Deep colour, sharp reflections and protection that lasts.</p>
        </div>
      </div>
      <img class="topcars" id="topcars" src="img/top-cars.webp" alt="Top view of two black G-Wagons and a green McLaren driving side by side" loading="lazy">
    </div>
  </section>

  <!-- 8. CARDS -->
  <section class="cards wrap" id="studios">
    <a class="card" href="#contact">
      <figure class="ph"><img src="img/recent-work.webp" alt="Satin blue Dodge Charger SRT outside a Detailing Devils studio" loading="lazy"></figure>
      <div class="ov"><span class="lbl">Portfolio</span><h3 class="disp">Recent Work</h3><p>Supercars, SUVs and daily drivers, corrected and protected in our studios.</p><span class="go">Explore work →</span></div>
    </a>
    <a class="card" href="#contact">
      <figure class="ph"><img src="img/find-studio.webp" alt="Empty Detailing Devils studio with numbered bays at the entrance of a Detailing Devils studio" loading="lazy"></figure>
      <div class="ov"><span class="lbl">145+ locations</span><h3 class="disp">Find a Studio</h3><p>There's a Detailing Devils studio in most major Indian cities. Find the one nearest you.</p><span class="go">Find a studio →</span></div>
    </a>
  </section>

  <!-- 8b. LINE-UP -->
  <section class="lineup" aria-label="The Devils line-up">
    <h2 class="disp lineup-h">145+ Studios. <span class="acc">One</span> Standard.</h2>
    <div class="lineup-in"><img id="lineupImg" src="img/five-cars.webp" alt="Two black G-Wagons, a yellow BMW M4, a yellow Porsche 911 and a green McLaren in a dark studio" loading="lazy"></div>
  </section>

  <!-- 9. FRANCHISE -->
  <section class="fr" id="franchise">
    <div class="wrap fr-in">
      <div>
        <span class="lbl">Franchise</span>
        <h2 class="disp">Own a <span class="acc">Devils</span> studio</h2>
        <p>Join India's largest detailing network. You bring the location and the drive. We bring the brand, training, products and systems to run it.</p>
        <div class="fr-ctas">
          @foreach($site['franchise_applications'] as $application)
          <a class="cta" href="{{ $application['url'] }}">{{ $application['label'] }} <i>↗</i></a>
          @endforeach
        </div>
      </div>
      <ol>
        @foreach($site['franchise_steps'] as $i => $step)
        <li><span class="n">{{ sprintf('%02d', $i + 1) }}</span><div><b>{{ $step['title'] }}</b><span>{{ $step['text'] }}</span></div></li>
        @endforeach
      </ol>
    </div>
  </section>

  <!-- 10. FINAL CTA -->
  <section class="final" id="contact">
    <figure class="ph"><img data-speed="10" src="img/back-view.webp" alt="" loading="lazy"></figure>
    <div class="wrap final-in">
      <div>
        <span class="pre">Are you ready to</span>
        <h2 class="disp">Refuse<br><span class="acc">Dull</span></h2>
        <address class="contact big">
          <a href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a>
          <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
          <span>{{ $site['address'] }}</span>
        </address>
      </div>
      <form id="form" method="POST" action="{{ route('enquiry.store') }}" novalidate>
        @csrf
        <div class="f"><label for="n">Name</label><input id="n" name="name" autocomplete="name" required></div>
        <div class="f"><label for="p">Phone</label><input id="p" name="phone" type="tel" autocomplete="tel" placeholder="+91" required></div>
        <div class="f"><label for="c">City</label><input id="c" name="city" autocomplete="address-level2"></div>
        <div class="f"><label for="m">Car</label><input id="m" name="car" placeholder="Make and model"></div>
        <div class="f full"><label for="s">I'm interested in</label>
          <select id="s" name="interest">@foreach($site['services'] as $svc)<option>{{ $svc['title'] }}</option>@endforeach<option>Franchise enquiry</option></select>
        </div>
        <div class="ff"><button class="cta red" type="submit">Start Your Detail <i>→</i></button><p class="note" id="note" role="status"></p></div>
      </form>
    </div>
  </section>
@endsection


@push('js')

  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/lenis@1.1.13/dist/lenis.min.js"></script>


  <script>
      window.DD_SERVICES = @json($site['services']);
      window.DD_FRAMES = @json(collect(range(1, $site['hero']['frames']))->map(fn ($i) => sprintf($site['hero']['frame_pattern'], $i))->values());
  </script>
  <script src="{{ asset('assets/js/modules/home.js') }}"></script>
@endpush
