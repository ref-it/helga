<div class="p-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ __('shift.editHeading') }}</h1>

    <div class="grid md:grid-cols-2 gap-6">
        <flux:field class="col-span-full">
            <flux:label>{{__("shift.title")}}</flux:label>
            <flux:input wire:model="title" />
            <flux:error name="title" />
        </flux:field>
        <flux:field class="col-span-full">
                <flux:label class="flex items-center gap-2">
                    {{__("shift.description")}}
                    <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
                </flux:label>
            <flux:editor wire:model="description" toolbar="bold italic underline strike | bullet ordered blockquote | link | undo redo" />
            <flux:error name="description" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("shift.startDesc")}}</flux:label>
            <flux:input wire:model="start" type="datetime-local" />
            <flux:error name="start" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("shift.endDesc")}}</flux:label>
            <flux:input wire:model="end" type="datetime-local" />
            <flux:error name="end" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("shift.team_sizeDesc")}}</flux:label>
            <flux:input wire:model="team_size" type="number" />
            <flux:error name="team_size" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("shift.unsubscribeLockHoursDesc")}}</flux:label>
            <flux:input wire:model="unsubscribe_lock_hours" type="number" />
            <flux:error name="unsubscribe_lock_hours" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("shift.type")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:select wire:model="category" variant="combobox">
                <x-slot name="input">
                    <flux:select.input wire:model="searchCategory" placeholder="Start typing..." />
                </x-slot>
                @foreach($shiftCategories as $shiftCategory)
                    <flux:select.option value="{{ $shiftCategory->id }}" :wire:key="$shiftCategory->id">{{ $shiftCategory->name }}</flux:select.option>
                @endforeach
                <flux:select.option.create wire:click="createCategory" min-length="1">
                    Create "<span wire:text="searchCategory"></span>"
                </flux:select.option.create>
            </flux:select>
            <flux:error name="category" />
        </flux:field>
        <flux:field variant="inline" class="col-span-full">
            <flux:checkbox wire:model="requires_health_certificate" />
            <flux:label>{{__("shift.requiresHealthCertificateDesc")}}</flux:label>
        </flux:field>
        <flux:field variant="inline" class="col-span-full">
            <flux:checkbox wire:model="requires_clothing_size" />
            <flux:label>{{__("shift.requiresClothingSizeDesc")}}</flux:label>
        </flux:field>
    </div>
    <div class="py-6 -mx-8 -mb-8 mt-auto px-8 flex items-center justify-end gap-x-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <flux:button
            icon="ban"
            wire:navigate
            href="{{route('plan.manage', $plan)}}"
        >
            {{__('shift.cancel')}}
        </flux:button>
        <flux:button
            variant="primary"
            icon="save"
            wire:click="save"
        >
            {{__('shift.save')}}
        </flux:button>
    </div>
</div>
