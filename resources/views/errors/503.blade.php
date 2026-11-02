@php
    $brand = config('site.brand');
    $supportPhone = config('site.phone');
    $supportPhoneLink = config('site.phone_link');
    $supportEmail = config('site.support_email');
    $logoPath = public_path('img/logo.png');
    $logo = is_readable($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark">
    <title>Temporarily Unavailable | {{ $brand }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #070707; color: #edeae4; font-family: Arial, Helvetica, sans-serif; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        a:focus-visible { outline: 3px solid #fff; outline-offset: 5px; }
        .maintenance { width: min(100%, 1180px); margin: auto; padding: clamp(24px, 4vw, 48px); }
        .maintenance-header { text-align: center; padding-bottom: 28px; border-bottom: 1px solid #252525; }
        .maintenance-logo { display: inline-block; width: 190px; height: auto; vertical-align: middle; }
        .maintenance-main { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: clamp(28px, 4vw, 56px); padding-block: 48px; }
        .maintenance-label { margin: 0 0 18px; color: #bab6af; font-family: ui-monospace, monospace; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; }
        .maintenance-label::before { content: ''; display: inline-block; width: 6px; height: 6px; margin-right: 9px; background: #d7232c; }
        h1 { margin: 0 0 20px; font-size: clamp(32px, 3.8vw, 48px); line-height: 1.08; letter-spacing: -.04em; text-wrap: balance; }
        h1 span { color: #ef4650; white-space: nowrap; }
        .maintenance-description { max-width: 45ch; color: #bab6af; margin: 0; }
        .maintenance-retry { display: inline-flex; gap: 40px; justify-content: space-between; align-items: center; min-height: 52px; margin-top: 24px; padding: 14px 24px; background: #ce1824; color: #fff; text-decoration: none; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .maintenance-retry:hover { background: #ad111b; }
        .maintenance-art { margin: 0; min-width: 0; border: 1px solid #292929; background: #0b0b0c; }
        .maintenance-art svg { display: block; width: 100%; height: auto; aspect-ratio: 4 / 3; }
        .maintenance-support { grid-column: 1 / -1; padding: 24px; background: #111112; border: 1px solid #292929; display: grid; grid-template-columns: 1fr 1.2fr; align-items: center; gap: 24px; }
        .maintenance-support h2 { margin: 0; font-size: 19px; line-height: 1.4; }
        .maintenance-support p { margin: 4px 0 0; color: #bab6af; font-size: 14px; }
        .maintenance-contacts { display: flex; flex-wrap: wrap; gap: 12px 24px; }
        .maintenance-contacts a { display: inline-flex; gap: 10px; align-items: center; min-height: 44px; text-decoration: none; font-size: 15px; overflow-wrap: anywhere; }
        .maintenance-contacts a:hover { text-decoration: underline; text-underline-offset: 4px; }
        .maintenance-contacts svg { width: 19px; height: 19px; flex: none; color: #ef4650; }
        .maintenance-footer { border-top: 1px solid #252525; padding-top: 22px; color: #96918c; text-align: center; font-size: 12px; }
        @media (max-width: 800px) {
            .maintenance-main { grid-template-columns: 1fr; gap: 28px; padding-block: 32px; }
            .maintenance-copy { text-align: center; }
            .maintenance-description { margin-inline: auto; }
            .maintenance-art { width: min(100%, 420px); justify-self: center; }
            .maintenance-support { grid-template-columns: 1fr; gap: 12px; padding: 20px; }
            .maintenance-contacts { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>
    <div class="maintenance">
        <header class="maintenance-header">
            @if ($logo)
                <img class="maintenance-logo" src="{{ $logo }}" alt="{{ $brand }}" width="1600" height="377">
            @else
                <strong>{{ $brand }}</strong>
            @endif
        </header>
        <main class="maintenance-main" aria-labelledby="maintenance-title">
            <div class="maintenance-copy">
                <p class="maintenance-label">503 / Temporarily unavailable</p>
                <h1 id="maintenance-title">We're <span>Fine-Tuning</span> Things</h1>
                <p class="maintenance-description">A little care behind the scenes. We're fine-tuning the website to bring you a smoother experience. Please try again later. Thank you for your patience.</p>
                <a class="maintenance-retry" href="/">
                    Try Again
                    <span aria-hidden="true">↗</span>
                </a>
            </div>
            <figure class="maintenance-art">
                @include('errors.illustration', ['variant' => 'maintenance'])
            </figure>
            <section class="maintenance-support" aria-labelledby="support-title">
                <div>
                    <h2 id="support-title">Need assistance?</h2>
                    <p>Contact our team.</p>
                </div>
                <div class="maintenance-contacts">
                    <a href="tel:{{ $supportPhoneLink }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M5 3H9L11 8L8 10C9.4 12.8 11.2 14.6 14 16L16 13L21 15V19C21 20.1 20.1 21 19 21C10.2 21 3 13.8 3 5C3 3.9 3.9 3 5 3Z" />
                        </svg>
                        <span>{{ $supportPhone }}</span>
                    </a>
                    <a href="mailto:{{ $supportEmail }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M3 7L12 13L21 7" />
                        </svg>
                        <span>{{ $supportEmail }}</span>
                    </a>
                </div>
            </section>
        </main>
        <footer class="maintenance-footer">© {{ date('Y') }} {{ $brand }}</footer>
    </div>
</body>
</html>
