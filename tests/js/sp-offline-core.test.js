import test from 'node:test';
import assert from 'node:assert/strict';

await import('../../public/js/sp-offline-shared.js');

const shared = globalThis.SPOfflineShared;

test('partition names are user specific', () => {
    assert.equal(shared.buildPartitionName('pages', 7), 'sp-pages-v10-u-7');
    assert.equal(shared.buildPartitionName('data', 18), 'sp-data-v10-u-18');
});

test('safe read whitelist accepts expected endpoints', () => {
    assert.equal(shared.isSafeReadPath('/api/tags'), true);
    assert.equal(shared.isSafeReadPath('/products/searchAll?page=2'), true);
    assert.equal(shared.isSafeReadPath('/admin/dashboard'), false);
});

test('mutation queue skips excluded paths', () => {
    assert.equal(shared.shouldQueueMutation('/products'), true);
    assert.equal(shared.shouldQueueMutation('/bills/store'), false);
    assert.equal(shared.shouldQueueMutation('/staff/punch'), false);
});

test('navigation handling skips admin and auth routes', () => {
    assert.equal(shared.shouldHandleNavigation('/dashboard'), true);
    assert.equal(shared.shouldHandleNavigation('/admin/dashboard'), false);
    assert.equal(shared.shouldHandleNavigation('/login'), false);
});

test('derive offline label prefers provided label and falls back to path', () => {
    assert.equal(shared.deriveOfflineLabel({ label: 'Create customer', url: '/customers' }), 'Create customer');
    assert.equal(shared.deriveOfflineLabel({ url: '/customers/15/payments' }), 'Customers / # / Payments');
});

test('error extraction understands laravel json payloads', () => {
    const message = shared.extractErrorMessage(JSON.stringify({
        message: 'Validation failed',
        errors: { name: ['The name field is required.'] }
    }), 'application/json');

    assert.equal(message, 'Validation failed');
});

test('classification maps retry and auth outcomes', () => {
    assert.deepEqual(shared.classifyReplayResult({ status: 503 }), { state: 'retry' });
    assert.deepEqual(shared.classifyReplayResult({ status: 401, location: '/login' }), { state: 'auth' });
    assert.deepEqual(shared.classifyReplayResult({ status: 404, method: 'DELETE' }), { state: 'success' });
});

test('backoff grows exponentially and is capped', () => {
    assert.equal(shared.computeBackoffMs(1), 5000);
    assert.equal(shared.computeBackoffMs(3), 20000);
    assert.equal(shared.computeBackoffMs(99), 300000);
});

test('queue manager preserves creation order', async () => {
    const storage = shared.createMemoryStore();
    const queue = shared.createQueueManager({
        storage,
        now: () => 10
    });

    await queue.enqueue({ id: 'b', userId: 1, createdAt: 20 });
    await queue.enqueue({ id: 'a', userId: 1, createdAt: 10 });

    const items = await queue.list(1);
    assert.deepEqual(items.map((item) => item.id), ['a', 'b']);
});

test('ready-wrapped methods wait for slow initialization', async () => {
    const steps = [];
    let release;
    const ready = new Promise((resolve) => {
        release = resolve;
    });

    const method = shared.createReadyMethod(ready, async () => {
        steps.push('method');
        return ['queued'];
    }, []);

    const pending = method().then((result) => {
        steps.push('resolved');
        return result;
    });

    steps.push('before-release');
    assert.deepEqual(steps, ['before-release']);

    release();
    const result = await pending;

    assert.deepEqual(result, ['queued']);
    assert.deepEqual(steps, ['before-release', 'method', 'resolved']);
});

test('ready-wrapped methods can return safe fallbacks after init failure', async () => {
    const error = new Error('indexeddb');
    const method = shared.createReadyMethod(Promise.reject(error), async () => ['queued'], []);

    const result = await method();
    assert.deepEqual(result, []);
});
