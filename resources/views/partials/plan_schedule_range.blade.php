@php
    $rangeStart = $plan->firstShiftStart();
    $rangeEnd = $plan->lastShiftEnd();
@endphp

@if ($rangeStart && $rangeEnd)
    <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
        @include('partials.compact_date_range', ['start' => $rangeStart, 'end' => $rangeEnd])
    </div>
@endif
