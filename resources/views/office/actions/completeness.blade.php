{{--
    WSUDD – completeness check. Rendered inside the page-wide #action-form (the checklist flags are part of it).
    The amount box is not marked "required" because the Return button submits this same form; endorse() checks it.
--}}
<p class="mt-3 text-sm text-gray-700">{{ __('If requirements are complete, approve the request. Otherwise flag the items for revision and return it to the water district.') }}</p>

<div class="mt-4">
    <x-input-label for="amount" :value="__('Assign a New Loan Amount (₱)')" />
    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full tabular-nums"
                  :value="old('amount', $project->amount)" />
    <p class="mt-1 text-xs text-gray-500">{{ __('This amount replaces the requested one.') }}</p>
    <x-input-error class="mt-1" :messages="$errors->get('amount')" />
</div>

<div class="mt-4">
    <x-input-label for="remarks" :value="__('Remarks (required when rejecting)')" />
    <textarea id="remarks" name="remarks" rows="3" maxlength="2000"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
    <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
</div>

{{-- "Return to Water District" only appears once at least one checklist item is flagged for return. --}}
<div class="mt-4 flex flex-col gap-2"
     x-data="{
         flagged: false,
         countFlags() { this.flagged = this.$root.closest('form').querySelectorAll('input[name$=&quot;[flag]&quot;]:checked').length > 0 },
     }"
     x-init="countFlags(); $root.closest('form').addEventListener('change', () => countFlags())">
    <button type="submit" formaction="{{ route('office.projects.endorse', $project) }}"
            onclick="return confirm(@js(__('Approve :ref?', ['ref' => $project->reference])))"
            class="rounded-md bg-green-500 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-800">
        {{ __('Approve') }}
    </button>
    {{-- Ends the request: nobody acts on it after this. --}}
    <button type="submit" formaction="{{ route('office.projects.disapprove-request', $project) }}"
            onclick="return confirm(@js(__('Reject :ref? This ends the request and it cannot continue.', ['ref' => $project->reference])))"
            class="rounded-md bg-red-500 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-red-800">
        {{ __('Reject') }}
    </button>
    <button type="submit" x-show="flagged" x-cloak
            onclick="return confirm(@js(__('Return :ref to the water district with the flagged items?', ['ref' => $project->reference])))"
            class="rounded-md border border-amber-600 bg-white px-4 py-2.5 text-sm font-semibold uppercase text-amber-700 hover:bg-amber-50">
        {{ __('Return to Water District') }}
    </button>
</div>
