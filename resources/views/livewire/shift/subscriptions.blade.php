<div class="p-8 space-y-6">
    <div class="flex items-center gap-3">
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            wire:navigate
            href="{{ route('plan.manage', $plan) }}"
        />
        <h1 class="text-2xl font-semibold">{{ $plan->title }}</h1>
    </div>

    @include('livewire.plan.partials.subscriptions_group', ['shifts' => collect([$shift])])
</div>
