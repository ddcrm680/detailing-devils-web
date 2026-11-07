@php
    $friendlyMessages = [
        'No approved social-sharing image.' => 'Optional: choose a social sharing image.',
        'Long title preserved; editorial review recommended.' => 'Your title may be shortened in search results. Your approved text is kept as written.',
        'Long description preserved; editorial review recommended.' => 'Your description may be shortened in search results. Your approved text is kept as written.',
        'Duplicate title.' => 'Another page uses this title.',
        'Duplicate description.' => 'Another page uses this description.',
    ];
@endphp
<ul class="findings">
    @foreach ($findings as $warning)
        <li>
            <span class="pill {{ $warning['level'] === 'error' ? 'critical' : '' }}">{{ ['error' => 'Needs attention', 'warning' => 'Suggestion', 'info' => 'Information'][$warning['level']] ?? $warning['level'] }}</span>
            @if ($showPage ?? false)
                <strong>{{ $warning['page'] }}</strong>
            @endif
            <span title="{{ $warning['message'] }}">{{ $friendlyMessages[$warning['message']] ?? $warning['message'] }}</span>
        </li>
    @endforeach
</ul>
