
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <base href="{{ url('/') }}/">

    {{-- SEO --}}
    <title>@yield('title', $site['brand'] ?? config('site.brand', 'Detailing Devils'))</title>
    <meta name="description" content="@yield('description', $site['description'] ?? config('site.description', 'Detailing Devils'))">
    @if (config('site.google_site_verification'))
        <meta name="google-site-verification" content="{{ config('site.google_site_verification') }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.webp') }}" type="image/webp">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&family=Hanken+Grotesk:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">

    {{-- Vendor CSS - Exclude Homepage --}}
    @if (!request()->is('/'))
        <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/tom-select.bootstrap5.min.css') }}">
    @endif

    {{-- SweetAlert2 CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/vendor/sweetalert2.min.css') }}">

    {{-- Main Website CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

    {{-- Page-specific CSS --}}
    @stack('css')

</head>

<body>

    {{-- Website Header & Navigation --}}
    <x-header />
    <x-navbar />

    {{-- Main Content --}}
    <main id="top">
        @yield('content')
    </main>

    {{-- Website Footer --}}
    <x-footer />

    {{-- Vendor JavaScript --}}
    <script src="{{ asset('assets/vendor/axios.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/sweetalert2.all.min.js') }}"></script>

    {{-- Bootstrap JS - Exclude Homepage --}}
    @if (!request()->is('/'))
        <script src="{{ asset('assets/vendor/bootstrap.bundle.min.js') }}"></script>
    @endif

    {{-- Common JavaScript --}}
    <script src="{{ asset('assets/js/common/dom.js') }}"></script>
    <script src="{{ asset('assets/js/common/axios.js') }}"></script>
    <script src="{{ asset('assets/js/common/location.js') }}"></script>
    <script src="{{ asset('assets/js/common/loader.js') }}"></script>
    <script src="{{ asset('assets/js/common/notification.js') }}"></script>
    <script src="{{ asset('assets/js/common/validation.js') }}"></script>
    <script src="{{ asset('assets/js/common/response.js') }}"></script>

    {{-- Session Notifications --}}
    <x-notification />

    {{-- Shared Website Navigation --}}
    <script src="{{ asset('assets/js/modules/navigation.js') }}"></script>

    {{-- Page-specific JavaScript --}}
    @stack('js')

</body>
</html>
