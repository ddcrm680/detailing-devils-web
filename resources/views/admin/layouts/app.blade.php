<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @hasSection('metadata')
        @yield('metadata')
    @else
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title', 'Administration') | {{ config('site.brand') }}</title>
    @endif
    <link rel="icon" href="{{ asset('favicon.webp') }}" type="image/webp">
    <link
        rel="stylesheet"
        href="{{ asset('assets/css/admin.css') }}?v={{ substr(hash_file('sha256', public_path('assets/css/admin.css')), 0, 12) }}"
    >
    <script
        type="module"
        src="{{ asset('assets/js/admin/workspace.js') }}?v={{ substr(hash_file('sha256', public_path('assets/js/admin/workspace.js')), 0, 12) }}"
    ></script>
</head>
<body class="admin-app {{ auth('web')->check() ? '' : 'is-guest' }}">
    <a class="skip" href="#workspace">Skip to workspace</a>
    @include('admin.partials.header')
    @auth('web')
        @include('admin.partials.sidebar')
        <button class="menu-backdrop" data-menu-backdrop type="button" aria-label="Close navigation" hidden></button>
    @endauth
    <div class="admin-body">
        <main id="workspace" class="workspace" tabindex="-1">
            @auth('web')
                <nav class="breadcrumbs" aria-label="Breadcrumb">
                    <a href="{{ route('admin.index') }}">Admin</a>
                    <span aria-hidden="true">/</span>
                    @if (request()->routeIs('admin.seo.index'))
                        <span aria-current="page">SEO overview</span>
                    @else
                        <a href="{{ route('admin.seo.index') }}">SEO</a>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">@yield('context', 'Page metadata')</span>
                    @endif
                </nav>
            @endauth
            @if (session('status'))
                <div class="notice success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="notice error" role="alert" tabindex="-1" data-validation-errors>
                    <strong>Please review the following:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
        @include('admin.partials.footer')
    </div>
</body>
</html>
