{{-- Admin page heading. The sections themselves are in the sidebar. Expects $title (and optional $subtitle). --}}
<div>
    <p class="text-sm font-medium text-lwua-blue">{{ __('System Admin') }}</p>
    <h1 class="text-2xl font-bold text-lwua-navy">{{ $title }}</h1>
    @isset($subtitle)
        <p class="mt-1 max-w-3xl text-sm text-gray-500">{{ $subtitle }}</p>
    @endisset
</div>
