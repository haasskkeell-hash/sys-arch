<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="auth-page min-h-screen antialiased">
        <div class="auth-stage">
            <div class="auth-content">
                <a href="{{ route('home') }}" class="auth-brand" wire:navigate>
                    <img src="{{ asset('fixtrack-logo.png') }}" alt="" class="auth-brand-mark">
                    <span class="sr-only">{{ config('app.name', 'FixTrack') }}</span>
                </a>
                <div>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
