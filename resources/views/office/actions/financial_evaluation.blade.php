{{--
    LWRD – final financial evaluation (regular step 9, emergency CAPEX step 7 / O&M step 4).
    First the compliance letter; once the water district sends its documents, review them.
--}}
@if ($project->complianceReceived())
    {{-- Rendered inside the page-wide #action-form: the post-evaluation checklist flags are part of it. --}}
    <p class="mt-3 text-sm text-gray-700">{{ __('The water district sent its post-evaluation documents. Check them on the left. Accept them to request the CAF from BFMD/FS, or flag the items to fix and return them.') }}</p>

    @unless ($project->type->isEmergency())
        <p @class([
            'mt-3 rounded-md px-3 py-2 text-xs',
            'bg-green-50 text-green-800' => $project->cfp_concurred_at,
            'bg-amber-50 text-amber-800' => ! $project->cfp_concurred_at,
        ])>
            {{ $project->cfp_concurred_at ? __('The water district concurred with the cash flow projections.') : __('No concurrence with the cash flow projections yet.') }}
        </p>
    @endunless

    <div class="mt-4">
        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
        <textarea id="remarks" name="remarks" rows="3" maxlength="2000"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
    </div>

    <div class="mt-4 flex flex-col gap-2">
        <button type="submit" formaction="{{ route('office.projects.compliance.accept', $project) }}"
                onclick="return confirm(@js(__('Accept the documents of :ref and request the CAF?', ['ref' => $project->reference])))"
                class="rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-700">
            {{ __('Accept – Request CAF') }}
        </button>
        <button type="submit"
                onclick="return confirm(@js(__('Return the flagged post-evaluation documents of :ref to the water district?', ['ref' => $project->reference])))"
                class="rounded-md border border-amber-600 bg-white px-4 py-2.5 text-sm font-semibold uppercase text-amber-700 hover:bg-amber-50">
            {{ __('Return to Water District') }}
        </button>
    </div>
@else
    <form method="POST" action="{{ route('office.projects.compliance.request', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
        @csrf
        <p class="text-sm text-gray-700">
            @if ($project->type->isEmergency())
                {{ __('Ask the water district for its :count post-evaluation documents.', ['count' => $project->complianceRequirements()->count()]) }}
            @else
                {{ __('Ask the water district for its concurrence with the cash flow projections and its :count post-evaluation documents (checklist items 20 to 25).', ['count' => $project->complianceRequirements()->count()]) }}
            @endif
        </p>

        <div>
            <x-input-label for="remarks" :value="__('Message to the water district')" />
            <textarea id="remarks" name="remarks" rows="4" maxlength="3000" required
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks', __('Please submit the post-evaluation documents listed in your PMS account.')) }}</textarea>
            <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
        </div>

        <div>
            <x-input-label for="letter" :value="__('Compliance letter (PDF, optional)')" />
            <input id="letter" name="letter" type="file" accept="application/pdf,.pdf"
                   class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
            <p class="mt-1 text-xs text-gray-500">{{ __('The water district can read this letter.') }}</p>
            <x-input-error class="mt-1" :messages="$errors->get('letter')" />
        </div>

        <button type="submit" onclick="return confirm(@js(__('Send the compliance letter for :ref?', ['ref' => $project->reference])))"
                class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
            {{ __('Send Compliance Letter') }}
        </button>
    </form>
@endif
