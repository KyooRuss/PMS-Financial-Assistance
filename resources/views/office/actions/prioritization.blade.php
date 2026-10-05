{{-- CPIC – funding / prioritization (regular step 5, emergency step 3). --}}
@php
    $yesGoesTo = $project->type->isEmergency()
        ? ($project->funding_purpose === \App\Enums\FundingPurpose::OperationsAndMaintenance ? __('LWRD (financial evaluation)') : __('ES (prepare LWUA-POW)'))
        : __('ES (prepare LWUA-POW)');
    $noMeans = $project->type->isEmergency()
        ? __('Returned to WSUDD to inform the water district.')
        : __('Kept on file for future funding.');
@endphp

<form method="POST" action="{{ route('office.projects.priority', $project) }}" class="mt-3 space-y-4">
    @csrf
    <p class="text-sm text-gray-700">
        @if ($project->type->isEmergency())
            {{ __('Review the situation report and recovery plan, then decide whether to fund this emergency request.') }}
        @else
            {{ __('Review the ES and LWRD rapid assessments, the WD-POW, the board resolution and the loan records, then decide.') }}
        @endif
    </p>

    <fieldset>
        <legend class="block text-sm font-medium text-gray-700">{{ __('Priority?') }}</legend>
        <div class="mt-2 space-y-2">
            <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-green-600 has-[:checked]:bg-green-50">
                <input type="radio" name="priority" value="1" @checked(old('priority') === '1') required class="mt-0.5 text-green-600 focus:ring-green-600">
                <span><span class="font-semibold">{{ __('Yes') }}</span> <span class="block text-xs text-gray-500">{{ __('Goes to :office.', ['office' => $yesGoesTo]) }}</span></span>
            </label>
            <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm has-[:checked]:border-red-600 has-[:checked]:bg-red-50">
                <input type="radio" name="priority" value="0" @checked(old('priority') === '0') required class="mt-0.5 text-red-600 focus:ring-red-600">
                <span><span class="font-semibold">{{ __('No') }}</span> <span class="block text-xs text-gray-500">{{ $noMeans }}</span></span>
            </label>
        </div>
        <x-input-error class="mt-1" :messages="$errors->get('priority')" />
    </fieldset>

    <div>
        <x-input-label for="remarks" :value="__('Remarks')" />
        <textarea id="remarks" name="remarks" rows="4" maxlength="3000" required
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <button type="submit" onclick="return confirm(@js(__('Record the CPIC decision for :ref?', ['ref' => $project->reference])))"
            class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
        {{ __('Record Decision') }}
    </button>
</form>
