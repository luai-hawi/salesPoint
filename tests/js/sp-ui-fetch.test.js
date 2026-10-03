import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const source = fs.readFileSync(
    path.join(path.dirname(fileURLToPath(import.meta.url)), '../../public/js/sp-ui.js'),
    'utf8',
);

function load(response = { ok: true, status: 200, body: '{"ok":true}' }) {
    const calls = [];
    const window = { SP_I18N: {} };
    const document = {
        documentElement: { getAttribute: () => 'ltr' },
        querySelector: () => ({ getAttribute: () => 'csrf-token-value' }),
        addEventListener() {},
        createElement: () => ({ classList: { add() {}, remove() {} }, setAttribute() {}, appendChild() {} }),
        body: { contains: () => false, appendChild() {} },
    };
    const sandbox = {
        window,
        document,
        FormData: class FormData {},
        requestAnimationFrame() {},
        setTimeout,
        fetch(url, init) {
            calls.push({ url, init });

            return Promise.resolve({
                ok: response.ok,
                status: response.status,
                text: () => Promise.resolve(response.body),
            });
        },
    };
    vm.createContext(sandbox);
    vm.runInContext(source, sandbox);

    return { SP: window.SP, calls };
}

test('fetchJson serialises plain objects as JSON', async () => {
    const { SP, calls } = load();

    await SP.fetchJson('/x', { method: 'POST', body: { a: 1 } });

    assert.equal(calls[0].init.headers['Content-Type'], 'application/json');
    assert.equal(calls[0].init.body, '{"a":1}');
    assert.equal(calls[0].init.headers['X-CSRF-TOKEN'], 'csrf-token-value');
});

test('fetchJson marks pre-serialised JSON strings as application/json (Laravel must parse them)', async () => {
    const { SP, calls } = load();

    await SP.fetchJson('/pos/held', { method: 'POST', body: JSON.stringify({ payload: { rows: [1] } }) });

    assert.equal(calls[0].init.headers['Content-Type'], 'application/json');
    assert.equal(calls[0].init.body, '{"payload":{"rows":[1]}}');
});

test('fetchJson keeps an explicit content type and leaves non-JSON bodies alone', async () => {
    const { SP, calls } = load();
    const params = new URLSearchParams({ a: '1' });

    await SP.fetchJson('/a', { method: 'POST', body: 'a=1', headers: { 'content-type': 'application/x-www-form-urlencoded' } });
    await SP.fetchJson('/b', { method: 'POST', body: params });

    assert.equal(calls[0].init.headers['Content-Type'], undefined);
    assert.equal(calls[0].init.headers['content-type'], 'application/x-www-form-urlencoded');
    assert.equal(calls[1].init.body, params);
    assert.equal(calls[1].init.headers['Content-Type'], undefined);
});

test('fetchJson rejects with the server message and status', async () => {
    const { SP } = load({ ok: false, status: 422, body: '{"message":"Invalid data"}' });

    await assert.rejects(
        () => SP.fetchJson('/x', { method: 'POST', body: '{}' }),
        (error) => error.message === 'Invalid data' && error.status === 422,
    );
});
