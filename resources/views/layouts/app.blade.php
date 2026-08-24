<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if(isset($title))
            <title>{{ $title }} | {{ config('app.name', 'Shift Planner') }}</title>
        @else
            <title>{{ config('app.name', 'Shift Planner') }}</title>
        @endif
        @yield('meta')
        @vite('resources/css/app.css')
        @vite('resources/css/theme.css')
        @vite('resources/js/app.js')
        @fluxAppearance(['nonce' => $cspNonce])
    </head>
    <body class="flex w-full h-full">
        @include('partials.sidebar')
        <div class="grid grid-rows-[auto_1fr] w-full h-full min-w-0">
            @include('partials.header')
            <main class="h-full flex-1 min-w-0 overflow-x-hidden overflow-y-auto">
                {{ $slot}}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @livewireScripts
        @fluxScripts(['nonce' => $cspNonce])
    </body>
</html>
