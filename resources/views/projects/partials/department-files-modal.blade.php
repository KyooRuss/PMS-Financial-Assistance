{{--
    Modal with the files an office uploaded, opened by clicking that office on the process flow.
    Expects: $project. Closing the modal clears the office highlighted on the stepper.
--}}
<x-modal name="department-files" maxWidth="2xl">
    <div x-data="{ selectedOffice: null }"
         x-on:office-selected.window="if ($event.detail.office) { selectedOffice = $event.detail.office; $dispatch('open-modal', 'department-files') }"
         x-init="$watch('show', open => open || $dispatch('office-selected', { office: null }))"
         class="p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-lwua-navy">{{ __('Files Uploaded') }}</h2>
            <button type="button" x-on:click="$dispatch('close')"
                    class="rounded p-1 text-gray-500 hover:bg-gray-100" aria-label="{{ __('Close') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        @include('projects.partials.department-files')
    </div>
</x-modal>
