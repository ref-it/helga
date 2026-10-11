<div class="p-6 sm:px-8 flex flex-col gap-6 min-h-full">
    <h1 class="text-2xl font-semibold">{{ $plan->title }}</h1>

    <div class="space-y-3">
        @foreach($this->shifts as $shift)
            <flux:card size="sm" wire:key="subscribe-shift-{{ $shift->id }}">
                @if($shift->description)
                    {{-- the summary is the toggle and the description is collapsed by default: several long
                         descriptions would push the sign-up form off the screen --}}
                    <flux:accordion transition>
                        <flux:accordion.item>
                            <flux:accordion.heading>@include('livewire.subscription.partials.shift_summary', ['shift' => $shift])</flux:accordion.heading>
                            <flux:accordion.content>
                                <div class="mt-3 mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('shift.description') }}</div>
                                @include('partials.rich_text', ['html' => $shift->description])
                            </flux:accordion.content>
                        </flux:accordion.item>
                    </flux:accordion>
                @else
                    @include('livewire.subscription.partials.shift_summary', ['shift' => $shift])
                @endif
            </flux:card>
        @endforeach
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
            <flux:phone wire:model="phone" country="DE" :disabled="$phoneFromAccount" />
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
        @if($this->requiresHealthCertificate)
            <flux:field variant="inline" class="col-span-full">
                <flux:checkbox wire:model="health_certificate_confirmed" />
                <flux:label>{{__("subscription.healthCertificateConfirm")}}</flux:label>
                <flux:error name="health_certificate_confirmed" />
            </flux:field>
        @endif
        @if($this->requiresClothingSize)
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

    <div class="-mb-6 mt-auto w-[100cqw] ml-[calc(50%-50cqw)] border-t border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800">
        <div class="mx-auto max-w-7xl py-6 px-6 sm:px-8 flex items-center justify-end gap-x-4">
            <flux:button href="{{ route('plan.show', ['plan' => $plan]) }}" icon="ban">
                {{__('subscription.cancel')}}
            </flux:button>
            <flux:button variant="primary" icon="save" wire:click="save">
                {{__('subscription.save')}}
            </flux:button>
        </div>
    </div>
</div>
