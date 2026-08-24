<div class="space-y-3">
    @foreach($shifts as $shift)
        <flux:card size="sm" class="relative flex flex-col gap-5 md:flex-row md:flex-wrap md:items-start md:gap-x-6 md:gap-y-5" wire:key="admin-shift-card-{{ $shift->id }}">
            <div class="flex flex-col gap-5 md:w-64 md:flex-none pr-10 md:pr-0">
                <div class="flex flex-col gap-2">
                    <flux:link
                        wire:navigate
                        href="{{ route('plan.shift.show', ['plan' => $shift->plan->view_id, 'shift' => $shift]) }}"
                        class="font-medium"
                    >
                        {{$shift->title}}
                    </flux:link>
                    @if($shift->requires_health_certificate)
                        <div>
                            <flux:badge size="sm" color="yellow" icon="circle-alert">{{ __('shift.healthCertificateRequired') }}</flux:badge>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-1 lg:hidden">
                    <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.schedule')}}</flux:text>
                    <div>{!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}</div>
                </div>
            </div>

            <div class="hidden lg:flex flex-col gap-1 lg:w-64 lg:flex-none">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.schedule')}}</flux:text>
                <div>{!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}</div>
            </div>

            <div class="flex flex-col gap-1 shrink-0">
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{__('shift.occupancy')}}</flux:text>
                @php
                    $filledSlots = min($shift->subscriptions->count(), $shift->team_size);
                @endphp
                @include('partials.occupancy_ring', [
                    'total' => $shift->team_size,
                    'filled' => $filledSlots,
                    'title' => __('plan.slotsOccupied', ['filled' => $filledSlots, 'total' => $shift->team_size]),
                ])
            </div>

            <div class="absolute top-4 right-4 md:static md:shrink-0 md:ml-auto">
                @include('livewire.plan.partials.admin_shift_menu', ['shift' => $shift, 'plan' => $plan])
            </div>
        </flux:card>
    @endforeach
</div>

@foreach($shifts as $shift)
    @can('forceDelete', $shift)
        <flux:modal name="delete-shift-{{ $shift->id }}" class="md:w-[26rem]">
            <flux:heading size="lg" class="modal-header">{{ __('shift.delete') }}</flux:heading>
            <div class="space-y-4">
                <flux:text>{{ __('shift.confirmDelete') }}</flux:text>
                <div class="flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('plan.cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="post" action="{{route('plan.shift.destroy', ['plan' => $plan, 'shift' => $shift])}}">
                        @method('delete')
                        @csrf
                        <flux:button type="submit" variant="danger">{{ __('shift.delete') }}</flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    @endcan
@endforeach
