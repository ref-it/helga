<div class="p-8 space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('plan.myPlans') }}</h1>

    <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('home.searchPlans') }}" clearable />

    <div class="space-y-4">
        <h2 class="text-lg font-semibold">{{ __('plan.ownedPlans') }}</h2>

        @if($owned->isEmpty())
            <flux:callout variant="secondary" icon="info" heading="{{ $search !== '' ? __('home.noSearchResults') : __('plan.noOwnedPlans') }}" />
        @else
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($owned as $plan)
                    <a
                        wire:navigate
                        href="{{route('plan.manage', $plan)}}"
                        class="px-4 py-3 flex items-center gap-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-xs hover:ring-2 focus:ring-2 ring-(--color-accent-content)"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold truncate">{{ $plan->title }}</div>
                            @include('partials.plan_schedule_range')
                        </div>
                        @include('partials.plan_occupancy')
                    </a>
                @endforeach
            </div>

            <flux:pagination :paginator="$owned" />
        @endif
    </div>

    <div class="space-y-4">
        <h2 class="text-lg font-semibold">{{ __('plan.sharedPlans') }}</h2>

        @if($shared->isEmpty())
            <flux:callout variant="secondary" icon="info" heading="{{ $search !== '' ? __('home.noSearchResults') : __('plan.noSharedPlans') }}" />
        @else
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($shared as $plan)
                    <a
                        wire:navigate
                        href="{{route('plan.manage', $plan)}}"
                        class="px-4 py-3 flex items-center gap-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-xs hover:ring-2 focus:ring-2 ring-(--color-accent-content)"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold truncate">{{ $plan->title }}</div>
                            @include('partials.plan_schedule_range')
                        </div>
                        @include('partials.plan_occupancy')
                    </a>
                @endforeach
            </div>

            <flux:pagination :paginator="$shared" />
        @endif
    </div>
</div>
