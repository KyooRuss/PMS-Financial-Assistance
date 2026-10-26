{{-- Water district top header (Figma user side). LWUA staff use layouts/sidebar instead. --}}
@php
    $user = Auth::user();
@endphp

<nav class="bg-gradient-to-r from-lwua-blue to-lwua-navy shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="{{ route('dashboard') }}" class="shrink-0">
                <x-application-logo class="h-14 w-14" />
            </a>

            <div class="flex items-center gap-4 sm:gap-6">
                {{-- Opens the "Select Project Type" modal. --}}
                <button type="button"
                        x-data
                        x-on:click="$dispatch('open-modal', 'select-project-type')"
                        class="inline-flex items-center gap-1 rounded-md bg-lwua-sky px-3 sm:px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-white/70">
                    <span aria-hidden="true">+</span> {{ __('Request Project') }}
                </button>

                <!-- Profile Dropdown -->
                <x-dropdown align="right" width="w-64">
                    <x-slot name="trigger">
                        <button type="button" class="flex items-center gap-1 text-white focus:outline-none" aria-label="{{ __('Account menu') }}">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-lwua-navy">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5 0-9 2.5-9 5.5V22h18v-2.5C21 16.5 17 14 12 14Z" />
                                </svg>
                            </span>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <dl class="px-4 py-3 space-y-2 text-xs text-gray-600">
                            <div><dt class="inline font-semibold text-gray-800">{{ __('User ID:') }}</dt> <dd class="inline">{{ $user->id }}</dd></div>
                            {{-- Surname, First Name Middle Name --}}
                            <div><dt class="inline font-semibold text-gray-800">{{ __('Name:') }}</dt> <dd class="inline">{{ $user->last_name }}, {{ trim($user->first_name.' '.$user->middle_name) }}</dd></div>
                        </dl>

                        <div class="border-t border-gray-100"></div>

                        @if ($user->hasRole(\App\Enums\Role::SystemAdmin))
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('My Profile') }}
                            </x-dropdown-link>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>
</nav>

@include('projects.partials.select-type-modal')
