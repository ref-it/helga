@php
    $namesVisible = $plan->subscriberNamesVisibleTo(auth()->user());
@endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($shifts as $shift)
        @if($shift->isOpenFor(auth()->user()))
            {{-- ticking several shifts signs up for all of them at once, see the bar at the bottom of the plan --}}
            <flux:checkbox
                variant="cards"
                value="{{ $shift->id }}"
                class="items-start"
                aria-label="{{ __('plan.selectShift', ['shift' => $shift->title]) }}"
                wire:key="public-shift-card-{{ $shift->id }}"
            >
                <flux:checkbox.indicator class="mt-px" />
                @include('livewire.plan.partials.public_shift_card_content', ['shift' => $shift, 'plan' => $plan, 'namesVisible' => $namesVisible])
            </flux:checkbox>
        @else
            <flux:card size="sm" class="flex items-start gap-3" wire:key="public-shift-card-{{ $shift->id }}">
                <flux:checkbox variant="default" disabled class="mt-px" aria-label="{{ __('plan.selectShift', ['shift' => $shift->title]) }}" />
                @include('livewire.plan.partials.public_shift_card_content', ['shift' => $shift, 'plan' => $plan, 'namesVisible' => $namesVisible])
            </flux:card>
        @endif
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
