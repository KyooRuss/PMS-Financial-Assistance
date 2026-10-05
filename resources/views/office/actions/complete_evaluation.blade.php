{{-- LWRD – prepare the MFTA and endorse to the Administrator (regular step 11, emergency CAPEX step 9 / O&M step 6). --}}
<form method="POST" action="{{ route('office.projects.endorse-to-administrator', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">{{ __('The CAF is in. Compile the requirements, prepare the MFTA and the Memorandum for the Administrator, and give your recommendation.') }}</p>

    <fieldset>
        <legend class="block text-sm font-medium text-gray-700">{{ __('Recommendation') }}</legend>
        <div class="mt-2 grid grid-cols-2 gap-2">
            @foreach (['approve' => __('Approve'), 'disapprove' => __('Disapprove')] as $value => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-lwua-blue has-[:checked]:bg-blue-50">
                    <input type="radio" name="recommendation" value="{{ $value }}" @checked(old('recommendation') === $value) required class="text-lwua-blue focus:ring-lwua-blue">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-1" :messages="$errors->get('recommendation')" />
    </fieldset>

    <div>
        <x-input-label for="memorandum" :value="__('Memorandum for the Administrator (PDF)')" />
        <input id="memorandum" name="memorandum" type="file" accept="application/pdf,.pdf" required
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('memorandum')" />
    </div>

    <div>
        <x-input-label for="mfta" :value="__('MFTA for the release of funds (PDF, optional)')" />
        <input id="mfta" name="mfta" type="file" accept="application/pdf,.pdf"
               class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('mfta')" />
    </div>

    <div>
        <x-input-label for="remarks" :value="__('Summary of the evaluation')" />
        <textarea id="remarks" name="remarks" rows="4" maxlength="3000" required
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Endorse :ref to the Administrator?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Endorse to Administrator') }}
    </button>
</form>
