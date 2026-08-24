<div class="p-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ __('plan.heading') }}</h1>
            
    <div class="grid gap-6">
        <flux:field>
            <flux:label>{{__("plan.title")}}</flux:label>
            <flux:input wire:model="title" />
            <flux:error name="title" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("plan.planDesc")}}</flux:label>
            <flux:editor wire:model="description" toolbar="bold italic underline strike | bullet ordered blockquote | link | undo redo" />
            <flux:error name="description" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("plan.contactEmailDesc")}}
                <flux:badge size="sm">{{__("plan.public")}}</flux:badge>
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:input type="email" wire:model="contact_email" />
            <flux:error name="contact_email" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("plan.contactPhoneDesc")}}
                <flux:badge size="sm">{{__("plan.public")}}</flux:badge>
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:input type="tel" wire:model="contact_phone" />
            <flux:error name="contact_phone" />
        </flux:field>
        <flux:field variant="inline">
            <flux:checkbox wire:model="allow_unsubscribe" />
            <flux:label>{{__("plan.allowUnsubscribe")}}</flux:label>
        </flux:field>
        <flux:field variant="inline">
            <flux:checkbox wire:model="show_subscriber_names" />
            <flux:label>{{__("plan.showSubscriberNames")}}</flux:label>
        </flux:field>
    </div>

    <div class="py-6 -mx-8 -mb-8 mt-auto px-8 flex items-center justify-end gap-x-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <flux:button variant="primary" icon="save" wire:click="save">
            {{__('plan.save')}}
        </flux:button>
    </div>
</div>
