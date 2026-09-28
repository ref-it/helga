@if(file_exists(public_path('logo.svg')) && file_exists(public_path('logo-small.svg')))
    <img class="h-8 w-auto hidden lg:inline" src="{{ asset('logo.svg') }}" alt="{{ config('app.name') }}">
    <img class="h-8 w-auto lg:hidden" src="{{ asset('logo-small.svg') }}" alt="{{ config('app.name') }}">
@else
    <span class="text-zinc-800 dark:text-white text-xl font-semibold">{{ config('app.name') }}</span>
@endif
