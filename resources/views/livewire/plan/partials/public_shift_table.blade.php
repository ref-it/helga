<div class="space-y-3">
    @php
        $namesVisible = $plan->subscriberNamesVisibleTo(auth()->user());
    @endphp
    @foreach($shifts as $shift)
        <flux:card size="sm" class="flex flex-col gap-5 md:flex-row md:flex-wrap md:items-start md:gap-x-6 md:gap-y-5" wire:key="public-shift-card-{{ $shift->id }}">
            <div class="flex flex-col gap-5 md:w-64 md:flex-none">
                <div class="flex flex-col gap-3">
                    <flux:link
                        wire:navigate
                        href="{{ route('plan.shift.show', ['plan' => $shift->plan->view_id, 'shift' => $shift]) }}"
                        class="font-medium"
                    >
                        {{$shift->title}}
                    </flux:link>
                    @if($shift->requires_health_certificate)
                        <div>
                            <flux:badge size="sm" color="yellow" icon="circle-alert">{{ __('shift.healthCertificateRequired') }}</flux:badge>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-1 lg:hidden">
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.schedule')}}</flux:text>
                    <div>{!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}</div>
                </div>
            </div>

            <div class="hidden lg:flex flex-col gap-1 lg:w-64 lg:flex-none">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.schedule')}}</flux:text>
                <div>{!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}</div>
            </div>

            <div class="flex flex-col gap-2 shrink-0">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.occupancy')}}</flux:text>
                <div class="flex flex-wrap gap-3 items-center">
                    @php
                        $filledSlots = min($shift->subscriptions->count(), $shift->team_size);
                    @endphp
                    @include('partials.occupancy_ring', [
                        'total' => $shift->team_size,
                        'filled' => $filledSlots,
                        'title' => __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $shift->team_size]),
                    ])
                    @if($namesVisible && $shift->subscriptions->count() > 0)
                        <div class="flex flex-col">
                            @foreach($shift->subscriptions as $subscription)
                                <span>{{$subscription->name}}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex gap-2 flex-wrap w-full xl:w-auto xl:shrink-0 xl:ml-auto">
                @include('livewire.plan.partials.public_shift_actions', ['shift' => $shift, 'plan' => $plan])
            </div>
        </flux:card>
    @endforeach
</div>

@foreach($shifts as $shift)
    @if ($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
        <flux:modal name="unsubscribe-shift-{{ $shift->id }}" class="md:w-[26rem]">
            <flux:heading size="lg" class="modal-header">{{ __('subscription.unsubscribe') }}</flux:heading>
            <div class="space-y-8">
                <flux:text>{{ __('subscription.confirmRemoveHelp') }}{{ $shift->title }}</flux:text>
                <div class="flex items-center justify-end gap-4">
                    <flux:modal.close>
                        <flux:button>{{ __('plan.cancel') }}</flux:button>
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
@endforeach
