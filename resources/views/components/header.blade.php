@props(['site' => config('site')])

<header class="hdr" id="hdr">
  <div class="wrap">
    <div class="left">
      @if (request()->routeIs('studios.tour'))
        <a class="header-cta" href="{{ route('studios.tour') }}#models">Apply Now <i aria-hidden="true">→</i></a>
      @else
        <a class="header-cta" href="{{ route('studios.tour') }}"><span class="header-cta-prefix">Franchise / </span>Studio Tour <i aria-hidden="true">→</i></a>
      @endif
    </div>
    <a class="brand" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}" aria-label="Detailing Devils home"><img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ $site['brand'] }}" width="1600" height="377"></a>
    <div class="right"><a class="ph-no" href="tel:{{ $site['phone_link'] }}"> <i class="fa-solid fa-phone" aria-hidden="true"></i>{{ $site['phone'] }}</a><button class="burger" id="burger" aria-expanded="false" aria-controls="menu">Menu<span><b></b><b></b></span></button></div>
  </div>
</header>
