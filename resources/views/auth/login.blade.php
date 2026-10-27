<x-guest-layout>
    <x-slot name="title">{{ __('Log in') }}</x-slot>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-[clamp(0.75rem,2.2vh,1.25rem)]">
        @csrf

        {{-- Email or username: usernames can't contain "@", so the server can tell them apart. --}}
        <div>
            <label for="login" class="sr-only">{{ __('Email or Username') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                       placeholder="{{ __('Email or Username') }}"
                       @class([
                           'block h-[clamp(2.75rem,6.5vh,3.5rem)] w-full rounded-md bg-white pl-12 pr-4 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-lwua-blue focus:ring-lwua-blue',
                           'border-gray-200' => ! $errors->has('login'),
                           'border-red-400' => $errors->has('login'),
                       ])>
            </div>
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        {{-- Password, with a button to show or hide it --}}
        <div x-data="{ show: false }">
            <label for="password" class="sr-only">{{ __('Password') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <input id="password" name="password" type="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
                       placeholder="{{ __('Password') }}"
                       @class([
                           'block h-[clamp(2.75rem,6.5vh,3.5rem)] w-full rounded-md bg-white pl-12 pr-12 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-lwua-blue focus:ring-lwua-blue',
                           'border-gray-200' => ! $errors->has('password'),
                           'border-red-400' => $errors->has('password'),
                       ])>
                <button type="button" x-on:click="show = ! show"
                        :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
                        :aria-pressed="show"
                        aria-label="{{ __('Show password') }}"
                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1 text-gray-600 hover:text-lwua-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-lwua-blue">
                    {{-- Eye with a slash while hidden; open eye while shown --}}
                    <svg x-show="! show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember_me" class="inline-flex items-center gap-3">
            <input id="remember_me" type="checkbox" name="remember" class="h-5 w-5 rounded border-gray-300 text-lwua-navy shadow-sm focus:ring-lwua-blue">
            <span class="text-sm text-gray-700">{{ __('Remember me') }}</span>
        </label>

        <button type="submit"
                class="flex h-[clamp(2.75rem,6.5vh,3.5rem)] w-full items-center justify-center rounded-md bg-lwua-navy text-sm font-semibold uppercase tracking-wider text-white shadow transition hover:brightness-125 focus:outline-none focus-visible:ring-2 focus-visible:ring-lwua-blue focus-visible:ring-offset-2">
            {{ __('Log in') }}
        </button>

        {{-- No "forgot password" link: a user who forgets it asks the System Admin to reset it. --}}
        <p class="whitespace-nowrap text-[11px] text-gray-500">{{ __('Forgot your password? Ask the System Admin to reset it.') }}</p>
    </form>
</x-guest-layout>
