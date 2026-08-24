<div class="p-8">
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="space-y-4 flex-1 min-w-0">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ $plan->title }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap shrink-0">
                @can('create', [\App\Models\Shift::class, $plan])
                    <flux:button
                        variant="primary"
                        icon="list-plus"
                        wire:navigate
                        href="{{route('plan.shift.create', $plan)}}"
                    >
                        {{__('shift.add')}}
                    </flux:button>
                @endcan
                @can('manage', $plan)
                    <flux:dropdown>
                        <flux:button icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                icon="pencil"
                                wire:navigate
                                href="{{route('plan.edit', ['plan' => $plan])}}"
                            >
                                {{__('plan.edit')}}
                            </flux:menu.item>
                            @can('share', $plan)
                                <flux:modal.trigger name="share">
                                    <flux:menu.item icon="share-2">
                                        {{__('plan.share')}}
                                    </flux:menu.item>
                                </flux:modal.trigger>
                            @endcan

                            <flux:menu.separator />

                            @if(count($plan->shifts) > 0)
                                <flux:menu.item
                                    icon="printer"
                                    href="{{ route('plan.export.pdf', ['plan' => $plan]) }}"
                                >
                                    {{__('plan.exportPdf')}}
                                </flux:menu.item>
                            @endif
                            <flux:menu.item
                                icon="download"
                                href="{{ route('plan.export', ['plan' => $plan]) }}"
                            >
                                {{__('plan.export')}}
                            </flux:menu.item>
                            <flux:menu.item
                                icon="file-output"
                                href="{{ route('plan.export', ['plan' => $plan, 'template' => 1]) }}"
                            >
                                {{__('plan.exportTemplate')}}
                            </flux:menu.item>
                            <flux:modal.trigger name="import">
                                <flux:menu.item icon="upload">
                                    {{__('plan.import')}}
                                </flux:menu.item>
                            </flux:modal.trigger>
                        </flux:menu>
                    </flux:dropdown>
                @endcan
            </div>
        </div>

        @if(count($plan->shifts) > 0)
            <flux:callout
                :variant="$plan->active ? 'success' : 'warning'"
                icon="{{ $plan->active ? 'eye' : 'eye-off' }}"
                heading="{{ $plan->active ? __('plan.activeHelp') : __('plan.inactiveHelp') }}"
            >
                <x-slot name="actions" class="flex-wrap">
                    @if($plan->active)
                        <flux:button
                            icon="external-link"
                            href="{{ route('plan.show', ['plan' => $plan]) }}"
                        >
                            {{__('plan.linksEmailPlan')}}
                        </flux:button>
                    @endif
                    @can('update', $plan)
                        @if($plan->active)
                            <flux:button
                                icon="{{ $plan->published ? 'eye-off' : 'eye' }}"
                                wire:click="togglePublished"
                            >
                                {{ $plan->published ? __('plan.unpublish') : __('plan.publish') }}
                            </flux:button>
                        @endif
                        <flux:button
                            icon="{{ $plan->active ? 'eye-off' : 'eye' }}"
                            wire:click="toggleActive"
                        >
                            {{ $plan->active ? __('plan.deactivate') : __('plan.activate') }}
                        </flux:button>
                    @endcan
                </x-slot>
            </flux:callout>
        @endif

        @if(count($plan->shifts) === 0)
            <flux:callout variant="warning" icon="circle-alert" heading="{{__('shift.noshifts')}}" />
        @else
            <div class="space-y-8">
                @foreach($plan->shifts->groupBy('type') as $type => $shiftsInGroup)
                    @if($type !== '')
                        <flux:fieldset>
                            <legend>{{ $categoryNames[$type] ?? $type }}</legend>
                            <div class="p-4">
                                @include('livewire.plan.partials.admin_shift_table', ['shifts' => $shiftsInGroup])
                            </div>
                        </flux:fieldset>
                    @else
                        @include('livewire.plan.partials.admin_shift_table', ['shifts' => $shiftsInGroup])
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{--
        rendered outside the .space-y-6 container on purpose - as siblings
        inside it, these modals would each pick up a space-y top margin of
        their own, leaving a visible empty gap at the bottom of the page
        since they render nothing until opened
    --}}
    @can('manage', $plan)
        <flux:modal name="import" class="md:w-[40rem]">
            <flux:heading size="lg" class="modal-header">{{ __('plan.import') }}</flux:heading>
            <div class="space-y-4">
                <flux:callout icon="info" heading="{{ __('plan.importHelp') }}" />
                <form id="importPlanForm" method="post" action="{{route('plan.import', ['plan' => $plan])}}" enctype="multipart/form-data">
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
    @endcan

    @can('share', $plan)
        <flux:modal name="share" class="md:w-[40rem]">
            <div class="space-y-6">
                <flux:heading size="lg" class="modal-header">{{ __('plan.share') }}</flux:heading>

                @if($plan->active)
                    <div class="space-y-3">
                        <flux:heading size="lg">{{ __('plan.publicLink') }}</flux:heading>
                        <flux:input readonly copyable value="{{ route('plan.show', ['plan' => $plan]) }}" />
                    </div>
                @endif

                <div class="space-y-3">
                    <flux:heading size="lg">{{ __('plan.sharedGroups') }}</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('plan.sharedGroupsHelp') }}</p>

                    <flux:pillbox
                        wire:model.live="manageGroupsInput"
                        placeholder="{{ __('plan.groupPlaceholder') }}"
                        empty="plan.noGroupsAvailable"
                        multiple
                        searchable
                        clearable
                    >
                        @foreach($availableGroups as $group)
                            <flux:pillbox.option value="{{ $group }}">{{ $group }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                </div>

                <div class="space-y-3">
                    <flux:heading size="lg">{{ __('plan.readOnlyGroups') }}</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('plan.readOnlyGroupsHelp') }}</p>

                    <flux:pillbox
                        wire:model.live="readGroupsInput"
                        placeholder="{{ __('plan.groupPlaceholder') }}"
                        empty="plan.noGroupsAvailable"
                        multiple
                        searchable
                        clearable
                    >
                        @foreach($availableGroups as $group)
                            <flux:pillbox.option value="{{ $group }}">{{ $group }}</flux:pillbox.option>
                        @endforeach
                    </flux:pillbox>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
