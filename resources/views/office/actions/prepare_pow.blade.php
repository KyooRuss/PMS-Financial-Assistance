{{-- ES – prepare the LWUA-POW (regular step 6, emergency CAPEX step 4). --}}
@php
    // Being back at this step with an entry on the approval step means the Administrator returned it.
    $revision = $project->logsForStep('approve_pow')->first();
@endphp

<form method="POST" action="{{ route('office.projects.pow.prepare', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf

    @if ($revision)
        <div class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-semibold">{{ __('Returned by the Administrator for revision') }} · {{ $revision->created_at->format('M j, Y') }}</p>
            <p class="mt-1 whitespace-pre-line">{{ $revision->remarks }}</p>
        </div>
    @else
        <p class="text-sm text-gray-700">{{ __('CPIC prioritized this request. Prepare the LWUA Program of Work based on the WD-POW and submit it for the Administrator\'s approval.') }}</p>
    @endif

    <div>
        <x-input-label for="lwua_pow" :value="$revision ? __('Revised LWUA-POW (PDF)') : __('LWUA-POW (PDF)')" />
        <input id="lwua_pow" name="lwua_pow" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('lwua_pow')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Submit the LWUA-POW of :ref to the Administrator?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Submit to Administrator') }}
    </button>
</form>
