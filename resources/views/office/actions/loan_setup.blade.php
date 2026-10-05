{{-- LPMD/FS – secure the required documents and endorse to Legal (regular step 14, emergency CAPEX 12 / O&M 9). --}}
<form method="POST" action="{{ route('office.projects.endorse-to-legal', $project) }}" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">{{ __('LWRD furnished the approved MFTA and documents. Check that everything needed for the contract is complete (see the documents on the left), then endorse to Legal. The loan account is set up once the contract is ready.') }}</p>

    <label class="flex items-start gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm">
        <input type="checkbox" name="documents_secured" value="1" @checked(old('documents_secured')) required
               class="mt-0.5 rounded border-gray-300 text-lwua-blue focus:ring-lwua-blue">
        {{ __('The required documents are secured.') }}
    </label>
    <x-input-error :messages="$errors->get('documents_secured')" />

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
    </div>

    <button type="submit" onclick="return confirm(@js(__('Endorse :ref to Legal for the Financial Assistance Contract?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Endorse to Legal') }}
    </button>
</form>
