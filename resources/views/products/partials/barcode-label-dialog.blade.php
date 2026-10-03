<div id="barcode-label-modal" class="hidden fixed inset-0 z-[200] flex items-center justify-center p-4"
    dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="absolute inset-0 bg-black/50" data-bl-close></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[92vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">{{ __('messages.Label Options') }}</h3>
            <button type="button" data-bl-close
                class="text-gray-400 hover:text-gray-600 text-2xl leading-none px-2">&times;</button>
        </div>

        <div class="p-5 space-y-4 text-sm">
            <div>
                <label class="flex items-center gap-2 font-medium text-gray-700 mb-1">
                    <input type="checkbox" id="bl-show-name" checked class="rounded border-gray-300">
                    {{ __('messages.Product Name') }}
                </label>
                <input type="text" id="bl-name"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <label class="flex items-center gap-2 font-medium text-gray-700">
                <input type="checkbox" id="bl-show-price" checked class="rounded border-gray-300">
                {{ __('messages.Show Price') }}
            </label>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-medium text-gray-700 mb-1">{{ __('messages.Production Date') }}</label>
                    <input type="date" id="bl-prod-date"
                        class="w-full border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block font-medium text-gray-700 mb-1">{{ __('messages.Expiry Date') }}</label>
                    <input type="date" id="bl-exp-date"
                        class="w-full border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="block font-medium text-gray-700 mb-1">{{ __('messages.Extra Text') }}</label>
                <input type="text" id="bl-extra" maxlength="80"
                    placeholder="{{ __('messages.Optional note') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-medium text-gray-700 mb-1">{{ __('messages.Label Size') }}</label>
                    <select id="bl-size"
                        class="w-full border border-gray-300 rounded-lg px-2 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="38x25">38 × 25 mm</option>
                        <option value="40x30">40 × 30 mm</option>
                        <option value="50x30" selected>50 × 30 mm</option>
                        <option value="58x40">58 × 40 mm</option>
                        <option value="70x30">70 × 30 mm</option>
                    </select>
                </div>
                <div>
                    <label class="block font-medium text-gray-700 mb-1">{{ __('messages.Copies') }}</label>
                    <input type="number" id="bl-copies" min="1" max="500" value="1"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" data-bl-close
                class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                {{ __('messages.Cancel') }}
            </button>
            <button type="button" id="bl-print-btn"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                {{ __('messages.Print') }}
            </button>
        </div>
    </div>
</div>

<script>
    (function() {
        const modal = document.getElementById('barcode-label-modal');
        const $ = (id) => document.getElementById(id);
        const SIZE_KEY = 'sp-barcode-label-size';

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[c]);

        const fmtDate = (v) => {
            if (!v) return '';
            const [y, m, d] = v.split('-');
            return `${d}/${m}/${y}`;
        };

        function close() {
            modal.classList.add('hidden');
        }

        modal.querySelectorAll('[data-bl-close]').forEach(el => el.addEventListener('click', close));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });

        window.openBarcodeLabelDialog = function() {
            const barcode = $('barcode').value.trim();
            if (!barcode) {
                alert('{{ __('messages.Please enter a barcode first.') }}');
                return;
            }
            $('bl-name').value = document.querySelector('input[name="name"]')?.value.trim() || '';
            const savedSize = localStorage.getItem(SIZE_KEY);
            if (savedSize && $('bl-size').querySelector(`option[value="${savedSize}"]`)) {
                $('bl-size').value = savedSize;
            }
            modal.classList.remove('hidden');
        };

        $('bl-print-btn').addEventListener('click', function() {
            const barcode = $('barcode').value.trim();
            const price = $('selling_price').value.trim();
            const name = $('bl-show-name').checked ? $('bl-name').value.trim() : '';
            const showPrice = $('bl-show-price').checked && price !== '';
            const prod = fmtDate($('bl-prod-date').value);
            const exp = fmtDate($('bl-exp-date').value);
            const extra = $('bl-extra').value.trim();
            const copies = Math.min(500, Math.max(1, parseInt($('bl-copies').value) || 1));
            const [w, h] = $('bl-size').value.split('x').map(Number);
            localStorage.setItem(SIZE_KEY, $('bl-size').value);

            const dates = [
                prod ? `<span>{{ __('messages.Prod.') }} ${esc(prod)}</span>` : '',
                exp ? `<span>{{ __('messages.Exp.') }} ${esc(exp)}</span>` : '',
            ].filter(Boolean).join('');

            const label = `
                <div class="label">
                    ${name ? `<div class="name">${esc(name)}</div>` : ''}
                    <svg class="bc" preserveAspectRatio="none"></svg>
                    <div class="code">${esc(barcode)}</div>
                    ${showPrice ? `<div class="price">₪${esc(price)}</div>` : ''}
                    ${dates ? `<div class="dates">${dates}</div>` : ''}
                    ${extra ? `<div class="extra">${esc(extra)}</div>` : ''}
                </div>`;

            const printWindow = window.open('', '_blank', 'width=500,height=600');
            if (!printWindow) {
                alert('{{ __('messages.Please allow popups for printing') }}');
                return;
            }

            printWindow.document.write(`<!DOCTYPE html>
<html dir="auto">
<head>
<meta charset="utf-8">
<title>{{ __('messages.Barcode Print') }}</title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
<style>
    @page { size: ${w}mm ${h}mm; margin: 0; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #eee; }
    .label {
        width: ${w}mm; height: ${h}mm; padding: 1mm 1.5mm;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        overflow: hidden; background: #fff; text-align: center; line-height: 1.15;
        margin: 4mm auto; page-break-after: always; break-after: page;
    }
    .label:last-child { page-break-after: auto; break-after: auto; }
    .name { font-size: 7pt; font-weight: bold; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bc { width: 100%; flex: 1 1 auto; min-height: 6mm; max-height: ${Math.round(h * 0.5)}mm; display: block; }
    .code { font-size: 6pt; letter-spacing: 0.5px; }
    .price { font-size: 8pt; font-weight: bold; }
    .dates { font-size: 5.5pt; display: flex; gap: 2mm; justify-content: center; white-space: nowrap; }
    .extra { font-size: 5.5pt; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .toolbar { text-align: center; padding: 8px; }
    .toolbar button { padding: 6px 14px; background: #2563eb; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
    @media print {
        body { background: #fff; }
        .label { margin: 0; }
        .toolbar { display: none; }
    }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">{{ __('messages.Print') }}</button></div>
<div class="labels">${label.repeat(copies)}</div>
<script>
    window.addEventListener('load', function() {
        if (typeof JsBarcode === 'undefined') return;
        JsBarcode('.bc', ${JSON.stringify(barcode).replace(/</g, '\\u003c')}, { format: 'CODE128', width: 2, height: 60, displayValue: false, margin: 0 });
        setTimeout(function() { window.print(); }, 300);
    });
<\/script>
</body>
</html>`);
            printWindow.document.close();
            close();
        });
    })();
</script>
