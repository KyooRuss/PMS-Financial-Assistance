<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <a href="{{ route('admin.water-districts.index') }}" class="text-sm font-medium text-lwua-blue hover:underline">&larr; {{ __('Water Districts') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-lwua-navy">{{ $district->exists ? $district->name : __('New Water District') }}</h1>
        </div>

        <form method="POST" action="{{ $district->exists ? route('admin.water-districts.update', $district) : route('admin.water-districts.store') }}"
              class="space-y-5 rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
            @csrf
            @if ($district->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $district->name)" required />
                <x-input-error class="mt-1" :messages="$errors->get('name')" />
            </div>

            <fieldset>
                <legend class="block text-sm font-medium text-gray-700">{{ __('Category') }}</legend>
                <div class="mt-2 flex gap-2">
                    @foreach (['A', 'B', 'C', 'D'] as $category)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm has-[:checked]:border-lwua-blue has-[:checked]:bg-blue-50">
                            <input type="radio" name="category" value="{{ $category }}" @checked(old('category', $district->category) === $category) required class="text-lwua-blue focus:ring-lwua-blue">
                            {{ $category }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('Category C and D can request emergency loans.') }}</p>
                <x-input-error class="mt-1" :messages="$errors->get('category')" />
            </fieldset>

            <div>
                <x-input-label for="address" :value="__('Address (optional)')" />
                <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $district->address)" />
                <x-input-error class="mt-1" :messages="$errors->get('address')" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.water-districts.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800">{{ __('Cancel') }}</a>
                <button type="submit" class="rounded-md bg-lwua-dark px-6 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
                    {{ $district->exists ? __('Save Changes') : __('Add Water District') }}
                </button>
            </div>
        </form>

        {{-- The water district's accounts are managed here (they are not on the Accounts page). --}}
        @if ($district->exists)
            <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-lwua-navy">{{ __('Accounts') }}</h2>
                        <a href="{{ route('admin.projects.index', ['district' => $district->id]) }}" class="text-xs font-medium text-lwua-blue hover:underline">
                            {{ trans_choice(':count project|:count projects', $district->projects_count) }} &rarr;
                        </a>
                    </div>
                    <a href="{{ route('admin.users.create', ['water_district' => $district->id]) }}"
                       class="inline-flex items-center gap-1 rounded-md bg-lwua-sky px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">
                        <span aria-hidden="true">+</span> {{ __('Add Account') }}
                    </a>
                </div>

                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @forelse ($district->users as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p @class(['font-medium', 'text-gray-900' => $member->is_active, 'text-gray-400' => ! $member->is_active])>
                                    {{ $member->full_name }}
                                    @if ($member->must_change_password)
                                        <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-normal text-amber-700">{{ __('Temporary password') }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">{{ $member->email }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-green-50 text-green-700' => $member->is_active, 'bg-gray-100 text-gray-500' => ! $member->is_active])>
                                    {{ $member->is_active ? __('Active') : __('Inactive') }}
                                </span>
                                <a href="{{ route('admin.users.edit', $member) }}" class="font-medium text-lwua-blue hover:underline">{{ __('Edit') }}</a>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-gray-500">{{ __('No accounts yet. Use "Add Account" to create one.') }}</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</x-app-layout>
