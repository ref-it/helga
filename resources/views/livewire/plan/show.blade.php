<div class="p-6 sm:px-8 space-y-6">
    @include('partials.plan_title')

    @can('manage', $plan)
        <flux:button
            icon="pencil"
            wire:navigate
            href="{{ route('plan.manage', ['plan' => $plan]) }}"
        >
            {{__('plan.admin')}}
        </flux:button>
    @endcan

    @if(count($plan->shifts) === 0)
        <flux:callout variant="warning" icon="info" heading="{{__('shift.noshifts')}}" />
    @else
        {{-- one group around every category, so the selection is a single list across all of them --}}
        <flux:checkbox.group wire:model.live="selectedShifts" variant="cards" class="block!">
            <div class="space-y-8">
                @foreach($plan->shifts->groupBy('type') as $type => $shiftsInGroup)
                    @if($type !== '')
                        <flux:fieldset>
                            <legend>{{ $categoryNames[$type] ?? $type }}</legend>
                            <div class="p-4">
                                @include('livewire.plan.partials.public_shift_table', ['shifts' => $shiftsInGroup])
                            </div>
                        </flux:fieldset>
                    @else
                        @include('livewire.plan.partials.public_shift_table', ['shifts' => $shiftsInGroup])
                    @endif
                @endforeach
            </div>
        </flux:checkbox.group>

        @if(count($selectedShifts) > 0)
            {{-- follows the page while shifts are being ticked, so the sign-up stays one click away --}}
            <div class="sticky bottom-0 z-20 -mb-6 w-[100cqw] ml-[calc(50%-50cqw)] border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
                <div class="mx-auto max-w-7xl px-6 sm:px-8 py-4 flex flex-wrap items-center justify-end gap-4">
                    <flux:text class="mr-auto hidden sm:block">
                        {{ trans_choice('plan.shiftsSelected', count($selectedShifts), ['count' => count($selectedShifts)]) }}
                    </flux:text>
                    <flux:button wire:click="clearSelection" icon="x">
                        {{ __('plan.clearSelection') }}
                    </flux:button>
                    <flux:button variant="primary" icon="plus" wire:click="subscribeToSelected">
                        {{ __('plan.subscribe') }}
                    </flux:button>
                </div>
            </div>
        @endif
    @endif
</div>
