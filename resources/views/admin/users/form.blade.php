{{--
    Create or edit an account. Editing also offers "reset password" and "make inactive".
    With $district (opened from a water district's page, or editing one of its accounts) this is a water district
    account; otherwise it is an LWUA staff account from the Accounts page.
--}}
@php
    $isSelf = $user->exists && $user->is(auth()->user());
    $isWaterDistrict = $district !== null;
    $backUrl = $isWaterDistrict ? route('admin.water-districts.edit', $district) : route('admin.users.index');
@endphp

<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <a href="{{ $backUrl }}" class="text-sm font-medium text-lwua-blue hover:underline">&larr; {{ $isWaterDistrict ? $district->name : __('Department Accounts') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-lwua-navy">{{ $user->exists ? $user->full_name : __('New Account') }}</h1>
            @if ($user->exists)
                <p class="mt-1 text-sm text-gray-500">
                    {{ $user->is_active ? __('Active') : __('Inactive') }} · {{ __('Created :date', ['date' => $user->created_at->format('M j, Y')]) }}
                    @if ($user->must_change_password) · <span class="text-amber-700">{{ __('Has a temporary password') }}</span> @endif
                </p>
            @endif
        </div>

        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
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

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="username" :value="__('Username')" />
                    <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" required autocapitalize="none" spellcheck="false" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Used to log in, along with the email. Lowercase letters, numbers, dots, dashes or underscores.') }}</p>
                    <x-input-error class="mt-1" :messages="$errors->get('username')" />
                </div>
                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
                    <x-input-error class="mt-1" :messages="$errors->get('email')" />
                </div>
            </div>

            @if ($isWaterDistrict)
                {{-- Water district account: the role is fixed; the district can be changed if the user moves. --}}
                <input type="hidden" name="role" value="{{ \App\Enums\Role::Lwd->value }}">
                <div>
                    <x-input-label for="water_district_id" :value="__('Water District')" />
                    <select id="water_district_id" name="water_district_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                        @foreach ($districts as $option)
                            <option value="{{ $option->id }}" @selected((int) old('water_district_id', $user->water_district_id) === $option->id)>
                                {{ $option->name }} ({{ __('Category :c', ['c' => $option->category]) }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-1" :messages="$errors->get('water_district_id')" />
                    <x-input-error class="mt-1" :messages="$errors->get('role')" />
                </div>
            @else
                {{-- LWUA staff account. --}}
                <div class="sm:w-1/2">
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" required @disabled($isSelf)
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue disabled:bg-gray-100">
                        @foreach (\App\Enums\Role::cases() as $role)
                            @continue($role === \App\Enums\Role::Lwd)
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
            @endif

            @unless ($user->exists)
                <div>
                    <x-input-label for="password" :value="__('Temporary password')" />
                    <x-text-input id="password" name="password" type="text" class="mt-1 block w-full font-mono" :value="old('password')" required minlength="8" autocomplete="off" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters. Give it to the user; they must set their own password on first login.') }}</p>
                    <x-input-error class="mt-1" :messages="$errors->get('password')" />
                </div>
            @endunless

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $backUrl }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">{{ __('Cancel') }}</a>
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
