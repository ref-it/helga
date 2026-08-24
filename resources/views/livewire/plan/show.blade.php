<div class="p-8 space-y-6">
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
    @endif
</div>
