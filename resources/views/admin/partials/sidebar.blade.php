<aside class="sidebar" id="admin-sidebar" aria-label="Administration navigation">
    <div class="sidebar-section">
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
                <span>SEO Management</span>
            </a>
            @can('manage-studios')
                @php($studiosActive = request()->routeIs('admin.studios.*'))
                <details class="sidebar-group {{ $studiosActive ? 'is-active' : '' }}" data-sidebar-group @if ($studiosActive) open @endif>
                    <summary class="sidebar-group-toggle" aria-controls="studio-navigation" aria-expanded="{{ $studiosActive ? 'true' : 'false' }}">
                        <i class="bi bi-buildings" aria-hidden="true"></i>
                        <span>Studios</span>
                        <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="sidebar-submenu" id="studio-navigation">
                        @foreach ([
                            ['admin.studios.index', 'All Studios', 'bi-geo-alt', request()->routeIs('admin.studios.index', 'admin.studios.edit', 'admin.studios.show')],
                            ['admin.studios.create', 'Add Studio', 'bi-plus-lg', request()->routeIs('admin.studios.create')],
                            ['admin.studios.gallery.index', 'Studio Gallery', 'bi-images', request()->routeIs('admin.studios.gallery.*')],
                        ] as [$destination, $label, $icon, $active])
                            <a class="sidebar-link sidebar-child {{ $active ? 'active' : '' }}" href="{{ route($destination) }}" @if ($active) aria-current="page" @endif>
                                <i class="bi {{ $icon }}" aria-hidden="true"></i>
                                <span>{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endcan
        </nav>
    </div>
    <div class="sidebar-bottom">
        <a
            class="sidebar-link"
            href="{{ route('home') }}"
            target="_blank"
            rel="noopener"
        ><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>View Website</span></a>
    </div>
</aside>
