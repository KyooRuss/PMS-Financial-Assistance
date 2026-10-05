{{-- LWRD – approval letter to the water district; approved MFTA to LPMD (regular step 13, emergency CAPEX 11 / O&M 8). --}}
@php
    $decision = $project->logsForStep('final_decision')->first();
@endphp

<form method="POST" action="{{ route('office.projects.approval-letter', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-900 ring-1 ring-green-200">
        <p class="font-semibold">{{ __('The Administrator approved this request') }}
                @if ($decision) · {{ $decision->created_at->format('M j, Y') }} @endif
            </p>
        @if ($decision?->remarks)
            <p class="mt-1 whitespace-pre-line">{{ $decision->remarks }}</p>
        @endif
    </div>

    <div>
        <x-input-label for="letter" :value="__('Approval letter to the water district (PDF)')" />
        <input id="letter" name="letter" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('letter')" />
    </div>

    <div>
        <x-input-label for="mfta" :value="__('Approved MFTA for LPMD (PDF, optional)')" />
        <input id="mfta" name="mfta" type="file" accept="application/pdf,.pdf"
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('mfta')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
    </div>

    <button type="submit" onclick="return confirm(@js(__('Send the approval letter for :ref and forward to LPMD?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Send Letter & Forward to LPMD') }}
    </button>
</form>
