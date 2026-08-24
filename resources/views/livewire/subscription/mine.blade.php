<div class="p-8 space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('subscription.mySubscriptions') }}</h1>

    @if($byPlan->isEmpty())
        <flux:callout variant="secondary" icon="info" heading="{{ __('subscription.noSubscriptions') }}" />
    @else
        <div class="space-y-8">
            @foreach($byPlan as $group)
                @php($plan = $group->first()->shift->plan)
                <flux:fieldset>
                    <legend>
                        <flux:link wire:navigate href="{{ route('plan.show', $plan) }}">
                            {{ $plan->title }}
                        </flux:link>
                    </legend>
                    <div class="p-4 divide-y divide-zinc-200 dark:divide-zinc-700 [&>*:first-child]:pt-0 [&>*:last-child]:pb-0">
                        @foreach($group as $subscription)
                            @php($shift = $subscription->shift)
                            <div class="flex items-center justify-between gap-3 py-3">
                                <div class="flex flex-col gap-1">
                                    <flux:link
                                        wire:navigate
                                        href="{{ route('plan.shift.show', ['plan' => $plan->view_id, 'shift' => $shift]) }}"
                                        class="font-medium"
                                    >
                                        {{ $shift->title }}
                                    </flux:link>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
                                    </div>
                                </div>
                                @if ($shift->selfUnsubscribeAllowed())
                                    <flux:modal.trigger name="unsubscribe-shift-{{ $shift->id }}">
                                        <flux:button size="sm" icon="ban" class="whitespace-nowrap">
                                            {{ __('subscription.unsubscribe') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:fieldset>
            @endforeach
        </div>

        @foreach($byPlan as $group)
            @foreach($group as $subscription)
                @php($shift = $subscription->shift)
                @php($plan = $shift->plan)
                @if ($shift->selfUnsubscribeAllowed())
                    <flux:modal name="unsubscribe-shift-{{ $shift->id }}" class="md:w-[26rem]">
                        <flux:heading size="lg" class="modal-header">{{ __('subscription.unsubscribe') }}</flux:heading>
                        <div class="space-y-4">
                            <flux:text>{{ __('subscription.confirmRemoveHelp') }}{{ $shift->title }}</flux:text>
                            <div class="flex items-center justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('plan.cancel') }}</flux:button>
                                </flux:modal.close>
                                <form method="post" action="{{ route('plan.subscription.unsubscribeSelf', ['plan' => $plan->view_id, 'shift' => $shift]) }}">
                                    @method('delete')
                                    @csrf
                                    <flux:button type="submit" variant="danger">{{ __('subscription.unsubscribe') }}</flux:button>
                                </form>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            @endforeach
        @endforeach
    @endif
</div>
