{{-- BFMD/FS – issue the Certificate of Availability of Funds (regular step 10, emergency CAPEX step 8 / O&M step 5). --}}
<form method="POST" action="{{ route('office.projects.caf', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">
        {{ __('LWRD requests a Certificate of Availability of Funds for :amount.', ['amount' => $project->amount ? '₱'.number_format($project->amount, 2) : __('this request')]) }}
    </p>

    <div>
        <x-input-label for="caf" :value="__('Certificate of Availability of Funds (PDF)')" />
        <input id="caf" name="caf" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('caf')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Issue the CAF for :ref?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Issue CAF') }}
    </button>
</form>
