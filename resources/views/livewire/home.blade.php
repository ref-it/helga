<div class="p-8 space-y-6">
    <div class="flex flex-col md:flex-row gap-6">
        <h1 class="text-2xl font-semibold">{{ __('home.Shiftplan') }}</h1>
        <div class="flex gap-2 md:ml-auto">
            <flux:button variant="primary" icon="plus" href="{{route('plan.create')}}">
                {{__('home.createPlan')}}
            </flux:button>
            @auth
                <flux:modal.trigger name="import">
                    <flux:button icon="import" id="openImportButton" href="#">
                        {{__('plan.import')}}
                    </flux:button>
                </flux:modal.trigger>
            @endauth
        </div>
    </div>

    @if(config('app.plan_cleanup_enabled'))
        <flux:callout variant="warning" icon="info" heading="{{ __('home.deleteInfo', ['days' => config('app.plan_cleanup_days')]) }}" />
    @endif

    <flux:input wire:model.live.debounce.300ms="search" icon="search" placeholder="{{ __('home.searchPlans') }}" clearable />

    @if(count($plans) === 0)
        <flux:callout variant="secondary" icon="info" heading="{{ $search !== '' ? __('home.noSearchResults') : __('home.noPublicPlans') }}" />
    @else
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($plans as $plan)
                <a
                    wire:navigate
                    href="{{route('plan.show', $plan)}}"
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
    @endif

    @auth
        <flux:modal name="import" class="md:w-[40rem]">
            <flux:heading size="lg" class="modal-header">{{ __('plan.import') }}</flux:heading>
            <div class="space-y-4">
                <flux:callout variant="secondary" icon="info" heading="{{ __('plan.importHelp') }}" />
                <form id="importPlanForm" method="post" action="{{route('plan.import')}}" enctype="multipart/form-data">
                    @csrf
                    <input
                        type="file"
                        id="import"
                        name="import"
                        accept="text/csv"
                        class="border border-zinc-200 dark:border-zinc-700 rounded-md p-2 w-full h-[10rem]"
                    />
                </form>
            </div>
        </flux:modal>
    @endauth
</div>
