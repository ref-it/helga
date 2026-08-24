@php
    $percentage = $total > 0 ? round($filled / $total * 100) : 0;
@endphp

@if ($total > 0)
    <svg viewBox="-3 -3 42 42" class="size-9 shrink-0">
        <title>{{ $title }}</title>
        <path
            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            fill="none"
            class="stroke-zinc-300 dark:stroke-zinc-600"
            stroke-width="6"
        />
        @if ($percentage > 0)
            <path
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                fill="none"
                class="stroke-emerald-500"
                stroke-width="6"
                stroke-linecap="round"
                stroke-dasharray="{{ $percentage }}, 100"
            />
        @endif
    </svg>
    <span class="sr-only">{{ $title }}</span>
@endif
