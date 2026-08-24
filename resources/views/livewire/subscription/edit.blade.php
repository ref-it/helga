<div class="p-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ $shift->title }}</h1>
    @include('partials.rich_text', ['html' => $shift->description])
    <div class="text-sm text-zinc-500 dark:text-zinc-400">
        {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
    </div>

    <div class="grid gap-6">
        <flux:field>
            <flux:label>{{__("subscription.nameDesc")}}</flux:label>
            <flux:input wire:model="name" />
            <flux:error name="name" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("subscription.email")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:input type="email" wire:model="email" />
            <flux:error name="email" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("subscription.phone")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:input type="tel" wire:model="phone" />
            <flux:error name="phone" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("subscription.comment")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:textarea wire:model="comment" />
            <flux:error name="comment" />
        </flux:field>
        @if(config('app.reminders_enabled'))
            <flux:field variant="inline">
                <flux:checkbox wire:model="notification" />
                <flux:label>{{__("subscription.notifyMe")}}</flux:label>
            </flux:field>
        @endif
    </div>

    <div class="py-6 -mx-8 -mb-8 mt-auto px-8 flex items-center justify-end gap-x-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <flux:button href="{{ route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]) }}" icon="ban">
            {{__('subscription.cancel')}}
        </flux:button>
        <flux:button variant="primary" icon="save" wire:click="save">
            {{__('subscription.update')}}
        </flux:button>
    </div>
</div>
