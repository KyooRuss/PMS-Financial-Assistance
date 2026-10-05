{{-- Opened by any "+ Request Project" button via $dispatch('open-modal', 'select-project-type'). --}}
@php($user = Auth::user())

<x-modal name="select-project-type" maxWidth="md" focusable>
    <div class="px-8 py-8 text-center">
        <h2 class="text-base font-bold uppercase tracking-wide text-gray-800">{{ __('Select Project Type') }}</h2>
        <p class="mt-1 text-xs text-gray-500">{{ __('Choose the type of financial assistance you are requesting.') }}</p>

        <div class="mt-6 flex flex-col items-center gap-3">
            @foreach (\App\Enums\ProjectType::cases() as $type)
                @php($number = $loop->iteration)

                @if ($user->canRequest($type))
                    <a href="{{ route('projects.create', $type) }}"
                       class="w-64 rounded-md bg-lwua-dark px-4 py-3 text-white shadow hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-lwua-sky focus:ring-offset-2">
                        <span class="block text-sm font-bold uppercase">{{ $number }} – {{ $type->shortLabel() }}</span>
                        <span class="block text-[11px] text-white/75">{{ $type->isEmergency() ? __('Category C & D only') : __('Regular Loan') }} · {{ $type->annex() }}</span>
                    </a>
                @else
                    <div class="w-64 cursor-not-allowed rounded-md bg-gray-200 px-4 py-3 text-gray-500" aria-disabled="true">
                        <span class="block text-sm font-bold uppercase">{{ $number }} – {{ $type->shortLabel() }}</span>
                        <span class="block text-[11px]">
                            {{ __('Not available to Category :category water districts', ['category' => $user->waterDistrict?->category ?? '—']) }}
                        </span>
                    </div>
                @endif
            @endforeach
        </div>

        <button type="button" x-on:click="$dispatch('close')" class="mt-6 text-sm font-medium text-gray-500 hover:text-gray-800">
            {{ __('Cancel') }}
        </button>
    </div>
</x-modal>
