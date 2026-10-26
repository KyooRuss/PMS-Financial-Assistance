<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Water Districts'), 'subtitle' => __('The category (A–D) decides eligibility: emergency loans are for Category C and D only, and the 10% equity is required for A and B.')])

        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Search') }}</label>
                    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('Name') }}"
                           class="mt-1 w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                </div>
                <div>
                    <label for="category" class="block text-xs font-medium text-gray-600">{{ __('Category') }}</label>
                    <select id="category" name="category" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['A', 'B', 'C', 'D'] as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-lwua-dark px-4 py-2 text-sm font-semibold text-white shadow hover:brightness-110">{{ __('Filter') }}</button>
            </form>

            <a href="{{ route('admin.water-districts.create') }}" class="inline-flex items-center gap-1 rounded-md bg-lwua-sky px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow hover:brightness-110">
                <span aria-hidden="true">+</span> {{ __('New Water District') }}
            </a>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow-md ring-1 ring-black/5">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold text-gray-600">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Name') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Category') }}</th>
                        <th scope="col" class="px-6 py-3 text-left">{{ __('Address') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Accounts') }}</th>
                        <th scope="col" class="px-6 py-3 text-right">{{ __('Projects') }}</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 tabular-nums">
                    @forelse ($districts as $district)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ $district->name }}</td>
                            <td class="px-6 py-3 text-gray-700">{{ $district->category }}</td>
                            <td class="px-6 py-3 text-gray-700">{{ $district->address ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.water-districts.edit', $district) }}" class="text-lwua-blue hover:underline">{{ $district->users_count }}</a>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.projects.index', ['district' => $district->id]) }}" class="text-lwua-blue hover:underline">{{ $district->projects_count }}</a>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('admin.water-districts.edit', $district) }}" class="font-medium text-lwua-blue hover:underline">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">{{ __('No water districts match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $districts->links() }}
    </div>
</x-app-layout>
