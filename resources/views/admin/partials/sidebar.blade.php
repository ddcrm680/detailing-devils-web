<aside class="sidebar" id="admin-sidebar" aria-label="Administration navigation">
    <div class="sidebar-section">
        <span class="sidebar-label">WORKSPACE</span>
        <nav aria-label="Admin modules">
            <a
                class="sidebar-link {{ request()->routeIs('admin.seo.*') ? 'active' : '' }}"
                href="{{ route('admin.seo.index') }}"
                @if (request()->routeIs('admin.seo.*'))
                    aria-current="{{ request()->routeIs('admin.seo.index') ? 'page' : 'true' }}"
                @endif
            >
                <svg
                    viewBox="0 0 24 24"
                    width="20"
                    height="20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    aria-hidden="true"
                >
                    <path d="M4 18V9m8 9V4m8 14v-6M2 21h20" />
                </svg>
                <span>SEO management</span>
            </a>
        </nav>
    </div>
    <div class="sidebar-bottom">
        <span class="sidebar-label">WEBSITE</span>
        <a
            class="sidebar-link"
            href="{{ route('home') }}"
            target="_blank"
            rel="noopener"
        >View public website <span aria-hidden="true">&nearr;</span></a>
        <p>Private administration.<br>No customer or enquiry data.</p>
    </div>
</aside>
