<div
    class="home-entrance"
    id="home-entrance"
    role="dialog"
    aria-modal="true"
    aria-label="Welcome to Detailing Devils"
    tabindex="-1"
    style="display: none"
    hidden
>
    <img
        class="home-entrance__logo"
        src="{{ asset('img/logo.png') }}"
        alt="{{ config('site.brand', 'Detailing Devils') }}"
        width="1600"
        height="377"
    >
    <div class="home-entrance__middle">
        <p class="home-entrance__quote">
            <span class="home-entrance__typed" aria-hidden="true"></span>
            <span class="home-entrance__accessible-quote">Every finish earned by hand. Protected to last. Built for those who refuse dull.</span>
        </p>
        <div class="home-entrance__loading" aria-hidden="true">
            <div class="home-entrance__bar"><b></b></div>
            <span class="home-entrance__percentage">000%</span>
        </div>
    </div>
    <div class="home-entrance__bottom">
        <button class="home-entrance__enter" type="button">Enter</button>
        <p class="home-entrance__note">By pressing &ldquo;Enter&rdquo; on this website, you accept the use of cookies for analytics</p>
    </div>
</div>
