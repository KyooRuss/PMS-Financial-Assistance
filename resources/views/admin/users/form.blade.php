{{-- Create or edit an account. Editing also offers "reset password" and "make inactive". --}}
@php
    $isSelf = $user->exists && $user->is(auth()->user());
@endphp

<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-lwua-blue hover:underline">&larr; {{ __('Accounts') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-lwua-navy">{{ $user->exists ? $user->full_name : __('New Account') }}</h1>
            @if ($user->exists)
                <p class="mt-1 text-sm text-gray-500">
                    {{ $user->is_active ? __('Active') : __('Inactive') }} · {{ __('Created :date', ['date' => $user->created_at->format('M j, Y')]) }}
                    @if ($user->must_change_password) · <span class="text-amber-700">{{ __('Has a temporary password') }}</span> @endif
                </p>
            @endif
        </div>

        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
              x-data="{ role: @js(old('role', $user->role?->value)) }"
              class="space-y-5 rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <div class="grid gap-5 sm:grid-cols-3">
                @foreach (['first_name' => __('First Name'), 'middle_name' => __('Middle Name'), 'last_name' => __('Last Name')] as $field => $label)
                    <div>
                        <x-input-label :for="$field" :value="$label" />
                        <x-text-input :id="$field" :name="$field" type="text" class="mt-1 block w-full" :value="old($field, $user->{$field})" :required="$field !== 'middle_name'" />
                        <x-input-error class="mt-1" :messages="$errors->get($field)" />
                    </div>
                @endforeach
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                <x-input-error class="mt-1" :messages="$errors->get('email')" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" x-model="role" required @disabled($isSelf)
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue disabled:bg-gray-100">
                        @foreach (\App\Enums\Role::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @if ($isSelf)
                        {{-- A disabled select is not submitted; keep the value. --}}
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('You cannot change your own role.') }}</p>
                    @endif
                    <x-input-error class="mt-1" :messages="$errors->get('role')" />
                </div>

                <div x-show="role === 'lwd'" x-cloak>
                    <x-input-label for="water_district_id" :value="__('Water District')" />
                    <select id="water_district_id" name="water_district_id" :disabled="role !== 'lwd'"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district->id }}" @selected((int) old('water_district_id', $user->water_district_id) === $district->id)>
                                {{ $district->name }} ({{ __('Category :c', ['c' => $district->category]) }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('water_district_id')" />
                </div>
            </div>

            @unless ($user->exists)
                <div>
                    <x-input-label for="password" :value="__('Temporary password')" />
                    <x-text-input id="password" name="password" type="text" class="mt-1 block w-full font-mono" :value="old('password')" required minlength="8" autocomplete="off" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters. Give it to the user; they must set their own password on first login.') }}</p>
                    <x-input-error class="mt-1" :messages="$errors->get('password')" />
                </div>
            @endunless

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">{{ __('Cancel') }}</a>
                <button type="submit" class="rounded-md bg-lwua-dark px-6 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
                    {{ $user->exists ? __('Save Changes') : __('Create Account') }}
                </button>
            </div>
        </form>

        @if ($user->exists)
            {{-- Reset password --}}
            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="space-y-3 rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                @csrf
                @method('PUT')
                <h2 class="text-base font-semibold text-lwua-navy">{{ __('Reset password') }}</h2>
                <p class="text-sm text-gray-600">{{ __('Set a new temporary password. The user must change it the next time they log in.') }}</p>
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1">
                        <label for="temporary_password" class="sr-only">{{ __('Temporary password') }}</label>
                        <x-text-input id="temporary_password" name="temporary_password" type="text" class="block w-full font-mono" required minlength="8" autocomplete="off" placeholder="{{ __('New temporary password') }}" />
                        <x-input-error class="mt-1" :messages="$errors->resetPassword->get('temporary_password')" />
                    </div>
                    <button type="submit" class="rounded-md border border-lwua-dark bg-white px-4 py-2 text-sm font-semibold text-lwua-dark hover:bg-gray-50">{{ __('Set Temporary Password') }}</button>
                </div>
            </form>

            {{-- Active / inactive --}}
            @unless ($isSelf)
                <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}"
                      class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                    @csrf
                    @method('PATCH')
                    <div>
                        <h2 class="text-base font-semibold text-lwua-navy">{{ $user->is_active ? __('Make inactive') : __('Make active') }}</h2>
                        <p class="text-sm text-gray-600">
                            {{ $user->is_active
                                ? __('The user can no longer log in. Their name stays on the timeline and on files they uploaded.')
                                : __('The user can log in again with their current password.') }}
                        </p>
                    </div>
                    <button type="submit"
                            onclick="return confirm(@js($user->is_active ? __('Make :name inactive?', ['name' => $user->full_name]) : __('Make :name active again?', ['name' => $user->full_name])))"
                            @class([
                                'rounded-md px-4 py-2 text-sm font-semibold shadow',
                                'border border-red-600 bg-white text-red-700 hover:bg-red-50' => $user->is_active,
                                'bg-green-600 text-white hover:bg-green-700' => ! $user->is_active,
                            ])>
                        {{ $user->is_active ? __('Make Inactive') : __('Make Active') }}
                    </button>
                </form>
            @endunless
        @endif
    </div>
</x-app-layout>
