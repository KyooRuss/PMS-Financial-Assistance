{{-- LWRD – rapid assessment of financial viability (regular step 4). --}}
<form method="POST" action="{{ route('office.projects.viability-assessment', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">{{ __('Assess items 1–11 (financial statements, cash flow, business plan) and attach the loan records. Either rating goes to CPIC, which decides the priority.') }}</p>

    <fieldset>
        <legend class="block text-sm font-medium text-gray-700">{{ __('Rating') }}</legend>
        <div class="mt-2 grid grid-cols-2 gap-2">
            @foreach (['1' => __('Financially Viable'), '0' => __('Not Viable')] as $value => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-lwua-blue has-[:checked]:bg-blue-50">
                    <input type="radio" name="viable" value="{{ $value }}" @checked(old('viable') === (string) $value) required class="text-lwua-blue focus:ring-lwua-blue">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-1" :messages="$errors->get('viable')" />
    </fieldset>

    <div>
        <x-input-label for="findings" :value="__('Findings')" />
        <textarea id="findings" name="findings" rows="5" maxlength="3000" required
                  placeholder="{{ __('Liquidity, debt service capacity, tariff, existing loans…') }}"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('findings') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('findings')" />
    </div>

    <div>
        <x-input-label for="loan_records" :value="__('Loan Records from LPMD/FS (PDF, optional)')" />
        <input id="loan_records" name="loan_records" type="file" accept="application/pdf,.pdf" class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('loan_records')" />
    </div>

    <div>
        <x-input-label for="assessment" :value="__('Assessment report (PDF, optional)')" />
        <input id="assessment" name="assessment" type="file" accept="application/pdf,.pdf" class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
        <x-input-error class="mt-1" :messages="$errors->get('assessment')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Forward :ref to CPIC?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Forward to CPIC') }}
    </button>
</form>
