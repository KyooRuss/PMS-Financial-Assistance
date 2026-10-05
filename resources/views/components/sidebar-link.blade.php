{{-- One sidebar entry. Active = white pill (as in the mockup). Optional step number and count badge. --}}
@props(['href', 'active' => false, 'count' => null, 'step' => null])

<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'flex items-center gap-2.5 rounded-md px-3 py-1.5 text-sm transition',
       'bg-white font-semibold text-lwua-navy shadow' => $active,
       'text-white/85 hover:bg-white/10 hover:text-white' => ! $active,
   ]) }}>
    @if ($step !== null)
        <span @class([
            'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
            'bg-lwua-navy text-white' => $active,
            'bg-white/90 text-lwua-navy' => ! $active,
        ])>{{ $step }}</span>
    @elseif (isset($icon))
        <span class="flex h-5 w-5 shrink-0 items-center justify-center" aria-hidden="true">{{ $icon }}</span>
    @endif

    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>

    @if ($count !== null)
        <span @class([
            'shrink-0 rounded-full px-2 text-[11px] font-semibold tabular-nums',
            'bg-lwua-navy/10 text-lwua-navy' => $active,
            'bg-white/15 text-white' => ! $active,
        ])>{{ $count }}</span>
    @endif
</a>
