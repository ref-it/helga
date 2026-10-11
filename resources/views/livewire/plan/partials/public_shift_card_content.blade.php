<div class="flex min-w-0 flex-1 flex-col gap-3">
    <flux:link
        wire:navigate
        href="{{ route('plan.shift.show', ['plan' => $shift->plan->view_id, 'shift' => $shift]) }}"
        class="relative z-10 self-start font-medium"
        {{-- the checkbox card paints an overlay (::after) above its content, so links and buttons sit above it
             (z-10) and stop the click, which must not toggle the card --}}
        x-on:click.stop=""
    >
        {{ $shift->title }}
    </flux:link>

    <div class="text-sm">{!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}</div>

    @if($shift->requires_health_certificate)
        <div>
            <flux:badge size="sm" color="yellow" icon="circle-alert">{{ __('shift.healthCertificateRequired') }}</flux:badge>
        </div>
    @endif

    @php
        $filledSlots = min($shift->subscriptions->count(), $shift->team_size);
    @endphp
    @include('partials.occupancy_bar', [
        'total' => $shift->team_size,
        'filled' => $filledSlots,
        'title' => __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $shift->team_size]),
        'names' => $namesVisible ? $shift->subscriptions->pluck('name')->all() : [],
    ])

    @if($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
        <div class="relative z-10 flex flex-wrap gap-2" x-on:click.stop="">
            @include('livewire.plan.partials.public_shift_actions', ['shift' => $shift, 'plan' => $plan])
        </div>
    @endif
</div>
