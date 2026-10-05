{{-- Success / error messages from redirects, shared by both layouts. --}}
@foreach (['error' => 'bg-red-50 text-red-700 ring-red-200', 'success' => 'bg-green-50 text-green-700 ring-green-200'] as $key => $classes)
    @if (session($key))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6" x-data="{ show: true }" x-show="show">
            <div role="alert" class="flex items-start justify-between gap-4 rounded-md px-4 py-3 text-sm ring-1 {{ $classes }}">
                <span>{{ session($key) }}</span>
                <button type="button" x-on:click="show = false" class="font-bold" aria-label="{{ __('Dismiss') }}">&times;</button>
            </div>
        </div>
    @endif
@endforeach
