{{-- Legal – prepare the Financial Assistance Contract (regular step 15, emergency CAPEX 13 / O&M 10). --}}
<form method="POST" action="{{ route('office.projects.contract', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">
        {{ __('LPMD endorsed this approved request. Prepare the Financial Assistance Contract for :district, :amount.', [
            'district' => $project->waterDistrict->name,
            'amount' => $project->amount ? '₱'.number_format($project->amount, 2) : __('the approved amount'),
        ]) }}
    </p>

    <div>
        <x-input-label for="contract" :value="__('Financial Assistance Contract (PDF)')" />
        <input id="contract" name="contract" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <p class="mt-1 text-xs text-gray-500">{{ __('The water district can view the contract.') }}</p>
        <x-input-error class="mt-1" :messages="$errors->get('contract')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
    </div>

    <button type="submit" onclick="return confirm(@js(__('Upload the contract and complete :ref?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-700">
        {{ __('Complete – Contract Prepared') }}
    </button>
</form>
