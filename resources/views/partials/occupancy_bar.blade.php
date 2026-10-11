@php
    $percentage = $total > 0 ? round($filled / $total * 100) : 0;
@endphp

@if ($total > 0)
    <div class="flex items-center gap-2">
        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
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
        @if(! empty($names))
            {{-- inside a selectable card: sits above the card's overlay and keeps the click from ticking it --}}
            <span class="relative z-10 shrink-0" x-on:click.stop="">
                <flux:dropdown position="top" align="end">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="users"
                        aria-label="{{ __('plan.showSubscribers') }}"
                    />
                    <flux:popover class="p-3">
                        <ul class="space-y-0.5 text-sm">
                            @foreach($names as $name)
                                <li>{{ $name }}</li>
                            @endforeach
                        </ul>
                    </flux:popover>
                </flux:dropdown>
            </span>
        @endif
    </div>
@endif
