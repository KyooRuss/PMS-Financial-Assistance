<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Department Accounts'), 'subtitle' => __('Manage LWUA staff accounts. Accounts are deactivated instead of deleted to preserve staff names in project timelines and uploaded file records.')])

        <div class="flex flex-wrap items-end justify-between gap-3">
            {{-- Filters apply as soon as an option is picked; the search box applies on Enter or when you leave it. --}}
            <form method="GET" class="flex flex-wrap items-end gap-3" x-data x-on:change="$el.submit()" x-on:search="$el.submit()">
                <div>
                    <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('Name, username or email') }}"
                           class="mt-1 w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                </div>
                <div>
                    <label for="role" class="block text-xs font-medium text-gray-600">{{ __('Role') }}</label>
                    <select id="role" name="role" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                        <option value="">{{ __('All') }}</option>
                        @foreach (\App\Enums\Role::cases() as $role)
                            @continue($role === \App\Enums\Role::Lwd)
                            <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                    <select id="status" name="status" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                        <option value="">{{ __('All') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                @if (request()->hasAny(['q', 'role', 'status']))
                    <a href="{{ route('admin.users.index') }}" class="py-2 text-sm text-gray-500 hover:text-gray-800">{{ __('Clear') }}</a>
                @endif
            </form>

            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-1 rounded-md bg-lwua-sky px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">
                <span aria-hidden="true">+</span> {{ __('New Account') }}
            </a>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Name') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Username') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Email') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Role') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Status') }}</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr @class(['hover:bg-gray-50', 'text-gray-400' => ! $user->is_active])>
                            <td class="px-6 py-3">
                                <span @class(['font-medium', 'text-gray-900' => $user->is_active])>{{ $user->full_name }}</span>
                                @if ($user->must_change_password)
                                    <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700">{{ __('Temporary password') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">{{ $user->username }}</td>
                            <td class="px-6 py-3">{{ $user->email }}</td>
                            <td class="px-6 py-3 whitespace-nowrap">{{ $user->role->shortLabel() }}</td>
                            <td class="px-6 py-3">
                                <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-green-50 text-green-700' => $user->is_active, 'bg-gray-100 text-gray-500' => ! $user->is_active])>
                                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-lwua-blue hover:underline">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">{{ __('No accounts match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
