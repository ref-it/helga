@if ($shift->team_size > $shift->subscriptions->count() && ! $shift->isSubscribedBy(auth()->user()))
    <div class="w-full xl:hidden">
        <flux:button
            variant="primary"
            icon="plus"
            href="{{route('plan.subscription.create', ['plan' => $shift->plan->view_id, 'shift'=> $shift])}}"
            class="w-full justify-center"
        >
            {{__('plan.subscribe')}}
        </flux:button>
    </div>
    <div class="hidden xl:block">
        <flux:button
            variant="primary"
            size="sm"
            icon="plus"
            href="{{route('plan.subscription.create', ['plan' => $shift->plan->view_id, 'shift'=> $shift])}}"
            class="whitespace-nowrap"
        >
            {{__('plan.subscribe')}}
        </flux:button>
    </div>
@endif
@if ($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
    <div class="w-full xl:hidden">
        <flux:modal.trigger name="unsubscribe-shift-{{ $shift->id }}">
            <flux:button icon="ban" class="w-full justify-center">
                {{__('subscription.unsubscribe')}}
            </flux:button>
        </flux:modal.trigger>
    </div>
    <div class="hidden xl:block">
        <flux:modal.trigger name="unsubscribe-shift-{{ $shift->id }}">
            <flux:button size="sm" icon="ban">
                {{__('subscription.unsubscribe')}}
            </flux:button>
        </flux:modal.trigger>
    </div>
@endif
