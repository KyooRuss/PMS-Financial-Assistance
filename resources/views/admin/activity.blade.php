<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Activity Log'), 'subtitle' => __('Every action by everyone, across all projects, newest first.')])

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="office" class="block text-xs font-medium text-gray-600">{{ __('Office') }}</label>
                <select id="office" name="office" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($offices as $office)
                        <option value="{{ $office }}" @selected(request('office') === $office)>{{ $office }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="q" class="block text-xs font-medium text-gray-600">{{ __('Person') }}</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('Name or email') }}"
                       class="mt-1 w-52 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
            </div>
            <div>
                <label for="from" class="block text-xs font-medium text-gray-600">{{ __('From') }}</label>
                <input id="from" name="from" type="date" value="{{ request('from') }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
            </div>
            <div>
                <label for="to" class="block text-xs font-medium text-gray-600">{{ __('To') }}</label>
                <input id="to" name="to" type="date" value="{{ request('to') }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">
            </div>
            <button type="submit" class="rounded-md bg-lwua-dark px-4 py-2 text-sm font-semibold text-white shadow hover:brightness-110">{{ __('Filter') }}</button>
            @if (request()->hasAny(['office', 'q', 'from', 'to']))
                <a href="{{ route('admin.activity.index') }}" class="py-2 text-sm text-gray-500 hover:text-gray-800">{{ __('Clear') }}</a>
            @endif
        </form>

        <section class="rounded-lg bg-white px-6 pb-2 pt-3 shadow-md ring-1 ring-black/5">
            @include('admin.partials.activity-list', ['logs' => $logs])
        </section>

        {{ $logs->links() }}
    </div>
</x-app-layout>
