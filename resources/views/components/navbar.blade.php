<div class="menu" id="menu" aria-hidden="true" inert data-lenis-prevent>
  <nav aria-label="Main">
    <a href="{{ request()->is('/') ? '#approach' : url('/') . '#approach' }}"><small>01</small><span>Approach</span></a>
    <a href="{{ request()->is('/') ? '#services' : url('/') . '#services' }}"><small>02</small><span>Services</span></a>
    <a href="{{ request()->is('/') ? '#work' : url('/') . '#work' }}"><small>03</small><span>Our Work</span></a>
    <a href="{{ request()->is('/') ? '#franchise' : url('/') . '#franchise' }}"><small>04</small><span>Franchise</span></a>
    <a href="{{ route('studios.index') }}"><small>05</small><span>Studios</span></a>
    <a href="https://www.devilscrm.detailingdevils.com/check-warranty"><small>06</small><span style="font-stretch:100%">Warranty Check</span></a>
  <a href="https://www.shop.detailingdevils.com/" target="_blank"><small>07</small><span>Shop</span></a>
    <a href="{{ request()->is('/') ? '#contact' : url('/') . '#contact' }}"><small>08</small><span>Contact</span></a>
  </nav>
  <figure class="ph"><img src="{{ asset('img/interior.webp') }}" alt="McLaren cockpit with black leather seats and green belts"></figure>
</div>
