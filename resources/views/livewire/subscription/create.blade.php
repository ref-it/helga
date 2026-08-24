<div class="p-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ $shift->title }}</h1>
    @include('partials.rich_text', ['html' => $shift->description])
    <div class="text-base">
        {!! \App\Http\Controllers\PlanController::buildDateString($shift->start, $shift->end) !!}
    </div>

    @guest
        <flux:callout icon="info" heading="{{ __('subscription.loginAutofillHelp') }}">
            <x-slot name="actions">
                <flux:button icon="log-in" href="{{ route('login') }}">
                    {{ __('common.login') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endguest

    <div class="grid sm:grid-cols-2 gap-6">
        <flux:field class="col-span-full">
            <flux:label>{{__("subscription.nameDesc")}}</flux:label>
            <flux:input wire:model="name" :disabled="auth()->check()" />
            <flux:error name="name" />
        </flux:field>
        <flux:field>
            <flux:label>{{__("subscription.email")}}</flux:label>
            <flux:input type="email" wire:model="email" :disabled="auth()->check()" />
            @guest
                <flux:description>{{__('subscription.emailVerifyHelpRequired')}}</flux:description>
            @endguest
            <flux:error name="email" />
        </flux:field>
        <flux:field>
            <flux:label class="flex items-center gap-2">
                {{__("subscription.phone")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:input type="tel" wire:model="phone" :disabled="$phoneFromAccount" />
            <flux:error name="phone" />
        </flux:field>
        <flux:field class="col-span-full">
            <flux:label class="flex items-center gap-2">
                {{__("subscription.comment")}}
                <flux:badge size="sm">{{__("shift.optional")}}</flux:badge>
            </flux:label>
            <flux:textarea wire:model="comment" />
            <flux:error name="comment" />
        </flux:field>
        @if(config('app.reminders_enabled'))
            <flux:field variant="inline" class="col-span-full">
                <flux:checkbox wire:model="notification" />
                <flux:label>{{__("subscription.notifyMe")}}</flux:label>
            </flux:field>
        @endif
        @if($shift->requires_health_certificate)
            <flux:field variant="inline" class="col-span-full">
                <flux:checkbox wire:model="health_certificate_confirmed" />
                <flux:label>{{__("subscription.healthCertificateConfirm")}}</flux:label>
                <flux:error name="health_certificate_confirmed" />
            </flux:field>
        @endif
        @if($shift->requires_clothing_size)
            <flux:field>
                <flux:label>{{__("subscription.clothingSize")}}</flux:label>
                <flux:select wire:model="clothing_size" variant="listbox" placeholder="{{__('subscription.clothingSizeChoose')}}">
                    <flux:select.option value="S">S</flux:select.option>
                    <flux:select.option value="M">M</flux:select.option>
                    <flux:select.option value="L">L</flux:select.option>
                    <flux:select.option value="XL">XL</flux:select.option>
                    <flux:select.option value="XXL">XXL</flux:select.option>
                </flux:select>
                <flux:error name="clothing_size" />
            </flux:field>
        @endif
        @guest
            <flux:field class="col-span-full">
                <flux:label>{{__("subscription.captcha")}}</flux:label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <img src="{{ $captchaUrl }}" alt="{{__('subscription.captcha')}}" class="rounded border border-zinc-200 dark:border-zinc-700">
                    <flux:button wire:click="refreshCaptcha" icon="rotate-ccw">{{__('subscription.captchaRefresh')}}</flux:button>
                </div>
                <flux:input wire:model="captcha" autocomplete="off" />
                <flux:error name="captcha" />
            </flux:field>
        @endguest
    </div>

    <div class="py-6 -mx-8 -mb-8 mt-auto px-8 flex items-center justify-end gap-x-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <flux:button href="{{ route('plan.show', ['plan' => $plan]) }}" icon="ban">
            {{__('subscription.cancel')}}
        </flux:button>
        <flux:button variant="primary" icon="save" wire:click="save">
            {{__('subscription.save')}}
        </flux:button>
    </div>
</div>
