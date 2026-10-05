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

        @if ($district->exists)
            <section class="rounded-lg bg-white p-6 shadow-md ring-1 ring-black/5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-semibold text-lwua-navy">{{ __('Users') }}</h2>
                    <a href="{{ route('admin.projects.index', ['district' => $district->id]) }}" class="text-sm font-medium text-lwua-blue hover:underline">
                        {{ trans_choice(':count project|:count projects', $district->projects_count) }} &rarr;
                    </a>
                </div>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @forelse ($district->users as $member)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ route('admin.users.edit', $member) }}" @class(['hover:underline', 'text-gray-900' => $member->is_active, 'text-gray-400 line-through' => ! $member->is_active])>{{ $member->full_name }}</a>
                            <span class="text-xs text-gray-500">{{ $member->email }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">
                            {{ __('No users yet.') }}
                            <a href="{{ route('admin.users.create') }}" class="font-medium text-lwua-blue hover:underline">{{ __('Create one') }}</a>
                        </li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</x-app-layout>
