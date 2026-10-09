@props(['site' => config('site')])

<footer>
  <div class="wrap">
    <div class="row">
      <div>
        <a class="brand" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}" aria-label="Detailing Devils home"><img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ $site['brand'] }}" width="1600" height="377"></a>
        <p class="about">{{ $site['footer_about'] }}</p>
        <address class="contact">
          <a href="tel:{{ $site['phone_link'] }}">{{ $site['phone'] }}</a>
          <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
          <span>{{ $site['address'] }}</span>
        </address>
      </div>
      <div><h4>Services</h4><ul>@foreach(array_slice($site['services'], 0, 4) as $svc)<li><a href="{{ request()->is('/') ? '#services' : url('/') . '#services' }}">{{ $svc['title'] }}</a></li>@endforeach</ul></div>
      <div><h4>Company</h4><ul><li><a href="{{ request()->is('/') ? '#approach' : url('/') . '#approach' }}">Approach</a></li><li><a href="{{ request()->is('/') ? '#work' : url('/') . '#work' }}">Our Work</a></li><li><a href="{{ request()->is('/') ? '#franchise' : url('/') . '#franchise' }}">Franchise</a></li><li><a href="{{ request()->is('/') ? '#studios' : url('/') . '#studios' }}">Studios</a></li></ul></div>
      <div><h4>Follow</h4><ul>@foreach($site['social'] as $name => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a></li>@endforeach<li><a href="{{ request()->is('/') ? '#contact' : url('/') . '#contact' }}">Contact</a></li></ul></div>
    </div>
    <div class="legal"><span class="lbl">© {{ date('Y') }} {{ $site['brand'] }}</span><span class="lbl">Privacy · Terms · Cookies</span><a class="lbl" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}">Back to top ↑</a></div>
    <div class="giant" aria-hidden="true">Detailing Devils</div>
  </div>
</footer>
