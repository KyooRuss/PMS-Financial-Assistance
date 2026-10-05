<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @include('admin.partials.heading', ['title' => __('Departments'), 'subtitle' => __('Each LWUA office: its staff, what is waiting on it now, and how long it usually takes per handling (all time).')])

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($departments as $department)
                @php
                    $role = $department['role'];
                    $active = $department['staff']->where('is_active', true);
                @endphp
                <section class="rounded-lg bg-white p-5 shadow-md ring-1 ring-black/5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-lwua-navy">{{ $role->label() }}</h2>
                            <p class="text-xs text-gray-500">{{ trans_choice(':count active account|:count active accounts', $active->count()) }}</p>
                        </div>
                        <a href="{{ route('admin.users.index', ['role' => $role->value]) }}" class="shrink-0 text-xs font-medium text-lwua-blue hover:underline">{{ __('Manage accounts') }}</a>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <a href="{{ route('admin.projects.index', ['waiting' => $role->value]) }}" class="rounded-md bg-gray-50 px-2 py-3 hover:bg-blue-50">
                            <dt class="text-xs text-gray-500">{{ __('Waiting now') }}</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $department['waiting']['count'] ?? 0 }}</dd>
                        </a>
                        <div class="rounded-md bg-gray-50 px-2 py-3">
                            <dt class="text-xs text-gray-500">{{ __('Average time') }}</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\ProcessingTimes::format($department['average']['average'] ?? null) }}</dd>
                        </div>
                        <div class="rounded-md bg-gray-50 px-2 py-3">
                            <dt class="text-xs text-gray-500">{{ __('Projects handled') }}</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $department['handled'] }}</dd>
                        </div>
                    </dl>

                    @if ($department['waiting'])
                        <p class="mt-3 text-xs text-gray-500">{{ __('Longest current wait: :time', ['time' => \App\Support\ProcessingTimes::format($department['waiting']['longest'])]) }}</p>
                    @endif

                    <ul class="mt-4 space-y-1 text-sm">
                        @forelse ($department['staff'] as $member)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('admin.users.edit', $member) }}" @class(['hover:underline', 'text-gray-800' => $member->is_active, 'text-gray-400 line-through' => ! $member->is_active])>{{ $member->full_name }}</a>
                                <span class="truncate text-xs text-gray-500">{{ $member->email }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-amber-700">{{ __('No accounts yet. Requests at this office\'s steps cannot move.') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
