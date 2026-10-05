<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/LWUA_Logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900">
        @if (Auth::user()->hasRole(\App\Enums\Role::Lwd))
            {{-- Water districts: the Figma top header --}}
            <div class="min-h-screen bg-lwua-page">
                @include('layouts.navigation')
                @include('layouts.flash')

                @isset($header)
                    <header class="max-w-7xl mx-auto pt-8 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        @else
            {{-- LWUA offices and the System Admin: left sidebar --}}
            {{--
                Phones/tablets: the sidebar slides over the page (sidebarOpen).
                Desktop: it sits beside the page and can be hidden (sidebarHidden, remembered in this browser).
            --}}
            <div class="min-h-screen bg-lwua-page lg:flex"
                 x-data="{
                     sidebarOpen: false,
                     sidebarHidden: false,
                     isDesktop() { return window.matchMedia('(min-width: 1024px)').matches },
                     showSidebar() { this.isDesktop() ? this.setHidden(false) : this.sidebarOpen = true },
                     hideSidebar() { this.isDesktop() ? this.setHidden(true) : this.sidebarOpen = false },
                     setHidden(value) {
                         this.sidebarHidden = value;
                         try { localStorage.setItem('pms.sidebarHidden', value ? '1' : '0') } catch (e) {}
                     },
                 }"
                 x-init="try { sidebarHidden = localStorage.getItem('pms.sidebarHidden') === '1' } catch (e) {}"
                 x-on:keydown.escape.window="sidebarOpen = false">
                @include('layouts.sidebar')

                <div class="min-w-0 flex-1">
                    {{-- Menu bar: always on phones/tablets; on desktop only while the sidebar is hidden --}}
                    <div class="sticky top-0 z-20 flex items-center gap-3 bg-lwua-navy px-4 py-3"
                         :class="sidebarHidden ? 'lg:flex' : 'lg:hidden'">
                        <button type="button" x-on:click="showSidebar()" class="rounded-md p-2 text-white hover:bg-white/10"
                                aria-label="{{ __('Show menu') }}" title="{{ __('Show menu') }}">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white">
                            <x-application-logo class="h-9 w-9" />
                            <span class="font-semibold">LWUA</span>
                        </a>
                    </div>

                    @include('layouts.flash')

                    @isset($header)
                        <header class="max-w-7xl mx-auto pt-8 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </header>
                    @endisset

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @endif
    </body>
</html>
