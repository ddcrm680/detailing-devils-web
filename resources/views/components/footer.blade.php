@props(['site' => config('site')])

<footer>
  <div class="wrap">
    <div class="row">
      <div>
        <a class="brand" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}" aria-label="Detailing Devils home">
          <img class="logo" src="{{ asset('img/logo.png') }}" alt="{{ $site['brand'] }}" width="1600" height="377">
        </a>
        <p class="about">{{ $site['footer_about'] }}</p>
        <address class="contact">
          <a href="tel:{{ $site['phone_link'] }}"><i class="fa-solid fa-phone" aria-hidden="true"></i>{{ $site['phone'] }}</a>
          <a href="mailto:{{ $site['email'] }}"><i class="fa-solid fa-envelope" aria-hidden="true"></i>{{ $site['email'] }}</a>
          <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $site['address'] }}</span>
        </address>
      </div>
      <div>
        <h4>Services</h4>
        <ul>@foreach(array_slice($site['services'], 0, 4) as $svc) <li>
            <a href="{{ request()->is('/') ? '#services' : url('/') . '#services' }}">{{ $svc['title'] }}</a>
          </li>@endforeach <li>
            <a href="https://www.devilscrm.detailingdevils.com/check-warranty">Warranty Check</a>
          </li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li>
            <a href="{{ request()->is('/') ? '#approach' : url('/') . '#approach' }}">Approach</a>
          </li>
          <li>
            <a href="{{ request()->is('/') ? '#work' : url('/') . '#work' }}">Our Work</a>
          </li>
          <li>
            <a href="{{ request()->is('/') ? '#franchise' : url('/') . '#franchise' }}">Franchise</a>
          </li>
          <li>
            <a href="{{ route('studios.index') }}">Studios</a>
          </li>
        </ul>
      </div>
      <div>
        <h4>Follow</h4>
        <ul>@foreach($site['social'] as $name => $url) <li>
            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a>
          </li>@endforeach <li>
            <a href="{{ request()->is('/') ? '#contact' : url('/') . '#contact' }}">Contact</a>
          </li>
        </ul>
      </div>
    </div>
    <div class="legal">
      <span class="lbl">© {{ date('Y') }} {{ $site['brand'] }}</span>
      <nav class="legal-links" aria-label="Legal policies">
        <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a>
        <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
        <a href="{{ route('legal.returns') }}" style="display:none;">Return &amp; Refund Policy</a>
        <a href="{{ route('legal.cancellation') }}" style="display: none;">Cancellation Policy</a>
        <a href="{{ route('legal.privacy') }}#cookies">Cookies</a>
      </nav>
      <a class="lbl" href="{{ request()->is('/') ? '#top' : url('/') . '#top' }}">Back to top ↑</a>
    </div>
    <div class="giant" aria-hidden="true">Detailing Devils</div>
  </div>

  <div class="whats-btn">
  <a href="https://api.whatsapp.com/send?phone={{ $site['whatsapp_phone'] }}" target="_blank" rel="noopener noreferrer" aria-label="Chat with Detailing Devils on WhatsApp">
    <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
  </a>
</div>

</footer>
