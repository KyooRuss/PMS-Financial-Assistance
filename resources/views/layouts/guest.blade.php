{{--
    Layout for the pages shown before entering the system: login, set your password, confirm password, verify email.
    The whole LWUA building photo sits on the right and fades into the background behind the seal, the title and the card.
    Sizes scale with the window height, so the login page fits on one screen without scrolling.
    Optional slot: $title (browser tab title).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/LWUA_Logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white">
        {{-- --gutter: space left of the card on large screens; --card: the card's width. The photo lines up with both. --}}
        <div class="relative min-h-dvh overflow-hidden bg-gradient-to-br from-sky-100 via-white to-white"
             style="--gutter: clamp(2rem, 6vw, 7rem); --card: clamp(22rem, 24vw, 26rem);">

            {{--
                Building photo (LWUAAA.png). On large screens it fades in behind the card and is fully visible from the
                card's right edge, so the sign above the entrance stays clear of the card. On smaller screens it is a soft
                background behind the card.
            --}}
            <div class="absolute inset-0 lg:left-[calc(var(--gutter)_+_var(--card)_-1rem)] lg:[mask-image:linear-gradient(to_right,transparent,black_15rem)]" aria-hidden="true">
                <img src="{{ asset('images/LWUAAA.png') }}" alt="" class="h-full w-full object-cover object-center lg:object-[-25%_center]">
                <!-- Gradient blending -->
                <div class="absolute inset-0 bg-gradient-to-r from-[#eaf6fc] via-[#eaf6fc]/5 to-transparent"></div>
                {{-- A white veil on smaller screens, so the card stays readable over the photo --}}
                <div class="absolute inset-0 bg-white/80 lg:hidden"></div>
            </div>

            {{-- Decorative bands: blue on the bottom left, red and navy on the bottom right. --}}
            <svg class="pointer-events-none absolute inset-x-0 bottom-0 hidden h-[28vh] w-full sm:block" viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true">
                <polygon class="fill-lwua-sky/30" points="0,40 700,320 520,320 0,110" />
                <polygon class="fill-lwua-blue/70" points="0,120 520,320 330,320 0,180" />
                <polygon class="fill-lwua-navy" points="0,180 330,320 0,320" />
                <polygon class="fill-lwua-red" points="1440,130 1440,170 1150,320 1075,320" />
                <polygon class="fill-lwua-navy" points="1440,170 1440,320 1150,320" />
            </svg>

            {{-- Seal, title and card --}}
            <div class="relative z-10 flex min-h-dvh -translate-y-8 translate-x-16 items-center px-4 py-[clamp(2rem,4vh,2rem)] sm:px-12 lg:pl-[var(--gutter)]">
                <div class="w-full max-w-[40rem]">
                    <a href="{{ route('login') }}" class="inline-block rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-lwua-blue">
                        <x-application-logo class="h-[clamp(4rem,13vh,8rem)] w-[clamp(4rem,13vh,8rem)]" />
                    </a>
                    <h1 class="mt-[clamp(0.75rem,2.5vh,1.5rem)] text-[min(clamp(1.5rem,min(4.6vh,3.2vw),2.75rem),calc(var(--card)/12))] whitespace-nowrap font-extrabold uppercase leading-[1.1] tracking-tight text-lwua-navy">
                        {{ __('Local Water Utilities') }}<br>{{ __('Administration') }}
                    </h1>

                    <div class="mt-[clamp(1rem,3.5vh,2rem)] w-full max-w-[var(--card)] rounded-xl bg-white/85 p-[clamp(1.25rem,3vh,1.75rem)] shadow-xl ring-1 ring-white/70 backdrop-blur">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
