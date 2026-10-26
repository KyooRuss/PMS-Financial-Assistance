<x-modal name="view-acronyms-modal" maxWidth="md" focusable="true">
    <div class="px-8 py-8 text-center">
        <h2 class="text-base font-bold uppercase tracking-wide text-gray-800">
            {{ __('ACRONYMS') }}
        </h2>

        <h2 class="mt-5 text-left text-sm text-gray-700"><strong>LWD - </strong>{{ __('Local Water District') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>WSUDD - </strong>{{ __('Water and Sanitation Utilities Development Department') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>ES - </strong>{{ __('Engineering Service') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>LWRD - </strong>{{ __('Loans and Water Rates Department') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>CPIC - </strong>{{ __('Capital/Project Investment Committe') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>BFMD - </strong>{{ __('Budget and Fund Management Division') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>LPMD - </strong>{{ __('Loans Portfolio Managament Department') }}</h2>
        <h2 class="mt-1 text-left text-sm text-gray-700"><strong>FS - </strong>{{ __('Financial Service') }}</h2>

        <button type="button"
                x-on:click="$dispatch('close')"
                class="mt-6 text-sm font-medium text-gray-500 hover:text-gray-800">
                {{ __('Cancel') }}
        </button>
    </div>
</x-modal>