@props(['returnUrl'])
<div class="mb-4 flex items-center justify-between no-print print:hidden">
    <button type="button" onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('finance.common.print') }}</button>
    <a href="{{ $returnUrl }}" onclick="if ({{ request()->boolean('popup') ? 'true' : 'false' }} || (window.opener && !window.opener.closed)) { window.close(); }" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">{{ __('ui.close') }}</a>
</div>
