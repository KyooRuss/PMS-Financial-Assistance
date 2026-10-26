{{-- One-time screen for an account with a temporary password (new account, or reset by the System Admin). --}}
<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ __('Set your password') }}</h1>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('You signed in with a temporary password from the System Admin. Choose your own password to continue. You will not be able to change it later; if you forget it, ask the System Admin to reset it.') }}
    </p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('put')

        <div>
            <x-input-label for="current_password" :value="__('Temporary Password')" />
            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('New Password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="submit" form="logout-form" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Log out') }}</button>
            <x-primary-button>{{ __('Save Password') }}</x-primary-button>
        </div>
    </form>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
</x-guest-layout>
