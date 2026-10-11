@if ($shift->isSubscribedBy(auth()->user()) && $shift->selfUnsubscribeAllowed())
    <flux:modal.trigger name="unsubscribe-shift-{{ $shift->id }}">
        <flux:button size="sm" icon="ban">
            {{__('subscription.unsubscribe')}}
        </flux:button>
    </flux:modal.trigger>
@endif
