<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-lwua-navy">{{ __('My Profile') }}</h1>
            {{-- Accounts are created and removed by LWUA, so there is no self-delete here. --}}
            <p class="mt-1 text-sm text-gray-500">{{ __('Update your name, email and password. To close an account, contact LWUA.') }}</p>
        </div>

        <div class="p-4 sm:p-8 bg-white shadow-md ring-1 ring-black/5 sm:rounded-lg">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-4 sm:p-8 bg-white shadow-md ring-1 ring-black/5 sm:rounded-lg">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
