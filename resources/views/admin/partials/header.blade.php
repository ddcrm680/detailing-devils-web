<header class="admin-header">
    <a
        class="brand"
        href="{{ auth('web')->check() ? route('admin.index') : route('admin.login') }}"
        aria-label="Detailing Devils administration"
    >
        <img src="{{ asset('img/logo.png') }}" alt="{{ config('site.brand') }}" width="190" height="45">
        <span>ADMIN</span>
    </a>
    <div class="header-workspace">
        @auth('web')
            <button
                class="nav-toggle icon-button"
                data-nav-toggle
                type="button"
                aria-controls="admin-sidebar"
                aria-expanded="false"
                aria-label="Open navigation"
                hidden
            >
                <svg
                    viewBox="0 0 24 24"
                    width="20"
                    height="20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    aria-hidden="true"
                >
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        @endauth
        <span class="header-context">@yield('context', 'Website administration')</span>
        @auth('web')
            <div class="user-context">
                <strong>{{ auth('web')->user()->name }}</strong>
                <span>{{ [
                    'super_admin' => 'Super Admin',
                    'admin' => 'Admin',
                    'seo_admin' => 'SEO Admin',
                ][auth('web')->user()->role] ?? 'Unassigned' }}</span>
            </div>
            <form action="{{ route('admin.logout') }}" method="post">
                @csrf
                <button class="button secondary small-button" type="submit">Sign out</button>
            </form>
        @else
            <span class="muted small">Secure workspace</span>
        @endauth
    </div>
</header>
