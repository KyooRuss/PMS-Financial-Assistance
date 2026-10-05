{{-- ES – forward the Approved POW with Project Profile to LWRD (regular step 8, emergency CAPEX step 6). --}}
@php($approval = $project->logsForStep('approve_pow')->first())

<form method="POST" action="{{ route('office.projects.pow.forward', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf

    <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-900 ring-1 ring-green-200">
        <p class="font-semibold">
            {{ __('The Administrator approved the LWUA-POW') }}
            @if ($approval) · {{ $approval->created_at->format('M j, Y') }} @endif
        </p>
        @if ($approval?->remarks)
            <p class="mt-1 whitespace-pre-line">{{ $approval->remarks }}</p>
        @endif
    </div>

    <p class="text-sm text-gray-700">{{ __('Upload the Approved POW with Project Profile. It is added to the project\'s LWUA Documents, which the water district can see.') }}</p>

    <div>
        <x-input-label for="approved_pow" :value="__('Approved POW with Project Profile (PDF)')" />
        <input id="approved_pow" name="approved_pow" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('approved_pow')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Forward the approved POW of :ref to LWRD?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Forward to LWRD') }}
    </button>
</form>
