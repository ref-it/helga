@canany(['update', 'forceDelete'], $shift)
    <flux:dropdown>
        <flux:button size="sm" icon="ellipsis-vertical" />
        <flux:menu>
            <flux:menu.item
                icon="users"
                wire:navigate
                href="{{ route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]) }}"
            >
                {{ __('plan.show_subscriptions') }}
            </flux:menu.item>
            @can('update', $shift)
                <flux:menu.item
                    icon="pencil"
                    wire:navigate
                    href="{{ route('plan.shift.edit', ['plan' => $plan, 'shift' => $shift]) }}"
                >
                    {{ __('shift.edit') }}
                </flux:menu.item>
            @endcan
            @can('forceDelete', $shift)
                <flux:modal.trigger name="delete-shift-{{ $shift->id }}">
                    <flux:menu.item variant="danger" icon="trash-2">
                        {{ __('shift.delete') }}
                    </flux:menu.item>
                </flux:modal.trigger>
            @endcan
        </flux:menu>
    </flux:dropdown>
@endcanany
