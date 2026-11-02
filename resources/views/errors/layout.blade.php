<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="dark">
    <title>@yield('title') | Detailing Devils</title>
    <link rel="icon" href="{{ asset('favicon.webp') }}" type="image/webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&amp;family=Hanken+Grotesk:wght@300;400;500;600&amp;family=JetBrains+Mono:wght@400;500&amp;display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <style>
        .error-page { padding-top: calc(76px + env(safe-area-inset-top, 0px)); }
        .error-content { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: center; gap: clamp(32px, 5vw, 72px); max-width: 1440px; margin: auto; padding-block: clamp(48px, 6vw, 88px); }
        .error-copy { min-width: 0; }
        .error-eyebrow { display: flex; align-items: center; gap: 10px; font-size: 10px; letter-spacing: .13em; }
        .error-eyebrow > span { width: 6px; height: 6px; background: var(--red); flex: none; }
        .error-code { margin: 24px 0 14px; color: var(--red); font-family: var(--display); font-weight: 800; font-size: clamp(72px, 8vw, 112px); letter-spacing: -.06em; line-height: .9; }
        .error-content h1 { max-width: 19ch; margin: 0 0 20px; font-size: clamp(28px, 3.1vw, 44px); line-height: 1.04; }
        .error-description { max-width: 47ch; margin: 0; color: var(--soft); font-size: 16px; }
        .error-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        .error-actions .cta { min-height: 52px; justify-content: space-between; }
        .error-artwork { min-width: 0; margin: 0; border: 1px solid #292929; background: #0b0b0c; }
        .error-artwork svg { display: block; width: 100%; height: auto; aspect-ratio: 4 / 3; }
        .error-artwork figcaption { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 16px 20px; border-top: 1px solid #292929; color: var(--soft); font-family: var(--mono); font-size: 9px; letter-spacing: .08em; text-transform: uppercase; }
        .error-artwork figcaption i { color: var(--red); font-style: normal; margin-left: 5px; }
        .error-noscript { display: flex; flex-wrap: wrap; gap: 20px; padding-block: 16px; border-bottom: 1px solid var(--line); }
        @media (max-width: 760px) {
            .error-page { padding-top: calc(68px + env(safe-area-inset-top, 0px)); }
            .error-content { grid-template-columns: 1fr; gap: 32px; padding-block: 36px; }
            .error-code { margin-top: 22px; }
            .error-artwork { width: min(100%, 480px); justify-self: center; }
            .error-actions .cta { width: 100%; }
        }
        @media (min-width: 761px) and (max-width: 980px) {
            .error-content { gap: 28px; }
            .error-actions .cta { padding: 14px 18px; font-size: 10px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .error-actions .cta, .error-actions .cta::before, .error-actions .cta i { transition: none; }
        }
    </style>
    <noscript>
        <style>.hdr .burger { display: none; }</style>
    </noscript>
</head>
<body>
    <x-header />
    <x-navbar />
    <main class="error-page" id="top">
        <noscript>
            <nav class="error-noscript wrap" aria-label="Essential navigation">
                <a href="{{ url('/') }}">Home</a>
                <a href="{{ route('studios.index') }}">Find a Studio</a>
                <a href="{{ route('studios.tour') }}">Studio Tour</a>
            </nav>
        </noscript>
        @include('errors.content')
    </main>
    <x-footer />
    <script src="{{ asset('assets/js/modules/navigation.js') }}" defer></script>
</body>
</html>
