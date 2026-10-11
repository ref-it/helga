@php
    $percentage = $total > 0 ? round($filled / $total * 100) : 0;
@endphp

@if ($total > 0)
    <div class="flex flex-col gap-1.5">
        <div class="flex items-baseline justify-between gap-2">
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('shift.occupancy') }}</flux:text>
            <flux:text size="sm" class="tabular-nums">{{ $filled }} / {{ $total }}</flux:text>
        </div>
        <div
            class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
            role="progressbar"
            aria-label="{{ $title }}"
            aria-valuemin="0"
            aria-valuemax="{{ $total }}"
            aria-valuenow="{{ $filled }}"
            title="{{ $title }}"
        >
            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $percentage }}%"></div>
        </div>
    </div>
@endif
