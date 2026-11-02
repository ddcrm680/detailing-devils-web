<section class="error-content wrap" aria-labelledby="error-title">
    <div class="error-copy">
        <span class="error-eyebrow lbl"><span aria-hidden="true"></span> Detailing Devils / System notice</span>
        <p class="error-code" aria-label="HTTP status @yield('code')">@yield('code')</p>
        <h1 class="disp" id="error-title">@yield('title')</h1>
        <p class="error-description">@yield('description')</p>
        <div class="error-actions">
            <a class="cta red error-home" href="{{ url('/') }}">
                Back to Home
                <i aria-hidden="true">↗</i>
            </a>
            <a class="cta" href="{{ route('studios.index') }}">
                Find a Studio
                <i aria-hidden="true">↗</i>
            </a>
        </div>
    </div>
    @php
        $errorCode = trim($__env->yieldContent('code'));
        $illustrationVariant = match ($errorCode) {
            '403' => 'access',
            '404' => 'route',
            '419' => 'pass',
            '429' => 'queue',
            '500' => 'inspection',
            '503' => 'maintenance',
            default => str_starts_with($errorCode, '4') ? 'client' : 'system',
        };
    @endphp
    <figure class="error-artwork">
        @include('errors.illustration', ['variant' => $illustrationVariant])
        <figcaption>
            <span>DD / Precision Studio</span>
            <span>Recovery in focus <i aria-hidden="true">↗</i></span>
        </figcaption>
    </figure>
</section>
