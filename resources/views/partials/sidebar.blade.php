{{--
    a logged-out visitor only ever gets the "create plan" entry below, which
    the home page offers as a button anyway - so on wide screens the sidebar
    is dropped for them entirely and the header carries the brand instead
--}}
<div @class(['lg:hidden' => auth()->guest()])>
    <flux:sidebar collapsible="mobile" class="w-[18rem]! p-0! flex flex-col gap-0! h-full grow bg-zinc-100 dark:bg-zinc-800">
        <flux:sidebar.header class="flex h-[4rem] pr-3 shrink-0 items-center bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 border-r lg:border-r-0 border-r-zinc-300  dark:border-r-zinc-700 z-10">
            <a wire:navigate href="/" class="h-full flex flex-1 items-center justify-start lg:justify-center px-6">
                @include('partials.brand')
            </a>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav class="grow overflow-y-auto border-r border-zinc-200 dark:border-zinc-700 px-6 py-4">
            {{--
                no wire:navigate here on purpose - a logged-out visitor gets
                redirected through this route to the external OIDC provider,
                and wire:navigate's fetch()-based navigation can't follow a
                cross-origin redirect (the browser blocks it via CORS), so
                the click would silently do nothing
            --}}
            <flux:sidebar.item
                icon="plus"
                href="{{route('plan.create')}}"
            >
                {{__('home.createPlan')}}
            </flux:sidebar.item>
            @auth
                <flux:sidebar.item
                    icon="calendar-days"
                    wire:navigate
                    href="{{ route('subscription.mine') }}"
                >
                    {{ __('subscription.mySubscriptions') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="folder"
                    wire:navigate
                    href="{{ route('plan.mine') }}"
                >
                    {{ __('plan.myPlans') }}
                </flux:sidebar.item>
                @if(auth()->user()->isGlobalAdmin())
                    <flux:sidebar.item
                        icon="folders"
                        wire:navigate
                        href="{{ route('plan.admin_all') }}"
                    >
                        {{ __('plan.adminPlans') }}
                    </flux:sidebar.item>
                @endif
            @endauth
        </flux:sidebar.nav>
    </flux:sidebar>
</div>