@props(['site' => config('site')])

<header class="hdr" id="hdr">
  <div class="wrap">
    <div class="left">@foreach($site['social'] as $name => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a>@endforeach</div>
    <a class="brand" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}" aria-label="Detailing Devils home"><img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ $site['brand'] }}" width="1600" height="377"></a>
    <div class="right"><a class="ph-no" href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a><button class="burger" id="burger" aria-expanded="false" aria-controls="menu">Menu<span><b></b><b></b></span></button></div>
  </div>
</header>
