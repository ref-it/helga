<div class="p-8 space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('subscription.unsubscribeConfirmation') }}</h1>
    <p>
        {{ __('subscription.confirmRemoveHelp') }} {{ $shift->title }}:
        {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
    </p>

    <div class="flex items-center gap-4">
        <flux:button href="{{ route('plan.show', $plan) }}" variant="ghost">
            {{ __('plan.cancel') }}
        </flux:button>
        <flux:button variant="primary" icon="send" wire:click="confirm">
            {{ __('plan.submit') }}
        </flux:button>
    </div>
</div>
