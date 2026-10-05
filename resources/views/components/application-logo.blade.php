{{-- LWUA seal. It has a transparent background and dark lettering, so it sits on a white circle (as in the Figma). --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-full bg-white p-0.5 shadow-sm']) }}>
    <img src="{{ asset('images/LWUA_Logo.png') }}" alt="{{ __('Local Water Utilities Administration') }}" class="h-full w-full object-contain">
</span>
