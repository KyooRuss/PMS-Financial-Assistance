{{--
    selectedOffice: the department highlighted at first (a role value), if any.
    toggleable: whether clicking the selected department again clears the selection.
--}}
@props(['project', 'selectedOffice' => null, 'toggleable' => true])

@php
    $steps = $project->steps();
@endphp

{{--
    Clicking an office selects its department and dispatches "office-selected" (detail: { office: role value or null }),
    so the page can show the files that department uploaded. Every circle of the selected department is highlighted.
--}}
<div x-data="{
        selectedOffice: @js($selectedOffice),
        toggleable: @js((bool) $toggleable),
        scroll(direction) { $refs.track.scrollBy({ left: direction * 320, behavior: 'smooth' }) },
        select(office) {
            this.$dispatch('office-selected', { office: this.toggleable && this.selectedOffice === office ? null : office });
        },
    }"
     x-on:office-selected.window="selectedOffice = $event.detail.office"
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
            @php($office = \App\Support\ProcessFlow::roleFor($step['unit'])->value)
            {{-- The water district's own steps have no files modal, so they are not clickable. --}}
            @php($clickable = $office !== \App\Enums\Role::Lwd->value)

            <li class="relative flex min-w-[120px] flex-1 flex-col items-center text-center"
                @unless (in_array($state, ['done', 'upcoming'])) data-current aria-current="step" @endunless>

                {{-- Connector to the next step --}}
                @unless ($loop->last)
                    <span class="absolute left-1/2 top-5 h-0.5 w-full {{ $state === 'done' ? 'bg-lwua-blue' : 'bg-gray-200' }}" aria-hidden="true"></span>
                @endunless

                @if ($clickable)
                <button type="button" x-on:click="select(@js($office))"
                        :aria-pressed="selectedOffice === @js($office)"
                        title="{{ __('Show files uploaded by :office', ['office' => $step['unit']]) }}"
                        class="group relative z-10 flex flex-col items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-lwua-blue">
                @else
                <div class="relative z-10 flex flex-col items-center">
                @endif

                <span :class="selectedOffice === @js($office) && 'outline outline-2 outline-offset-4 outline-lwua-blue'"
                      @class([
                    'relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold',
                    'group-hover:ring-4 group-hover:ring-blue-100' => $clickable,
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

                <span :class="selectedOffice === @js($office) ? 'text-lwua-blue underline' : 'text-lwua-navy'"
                      class="mt-2 text-xs font-semibold group-hover:text-lwua-blue group-hover:underline">{{ $step['unit'] }}</span>
                <span class="mt-0.5 px-2 text-[11px] leading-tight text-gray-500">{{ $step['action'] }}</span>

                @if ($clickable)
                </button>
                @else
                </div>
                @endif
            </li>
        @endforeach
    </ol>

    <button type="button" x-on:click="scroll(1)" class="shrink-0 p-2 text-gray-400 hover:text-lwua-navy" aria-label="{{ __('Next steps') }}">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
    </button>
</div>
