
@if (session('error'))
    <script>
        window.showNotification({
            type: 'error',
            title: @json(session('error')),
        });
    </script>
@endif
