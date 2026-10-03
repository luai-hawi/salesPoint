import test from 'node:test';
import assert from 'node:assert/strict';

const registered = new Map();

globalThis.window = globalThis;
globalThis.document = {
    addEventListener() {},
    querySelectorAll() { return []; },
    createElement() {
        return {
            type: '',
            name: '',
            value: '',
        };
    },
};
globalThis.Alpine = {
    data(name, factory) {
        registered.set(name, factory);
    },
};

await import('../../public/js/product-forms.js');

test('product form alpine components register immediately when Alpine already exists', () => {
    assert.equal(registered.has('productForm'), true);
    assert.equal(registered.has('productIndex'), true);
    assert.equal(registered.has('outOfStockPage'), true);
});

test('product submit blocks native submit when duplicate lookup fails', async () => {
    let submitted = false;
    let prevented = false;
    let toastMessage = null;

    globalThis.window.SP = {
        fetchJson() {
            return Promise.reject(new Error('duplicate lookup failed'));
        },
        toast(message) {
            toastMessage = message;
        },
    };

    const component = registered.get('productForm')({
        duplicateCheckUrl: 'https://example.test/products/check-barcodes',
        strings: {
            stockError: 'stock error',
            duplicateLookupFailed: 'duplicate check failed',
        },
    });

    component.$refs = {
        mainBarcode: { value: 'ABC-123' },
    };
    component.$root = {
        querySelectorAll() { return []; },
        querySelector() { return null; },
    };
    component.$nextTick = (callback) => callback.call(component);
    component.barcodeRows = ['ALT-1'];
    const form = {
        querySelectorAll() { return []; },
        submit() { submitted = true; },
    };
    const result = await component.submitProductForm({
        target: form,
        preventDefault() { prevented = true; },
    });
    assert.equal(result, false);
    assert.equal(prevented, true);
    assert.equal(submitted, false);
    assert.equal(component.productFormBusy, false);
    assert.equal(toastMessage, 'duplicate lookup failed');
});

test('quick stock reports invalid inputs before sending and retries failures with the same intake UUID', async () => {
        const component = registered.get('productIndex')({
            quickStockUrl: '/products/__PRODUCT__/add-quantity',
            strings: { saving: 'Saving', submitStock: 'Add stock', stockError: 'Failed' },
        });
        const uuid = { value: '' };
        let valid = false;
        let requests = [];
        let notification;
        const form = {
            reportValidity() { return valid; },
            querySelector() { return uuid; },
            querySelectorAll() { return []; },
            appendChild() {},
        };
        component.$refs = { quickStockForm: form, quickStockSubmit: { disabled: false } };
        component.stockProductId = 7;
        const originalFormData = globalThis.FormData;
        globalThis.FormData = class {
            entries() { return [['amount', '2'], ['cost_price', '5']]; }
        };
        globalThis.SP = {
            fetchJson(url, options) {
                requests.push({ url, body: JSON.parse(options.body) });
                return Promise.reject(new Error('Server rejected funding'));
            },
            toast(message) { notification = message; },
        };
        try {
            component.submitQuickStock();
            assert.equal(requests.length, 0);
            valid = true;
            await component.submitQuickStock();
            assert.equal(component.stockSaving, false);
            assert.equal(notification, 'Server rejected funding');
            await component.submitQuickStock();
            assert.equal(requests[0].url, '/products/7/add-quantity');
            assert.ok(requests[0].body.intake_client_uuid);
            assert.equal(requests[1].body.intake_client_uuid, requests[0].body.intake_client_uuid);
        } finally {
            globalThis.FormData = originalFormData;
        }
    });

    test('switching quick stock product resets stale intake and preloads the selected product cost', () => {
        const component = registered.get('productIndex')({});
        let resets = 0;
        const uuid = { value: 'previous-product-request' };
        const cost = { value: '999' };
        const form = {
            reset() { resets++; },
            querySelector(selector) { return selector.includes('cost_price') ? cost : uuid; },
            querySelectorAll() { return []; },
        };
        component.$refs = { quickStockForm: form };
        component.$root = { querySelectorAll() { return []; } };
        component.$nextTick = (callback) => callback();
        component.openStockForm({ id: 8, name: 'Product eight', costPrice: 12 });
        assert.equal(resets, 1);
        assert.equal(cost.value, 12);
        assert.equal(uuid.value, '');
        assert.equal(component.stockFormOpen, true);
        uuid.value = 'retry-eight';
        component.openStockForm({ id: 8, name: 'Product eight', costPrice: 12 });
        assert.equal(resets, 1);
        assert.equal(uuid.value, 'retry-eight');
        component.openStockForm({ id: 9, name: 'Product nine', costPrice: 15 });
        assert.equal(resets, 2);
        assert.equal(cost.value, 15);
        assert.equal(uuid.value, '');
    });
