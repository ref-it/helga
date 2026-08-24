<div class="p-8 space-y-6">
    <div class="flex items-center gap-3">
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            x-on:click="window.history.back()"
        />
        <h1 class="text-2xl font-semibold">{{ $plan->title }}</h1>
    </div>

    <flux:fieldset>
        <legend class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex flex-col gap-1">
                <span>{{ $shift->title }}</span>
                <span class="text-sm text-zinc-500 dark:text-zinc-400 font-normal">
                    {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
                </span>
            </div>

            @if ($shift->team_size > $shift->subscriptions->count() && ! $shift->isSubscribedBy(auth()->user()))
                <flux:button
                    variant="primary"
                    icon="plus"
                    href="{{route('plan.subscription.create', ['plan' => $shift->plan->view_id, 'shift'=> $shift])}}"
                >
                    {{__('plan.subscribe')}}
                </flux:button>
            @endif
            @if ($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
                <flux:modal.trigger name="unsubscribe-shift-{{ $shift->id }}">
                    <flux:button icon="ban">
                        {{__('subscription.unsubscribe')}}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </legend>

        <div class="p-4 space-y-4">
            @if($shift->requires_health_certificate)
                <div>
                    <flux:badge color="amber" icon="circle-alert">{{ __('shift.healthCertificateRequired') }}</flux:badge>
                </div>
            @endif

            @if($shift->description)
                <div class="flex flex-col gap-1">
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.description')}}</flux:text>
                    @include('partials.rich_text', ['html' => $shift->description])
                </div>
            @endif

            <div class="flex flex-col gap-2">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.occupancy')}}</flux:text>
                @php
                    $filledSlots = min($shift->subscriptions->count(), $shift->team_size);
                @endphp
                <div class="flex flex-wrap gap-3 items-center">
                    @include('partials.occupancy_ring', [
                        'total' => $shift->team_size,
                        'filled' => $filledSlots,
                        'title' => __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $shift->team_size]),
                    ])
                    <span>{{ __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $shift->team_size]) }}</span>
                </div>
                @if($plan->subscriberNamesVisibleTo(auth()->user()) && $shift->subscriptions->count() > 0)
                    <div class="flex flex-col">
                        @foreach($shift->subscriptions as $subscription)
                            <span>{{$subscription->name}}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </flux:fieldset>

    @if ($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
        <flux:modal name="unsubscribe-shift-{{ $shift->id }}" class="md:w-[26rem]">
            <flux:heading size="lg" class="modal-header">{{ __('subscription.unsubscribe') }}</flux:heading>
            <div class="space-y-4">
                <flux:text>{{ __('subscription.confirmRemoveHelp') }}{{ $shift->title }}</flux:text>
                <div class="flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('plan.cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="post" action="{{ route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]) }}">
                        @method('delete')
                        @csrf
                        <flux:button type="submit" variant="danger">{{ __('subscription.unsubscribe') }}</flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
