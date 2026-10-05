{{--
    Administrator – act on LWRD's recommendation (regular step 12, emergency CAPEX 10 / O&M 7).
    After a disapproval the same step waits for LWRD's letter to the water district.
--}}
@if ($project->awaitsDisapprovalLetter())
    @php
        $decision = $project->logsForStep('final_decision')->first();
    @endphp

    <form method="POST" action="{{ route('office.projects.disapproval-letter', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
        @csrf
        <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-900 ring-1 ring-red-200">
            <p class="font-semibold">{{ __('The Administrator disapproved this request') }}
                @if ($decision) · {{ $decision->created_at->format('M j, Y') }} @endif
            </p>
            @if ($decision?->remarks)
                <p class="mt-1 whitespace-pre-line">{{ $decision->remarks }}</p>
            @endif
        </div>
        <p class="text-sm text-gray-700">{{ __('Prepare the letter informing the water district of the disapproval. Sending it closes the request.') }}</p>

        <div>
            <x-input-label for="letter" :value="__('Letter to the water district (PDF)')" />
            <input id="letter" name="letter" type="file" accept="application/pdf,.pdf" required
                   class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
            <x-input-error class="mt-1" :messages="$errors->get('letter')" />
        </div>

        <div>
            <x-input-label for="remarks" :value="__('Message (optional)')" />
            <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
        </div>

        <button type="submit" onclick="return confirm(@js(__('Send the disapproval letter for :ref? The request will be closed.', ['ref' => $project->reference])))"
                class="w-full rounded-md bg-lwua-dark px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:brightness-110">
            {{ __('Send Letter & Close') }}
        </button>
    </form>
@else
    @php
        $endorsement = $project->logsForStep('complete_evaluation')->first();
    @endphp

    <form method="POST" action="{{ route('office.projects.approve', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
        @csrf
        @if ($endorsement)
            <div class="rounded-md bg-gray-50 p-4 text-sm ring-1 ring-gray-100">
                <p class="font-semibold text-gray-900">{{ __('LWRD recommendation') }}</p>
                <p class="mt-1 text-gray-700">{{ $endorsement->action }}</p>
                @if ($endorsement->remarks)
                    <p class="mt-2 whitespace-pre-line text-gray-700">{{ $endorsement->remarks }}</p>
                @endif
                @foreach ($endorsement->documents as $file)
                    <a href="{{ route('documents.show', $file) }}" target="_blank" rel="noopener" class="mt-1 block truncate text-lwua-blue underline">{{ $file->original_name }}</a>
                @endforeach
            </div>
        @endif

        <div>
            <x-input-label for="remarks" :value="__('Remarks (required when disapproving)')" />
            <textarea id="remarks" name="remarks" rows="3" maxlength="3000"
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lwua-blue focus:ring-lwua-blue">{{ old('remarks') }}</textarea>
            <x-input-error class="mt-1" :messages="$errors->get('remarks')" />
        </div>

        <div>
            <x-input-label for="signed_memorandum" :value="__('Signed memorandum (PDF, optional, on approval)')" />
            <input id="signed_memorandum" name="signed_memorandum" type="file" accept="application/pdf,.pdf"
                   class="mt-1 block w-full text-xs text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs">
            <p class="mt-1 text-xs text-gray-500">{{ __('Added to LWUA Documents as "Approved Loan per Memorandum for the Administrator".') }}</p>
            <x-input-error class="mt-1" :messages="$errors->get('signed_memorandum')" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" onclick="return confirm(@js(__('Approve the financial assistance for :ref?', ['ref' => $project->reference])))"
                    class="rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold uppercase text-white shadow hover:bg-green-700">
                {{ __('Approve') }}
            </button>
            <button type="submit" formaction="{{ route('office.projects.disapprove', $project) }}" formnovalidate
                    onclick="return confirm(@js(__('Disapprove :ref? LWRD will inform the water district.', ['ref' => $project->reference])))"
                    class="rounded-md border border-red-600 bg-white px-4 py-2.5 text-sm font-semibold uppercase text-red-700 hover:bg-red-50">
                {{ __('Disapprove') }}
            </button>
        </div>
    </form>
@endif
