{{-- View-only page for an uploaded PDF. Expects: $document. The pages are drawn by resources/js/pdf-viewer.js. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $document->original_name }} · PMS</title>
        <link rel="icon" type="image/png" href="{{ asset('images/LWUA_Logo.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/pdf-viewer.js'])

        {{-- Printing shows nothing. --}}
        <style>@media print { body { display: none !important; } }</style>
    </head>
    <body class="font-sans antialiased select-none bg-gray-800">
        <div id="pdf-viewer"
             data-url="{{ route('documents.file', $document) }}"
             data-error-message="{{ __('This file could not be opened.') }}"
             class="flex h-screen flex-col">

            {{-- Toolbar: no download or print buttons on purpose. --}}
            <header class="flex items-center justify-between gap-4 bg-lwua-navy px-4 py-2.5 text-white shadow">
                <div class="min-w-0">
                    <h1 class="truncate text-sm font-semibold" title="{{ $document->original_name }}">{{ $document->original_name }}</h1>
                    <p class="text-xs text-blue-100">
                        {{ $document->project->reference }} · <span id="pdf-page-count">{{ __('Loading…') }}</span> · {{ __('View only') }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" id="pdf-zoom-out" class="rounded p-1.5 hover:bg-white/10" aria-label="{{ __('Zoom out') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                    </button>
                    <span id="pdf-zoom" class="w-12 text-center text-xs tabular-nums">125%</span>
                    <button type="button" id="pdf-zoom-in" class="rounded p-1.5 hover:bg-white/10" aria-label="{{ __('Zoom in') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button type="button" onclick="window.close()" class="ml-3 rounded border border-white/40 px-3 py-1 text-xs font-semibold uppercase tracking-wide hover:bg-white/10">
                        {{ __('Close') }}
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-auto px-4 py-6">
                <p id="pdf-status" class="py-16 text-center text-sm text-gray-300">{{ __('Loading file…') }}</p>
                <div id="pdf-pages" class="space-y-4"></div>
            </main>
        </div>
    </body>
</html>
