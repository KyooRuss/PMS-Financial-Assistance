@props(['project'])

@php($steps = $project->steps())

<div x-data="{
        scroll(direction) { $refs.track.scrollBy({ left: direction * 320, behavior: 'smooth' }) },
    }"
     x-init="$nextTick(() => {
        const current = $refs.track.querySelector('[data-current]');
        if (current) $refs.track.scrollLeft = current.offsetLeft - $refs.track.clientWidth / 2 + current.clientWidth / 2;
     })"
     class="relative flex items-center rounded-lg bg-white shadow-md ring-1 ring-black/5 px-2 py-5">

    <button type="button" x-on:click="scroll(-1)" class="shrink-0 p-2 text-gray-400 hover:text-lwua-navy" aria-label="{{ __('Previous steps') }}">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
    </button>

    <ol x-ref="track" class="flex flex-1 overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($steps as $i => $step)
            @php($number = $i + 1)
            @php($state = $project->stepState($number))

            <li class="relative flex min-w-[120px] flex-1 flex-col items-center text-center"
                @unless (in_array($state, ['done', 'upcoming'])) data-current aria-current="step" @endunless>

                {{-- Connector to the next step --}}
                @unless ($loop->last)
                    <span class="absolute left-1/2 top-5 h-0.5 w-full {{ $state === 'done' ? 'bg-lwua-blue' : 'bg-gray-200' }}" aria-hidden="true"></span>
                @endunless

                <span @class([
                    'relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold',
                    'bg-green-500 text-white ring-4 ring-green-100' => $state === 'done',
                    'bg-white border-2 border-lwua-blue text-lwua-blue' => $state === 'current',
                    'bg-amber-50 border-2 border-amber-500 text-amber-600' => $state === 'returned',
                    'bg-red-500 text-white ring-4 ring-red-100' => $state === 'stopped',
                    'bg-white border-2 border-gray-300 text-gray-500' => $state === 'upcoming',
                ])>
                    @if ($state === 'done')
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-label="{{ __('Done') }}"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @elseif ($state === 'stopped')
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-label="{{ __('Stopped') }}"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    @elseif ($state === 'returned')
                        !
                    @else
                        {{ $number }}
                    @endif
                </span>

                <span class="mt-2 text-xs font-semibold text-lwua-navy">{{ $step['unit'] }}</span>
                <span class="mt-0.5 px-2 text-[11px] leading-tight text-gray-500">{{ $step['action'] }}</span>
            </li>
        @endforeach
    </ol>

    <button type="button" x-on:click="scroll(1)" class="shrink-0 p-2 text-gray-400 hover:text-lwua-navy" aria-label="{{ __('Next steps') }}">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
    </button>
</div>
