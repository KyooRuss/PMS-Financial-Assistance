{{-- Left sidebar for LWUA offices and the System Admin (water districts keep the Figma top header). --}}
@php
    $user = Auth::user();
    $isAdmin = $user->hasRole(\App\Enums\Role::SystemAdmin);

    // Counts shown beside the links.
    $waitingCounts = \App\Models\Project::query()->whereNotNull('current_role')->where('status', '!=', 'draft')
        ->selectRaw('current_role, count(*) as total')->groupBy('current_role')->pluck('total', 'current_role');
    $statusCounts = $isAdmin
        ? \App\Models\Project::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
        : collect();

    $onProjects = request()->routeIs('admin.projects.index');
    $filterIs = fn (string $key, string $value) => $onProjects && request($key) === $value;

    // Offices in flow order, then the water districts (their turn to fix or comply).
    $workflow = [
        \App\Enums\Role::Wsudd, \App\Enums\Role::Es, \App\Enums\Role::Lwrd, \App\Enums\Role::Cpic,
        \App\Enums\Role::Administrator, \App\Enums\Role::Bfmd, \App\Enums\Role::Lpmd, \App\Enums\Role::Legal, \App\Enums\Role::Lwd,
    ];
    $icon = fn (string $path) => '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="'.$path.'"/></svg>';
@endphp

{{-- Dimmed background behind the sidebar on small screens --}}
<div x-show="sidebarOpen" x-cloak x-transition.opacity x-on:click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" aria-hidden="true"></div>

<aside :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', sidebarHidden ? 'lg:hidden' : '']"
       class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-gradient-to-b from-lwua-navy to-[#002A50] text-white shadow-xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none"
       aria-label="{{ __('Main navigation') }}">

    {{-- Brand --}}
    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <x-application-logo class="h-11 w-11" />
            <span>
                <span class="block text-lg font-bold leading-tight">LWUA</span>
                <span class="block text-xs text-white/70">{{ $isAdmin ? __('Admin Portal') : __('Office Portal') }}</span>
            </span>
        </a>
        <button type="button" x-on:click="hideSidebar()" class="rounded p-1 text-white/80 hover:bg-white/10 hover:text-white"
                aria-label="{{ __('Hide menu') }}" title="{{ __('Hide menu') }}">
            {{-- Phones: close (×). Desktop: collapse («). --}}
            <svg class="h-5 w-5 lg:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            <svg class="hidden h-5 w-5 lg:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
        </button>
    </div>

    <nav class="sidebar-scroll flex-1 space-y-5 overflow-y-auto px-3 py-4">
        @if ($isAdmin)
            <div class="space-y-1">
                <x-sidebar-link :href="route('admin.overview')" :active="request()->routeIs('admin.overview')">
                    <x-slot:icon>{!! $icon('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6') !!}</x-slot:icon>
                    {{ __('Overview') }}
                </x-sidebar-link>
                <x-sidebar-link :href="route('admin.projects.index')" :active="$onProjects && ! request()->hasAny(['status', 'waiting'])" :count="$statusCounts->sum()">
                    <x-slot:icon>{!! $icon('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2') !!}</x-slot:icon>
                    {{ __('All Requests') }}
                </x-sidebar-link>
            </div>

            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ __('Status') }}</p>
                <div class="space-y-0.5">
                    @php
                        $inProgress = collect(\App\Http\Controllers\Admin\ProjectController::IN_PROGRESS)->sum(fn ($status) => $statusCounts[$status->value] ?? 0);
                    @endphp
                    <x-sidebar-link :href="route('admin.projects.index', ['status' => 'in_progress'])" :active="$filterIs('status', 'in_progress')" :count="$inProgress">{{ __('In Progress') }}</x-sidebar-link>
                    @foreach ([
                        \App\Enums\ProjectStatus::Returned,
                        \App\Enums\ProjectStatus::ForCompliance,
                        \App\Enums\ProjectStatus::Approved,
                        \App\Enums\ProjectStatus::Completed,
                        \App\Enums\ProjectStatus::Disapproved,
                    ] as $status)
                        <x-sidebar-link :href="route('admin.projects.index', ['status' => $status->value])" :active="$filterIs('status', $status->value)" :count="$statusCounts[$status->value] ?? 0">
                            {{ $status === \App\Enums\ProjectStatus::Returned ? __('Returned') : $status->label() }}
                        </x-sidebar-link>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ __('Workflow Step') }}</p>
                <div class="space-y-0.5">
                    @foreach ($workflow as $role)
                        <x-sidebar-link :href="route('admin.projects.index', ['waiting' => $role->value])" :active="$filterIs('waiting', $role->value)" :step="$loop->iteration" :count="$waitingCounts[$role->value] ?? 0">
                            {{ $role->shortLabel() }}
                        </x-sidebar-link>
                    @endforeach
                    <x-sidebar-link :href="route('admin.projects.index', ['status' => 'not_prioritized'])" :active="$filterIs('status', 'not_prioritized')" :step="count($workflow) + 1" :count="$statusCounts['not_prioritized'] ?? 0">
                        {{ __('Kept on file') }}
                    </x-sidebar-link>
                </div>
            </div>

            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ __('Manage') }}</p>
                <div class="space-y-0.5">
                    <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                        <x-slot:icon>{!! $icon('M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z') !!}</x-slot:icon>
                        {{ __('Accounts') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('admin.water-districts.index')" :active="request()->routeIs('admin.water-districts.*')">
                        <x-slot:icon>{!! $icon('M12 3c-3 4-6 7.5-6 11a6 6 0 0012 0c0-3.5-3-7-6-11z') !!}</x-slot:icon>
                        {{ __('Water Districts') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('reports.processing-time')" :active="request()->routeIs('reports.processing-time')">
                        <x-slot:icon>{!! $icon('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z') !!}</x-slot:icon>
                        {{ __('Processing Time') }}
                    </x-sidebar-link>
                </div>
            </div>
        @else
            {{-- LWUA offices --}}
            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ $user->role->shortLabel() }}</p>
                <div class="space-y-0.5">
                    <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard') && request('tab') !== 'handled'" :count="$waitingCounts[$user->role->value] ?? 0">
                        <x-slot:icon>{!! $icon('M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4') !!}</x-slot:icon>
                        {{ __('My Queue') }}
                    </x-sidebar-link>
                    <x-sidebar-link :href="route('dashboard', ['tab' => 'handled'])" :active="request()->routeIs('dashboard') && request('tab') === 'handled'">
                        <x-slot:icon>{!! $icon('M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z') !!}</x-slot:icon>
                        {{ __('Handled by my office') }}
                    </x-sidebar-link>
                </div>
            </div>

            <div>
                <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-white/55">{{ __('Reports') }}</p>
                <x-sidebar-link :href="route('reports.processing-time')" :active="request()->routeIs('reports.processing-time')">
                    <x-slot:icon>{!! $icon('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z') !!}</x-slot:icon>
                    {{ __('Processing Time') }}
                </x-sidebar-link>
            </div>
        @endif
    </nav>

    {{-- Signed-in user --}}
    <div class="border-t border-white/10 px-4 py-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/90 text-sm font-semibold text-lwua-navy" aria-hidden="true">
                {{ mb_substr($user->first_name, 0, 1) }}{{ mb_substr($user->last_name, 0, 1) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ $user->full_name }}</p>
                <p class="truncate text-xs text-white/65">{{ $user->role->shortLabel() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded p-1.5 text-white/80 hover:bg-white/10 hover:text-white" title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
        {{-- Only the System Admin edits their own account; everyone else's is managed by the System Admin. --}}
        @if ($user->hasRole(\App\Enums\Role::SystemAdmin))
            <a href="{{ route('profile.edit') }}" @class(['mt-3 block rounded-md px-3 py-1.5 text-center text-xs', 'bg-white text-lwua-navy font-semibold' => request()->routeIs('profile.edit'), 'text-white/80 ring-1 ring-white/20 hover:bg-white/10' => ! request()->routeIs('profile.edit')])>
                {{ __('My Profile') }}
            </a>
        @endif
    </div>
</aside>
