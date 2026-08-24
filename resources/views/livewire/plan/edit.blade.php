<div class="p-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ __('plan.edit') }}</h1>

    <div class="grid grid-cols-2 gap-6">
        <flux:field class="col-span-full">
            <flux:label>{{__("plan.title")}}</flux:label>
            <flux:input wire:model="title" />
            <flux:error name="title" />
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label>{{__("plan.planDesc")}}</flux:label>
            <flux:editor wire:model="description" toolbar="bold italic underline strike | bullet ordered blockquote | link | undo redo" />
            <flux:error name="description" />
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label>{{__("plan.mailDesc")}}</flux:label>
            <flux:input type="email" wire:model="owner_email" disabled />
            <flux:error name="owner_email" />
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
        <flux:field variant="inline" class="col-span-full">
            <flux:checkbox wire:model="allow_unsubscribe" />
            <flux:label>{{__("plan.allowUnsubscribe")}}</flux:label>
        </flux:field>
        <flux:field variant="inline" class="col-span-full">
            <flux:checkbox wire:model="show_subscriber_names" />
            <flux:label>{{__("plan.showSubscriberNames")}}</flux:label>
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label class="flex items-center gap-2">
                {{__("plan.logoDesc")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>

            @if ($newLogo && $newLogo->isPreviewable())
                <div class="flex items-center gap-4 mb-3">
                    <img src="{{ $newLogo->temporaryUrl() }}" alt="" class="h-16 w-16 object-contain rounded border border-zinc-200 dark:border-zinc-700 bg-white">
                    <flux:button size="sm" variant="ghost" icon="trash-2" wire:click="removeLogo">{{__('plan.logoRemove')}}</flux:button>
                </div>
            @elseif ($existingLogo)
                <div class="flex items-center gap-4 mb-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogo) }}" alt="" class="h-16 w-16 object-contain rounded border border-zinc-200 dark:border-zinc-700 bg-white">
                    <flux:button size="sm" variant="ghost" icon="trash-2" wire:click="removeLogo">{{__('plan.logoRemove')}}</flux:button>
                </div>
            @endif

            <flux:file-upload wire:model="newLogo" accept="image/*">
                <flux:file-upload.dropzone
                    heading="{{__('plan.logoUploadHeading')}}"
                    text="{{__('plan.logoUploadText')}}"
                />
            </flux:file-upload>
            <flux:error name="newLogo" />
        </flux:field>
    </div>

    @can('forceDelete', $plan)
        <flux:callout variant="danger" icon="triangle-alert" heading="{{ __('plan.dangerZone') }}">
            <x-slot name="actions">
                <flux:modal.trigger name="delete-plan">
                    <flux:button variant="danger" icon="trash-2">{{ __('plan.delete') }}</flux:button>
                </flux:modal.trigger>
            </x-slot>
        </flux:callout>

        <flux:modal name="delete-plan" class="md:w-[26rem]">
            <flux:heading size="lg" class="modal-header">{{ __('plan.delete') }}</flux:heading>
            <div class="space-y-4">
                <flux:text>{{ __('plan.confirmDelete') }}</flux:text>
                <div class="flex items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('plan.cancel') }}</flux:button>
                    </flux:modal.close>
                    <form method="post" action="{{ route('plan.destroy', ['plan' => $plan]) }}">
                        @method('delete')
                        @csrf
                        <flux:button type="submit" variant="danger">{{ __('plan.delete') }}</flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    @endcan

    <div class="py-6 -mx-8 -mb-8 mt-auto px-8 flex items-center justify-end gap-x-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <flux:button href="{{ route('plan.manage', $plan) }}" icon="ban">
            {{__('plan.cancel')}}
        </flux:button>
        <flux:button variant="primary" icon="save" wire:click="save">
            {{__('plan.save')}}
        </flux:button>
    </div>
</div>
