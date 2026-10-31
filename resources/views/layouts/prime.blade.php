<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Franchise Application | Detailing Devils Prime')</title>
    <meta name="description" content="@yield('description', 'Apply for a Detailing Devils Prime franchise opportunity.')">
    @if (config('site.google_site_verification'))
        <meta name="google-site-verification" content="{{ config('site.google_site_verification') }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.webp') }}" type="image/webp">

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://code.jquery.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,300..900&family=Hanken+Grotesk:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">

    {{-- Prime keeps its own form layout while reusing the website header, menu and footer. --}}
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/prime.css') }}">
    @stack('css')

    {{-- Preserve the legacy Prime Pixel ID, but do not track local development. --}}
    @production
    <script>
        !function(f,b,e,v,n,t,s){
            if(f.fbq)return;
            n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];
            t=b.createElement(e);t.async=!0;t.src=v;
            s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s);
        }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1565782775050658');
        fbq('track', 'PageView');
    </script>
    @endproduction
</head>
<body class="prime-page">
    @production
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=1565782775050658&amp;ev=PageView&amp;noscript=1"
             alt="">
    </noscript>
    @endproduction

    <x-header />
    <x-navbar />

    <main>
        @yield('content')
    </main>

    <x-footer />

    {{-- Original OTP modal: phone text is populated by the wizard, never hard-coded. --}}
    <div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-semibold" id="otpModalLabel">Mobile Number Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">
                        Enter the <strong>6-digit verification code</strong> sent to
                        <strong id="otpMobileNumber"></strong>.
                    </p>
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg text-center"
                               id="otpCode" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                               autocomplete="one-time-code" placeholder="••••••">
                    </div>
                    <div id="otpError" class="text-danger small mb-2 d-none"></div>
                    <div id="otpVerifying" class="text-center text-muted small mb-3 d-none">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Verifying your code...
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-link btn-sm p-0" id="btnResendOtp">Resend Code</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Prime-only vendor JS. Home/app layout never loads jQuery. --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/vendor/bootstrap.bundle.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.22.1/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.1.2/dist/js/intlTelInput.min.js"></script>
    <script src="{{ asset('assets/vendor/axios.min.js') }}"></script>
    <script src="{{ asset('assets/js/common/axios.js') }}"></script>
    <script src="{{ asset('assets/js/modules/navigation.js') }}"></script>

    {{-- Prime form JavaScript is pushed by forms/prime.blade.php. --}}
    @stack('js')
</body>
</html>
