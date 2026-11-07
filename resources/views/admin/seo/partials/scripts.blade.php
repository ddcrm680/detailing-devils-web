@pushOnce('scripts', 'seo-workspace')
    <script
        type="module"
        src="{{ asset('assets/js/admin/workspace.js') }}?v={{ substr(hash_file('sha256', public_path('assets/js/admin/workspace.js')), 0, 12) }}"
    ></script>
@endPushOnce
