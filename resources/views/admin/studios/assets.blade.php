@pushOnce('styles', 'studio-cms-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin-studios.css') }}?v={{ substr(hash_file('sha256', public_path('assets/css/admin-studios.css')), 0, 12) }}">
@endPushOnce

@pushOnce('scripts', 'studio-cms-scripts')
    <script type="module" src="{{ asset('assets/js/admin/studios.js') }}?v={{ substr(hash_file('sha256', public_path('assets/js/admin/studios.js')), 0, 12) }}"></script>
@endPushOnce
