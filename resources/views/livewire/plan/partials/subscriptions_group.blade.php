<div class="space-y-8">
    @foreach($shifts as $shift)
        <flux:fieldset class="min-w-0">
            <legend class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex flex-col gap-1">
                    <div>{{ $shift->title }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400 font-normal">
                        @include('partials.compact_date_range', ['start' => $shift->start, 'end' => $shift->end])
                    </div>
                </div>
                <flux:badge :color="$shift->subscriptions->count() < $shift->team_size ? 'red' : 'green'">
                    {{ $shift->subscriptions->count() }} / {{ $shift->team_size }}
                </flux:badge>
            </legend>
            <div class="p-4">
                @if($shift->subscriptions->isEmpty())
                    <flux:callout variant="warning" icon="info" heading="{{ __('plan.noSubscriptions') }}" />
                @else
                    <div class="overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{__('subscription.name')}}</flux:table.column>
                                <flux:table.column>{{__('subscription.comment')}}</flux:table.column>
                                <flux:table.column>{{__('shift.action')}}</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($shift->subscriptions as $subscription)
                                    <flux:table.row :key="$subscription->id">
                                        <flux:table.cell>
                                            <div class="flex flex-col gap-2">
                                                <div class="flex items-center gap-3">
                                                    <span>{{ $subscription->name }}</span>
                                                    <flux:dropdown position="bottom" align="center">
                                                        <flux:button size="sm" variant="ghost" icon="mail" />
                                                        <flux:popover>
                                                            <flux:link href="mailto:{{ $subscription->email }}">{{ $subscription->email }}</flux:link>
                                                        </flux:popover>
                                                    </flux:dropdown>
                                                    @if($subscription->phone)
                                                        <flux:dropdown position="bottom" align="center">
                                                            <flux:button size="sm" variant="ghost" icon="phone" />
                                                            <flux:popover>
                                                                <flux:link href="tel:{{ str_replace(' ', '', $subscription->phone) }}">{{ $subscription->phone }}</flux:link>
                                                            </flux:popover>
                                                        </flux:dropdown>
                                                    @endif
                                                </div>
                                                @if($shift->requires_health_certificate)
                                                    <div>
                                                        <flux:badge
                                                            size="sm"
                                                            :color="$subscription->health_certificate_confirmed ? 'green' : 'red'"
                                                            :icon="$subscription->health_certificate_confirmed ? 'shield-check' : 'shield-alert'"
                                                        >
                                                            {{ $subscription->health_certificate_confirmed ? __('subscription.healthCertificateConfirmed') : __('subscription.healthCertificateNotConfirmed') }}
                                                        </flux:badge>
                                                    </div>
                                                @endif
                                                @if($shift->requires_clothing_size)
                                                    <div>
                                                        <flux:badge size="sm" icon="shirt">
                                                            {{ __('subscription.clothingSize') }}: {{ $subscription->clothing_size ?? '–' }}
                                                        </flux:badge>
                                                    </div>
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $subscription->comment }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex gap-2 justify-end">
                                                @canany(['update', 'forceDelete'], $subscription)
                                                    <flux:dropdown>
                                                        <flux:button size="sm" icon="ellipsis-vertical" />
                                                        <flux:menu>
                                                            @can('update', $subscription)
                                                                <flux:menu.item
                                                                    icon="pencil"
                                                                    wire:navigate
                                                                    href="{{ route('plan.shift.subscription.edit', ['plan' => $plan, 'shift' => $shift, 'subscription' => $subscription]) }}"
                                                                >
                                                                    {{ __('subscription.edit') }}
                                                                </flux:menu.item>
                                                            @endcan
                                                            @can('forceDelete', $subscription)
                                                                <flux:modal.trigger name="delete-subscription-{{ $subscription->id }}">
                                                                    <flux:menu.item variant="danger" icon="trash-2">
                                                                        {{ __('subscription.unsubscribe') }}
                                                                    </flux:menu.item>
                                                                </flux:modal.trigger>
                                                            @endcan
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endcanany
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    @foreach($shift->subscriptions as $subscription)
                        @can('forceDelete', $subscription)
                            <flux:modal name="delete-subscription-{{ $subscription->id }}" class="md:w-[26rem]">
                                <flux:heading size="lg" class="modal-header">{{ __('subscription.unsubscribe') }}</flux:heading>
                                <div class="space-y-4">
                                    <flux:text>{{ __('subscription.confirmDelete') }}</flux:text>
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('plan.cancel') }}</flux:button>
                                        </flux:modal.close>
                                        <form method="post" action="{{ route('plan.shift.subscription.destroy', ['plan' => $plan, 'shift' => $shift, 'subscription' => $subscription]) }}">
                                            @method('delete')
                                            @csrf
                                            <flux:button type="submit" variant="danger">{{ __('subscription.unsubscribe') }}</flux:button>
                                        </form>
                                    </div>
                                </div>
                            </flux:modal>
                        @endcan
                    @endforeach
                @endif
            </div>
        </flux:fieldset>
    @endforeach
</div>
