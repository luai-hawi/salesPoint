<?php

use App\Models\IdempotencyKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Support\Builds;

uses(Builds::class);

beforeEach(function () {
    Route::middleware(['web', 'auth'])->post('/_test/offline/idempotent', function (Request $request) {
        $count = (int) Cache::increment('offline-test-counter');

        if ($request->boolean('redirect')) {
            return redirect('/offline/queue');
        }

        if ($request->boolean('server_error')) {
            return response('server-error', 500);
        }

        return response()->json([
            'count' => $count,
            'value' => $request->input('value'),
        ], $request->integer('status', 200));
    });

    Route::middleware(['web', 'auth'])->post('/_test/offline/idempotent-alt', function (Request $request) {
        $count = (int) Cache::increment('offline-test-counter-alt');

        return response()->json([
            'count' => $count,
            'value' => $request->input('value'),
        ]);
    });

    Route::middleware(['web', 'auth'])->post('/_test/offline/idempotent-large', function () {
        return response(str_repeat('L', 70000), 200, ['Content-Type' => 'text/plain']);
    });
});

test('finished idempotent requests replay stored responses', function () {
    $user = $this->makeOwner();

    $first = $this->actingAs($user)->post('/_test/offline/idempotent', [
        'value' => 'first',
    ], [
        'X-Idempotency-Key' => 'same-key',
    ]);

    $second = $this->actingAs($user)->post('/_test/offline/idempotent', [
        'value' => 'second',
    ], [
        'X-Idempotency-Key' => 'same-key',
    ]);

    $first->assertOk()->assertJson(['count' => 1, 'value' => 'first']);
    $second->assertOk()->assertJson(['count' => 1, 'value' => 'first']);
    expect($second->headers->get('X-Idempotent-Replay'))->toBe('true');
});

test('same key is isolated per user', function () {
    $ownerA = $this->makeOwner();
    $ownerB = $this->makeOwner();

    $this->actingAs($ownerA)->post('/_test/offline/idempotent', ['value' => 'a'], [
        'X-Idempotency-Key' => 'shared-key',
    ])->assertOk()->assertJson(['count' => 1]);

    $this->actingAs($ownerB)->post('/_test/offline/idempotent', ['value' => 'b'], [
        'X-Idempotency-Key' => 'shared-key',
    ])->assertOk()->assertJson(['count' => 2, 'value' => 'b']);
});

test('same raw key may be reused on another endpoint safely', function () {
    $user = $this->makeOwner();

    $this->actingAs($user)->post('/_test/offline/idempotent', ['value' => 'first'], [
        'X-Idempotency-Key' => 'route-key',
    ])->assertOk()->assertJson(['count' => 1, 'value' => 'first']);

    $this->actingAs($user)->post('/_test/offline/idempotent-alt', ['value' => 'second'], [
        'X-Idempotency-Key' => 'route-key',
    ])->assertOk()->assertJson(['count' => 1, 'value' => 'second']);
});

test('in flight duplicate requests return retry later', function () {
    $user = $this->makeOwner();
    Cache::add('idempotency-lock:' . $user->id . ':POST:/_test/offline/idempotent:' . sha1('POST|/_test/offline/idempotent|locked-key'), 1, now()->addMinute());

    $this->actingAs($user)->post('/_test/offline/idempotent', ['value' => 'x'], [
        'X-Idempotency-Key' => 'locked-key',
    ])->assertStatus(409)->assertJson(['message' => __('sync.retry_later')]);
});

test('server errors are not stored for replay', function () {
    $user = $this->makeOwner();

    $this->actingAs($user)->post('/_test/offline/idempotent', [
        'server_error' => '1',
    ], [
        'X-Idempotency-Key' => 'server-key',
    ])->assertStatus(500);

    $this->actingAs($user)->post('/_test/offline/idempotent', [
        'value' => 'retry',
    ], [
        'X-Idempotency-Key' => 'server-key',
    ])->assertOk()->assertJson(['count' => 2, 'value' => 'retry']);
});

test('requests without an idempotency key are unaffected', function () {
    $user = $this->makeOwner();

    $this->actingAs($user)->post('/_test/offline/idempotent', ['value' => 'first'])
        ->assertOk()
        ->assertJson(['count' => 1, 'value' => 'first']);

    $this->actingAs($user)->post('/_test/offline/idempotent', ['value' => 'second'])
        ->assertOk()
        ->assertJson(['count' => 2, 'value' => 'second']);
});

test('oversized responses are not stored for replay', function () {
    $user = $this->makeOwner();

    $this->actingAs($user)->post('/_test/offline/idempotent-large', [], [
        'X-Idempotency-Key' => 'large-key',
    ])->assertOk();

    expect(IdempotencyKey::query()->where('request_key', 'large-key')->exists())->toBeFalse();
});

test('redirects replay with the stored location', function () {
    $user = $this->makeOwner();

    $this->actingAs($user)->post('/_test/offline/idempotent', [
        'redirect' => '1',
    ], [
        'X-Idempotency-Key' => 'redirect-key',
    ])->assertRedirect('/offline/queue');

    $response = $this->actingAs($user)->post('/_test/offline/idempotent', [
        'redirect' => '1',
    ], [
        'X-Idempotency-Key' => 'redirect-key',
    ]);

    $response->assertRedirect('/offline/queue');
    expect($response->headers->get('X-Idempotent-Replay'))->toBe('true');
});

test('prune command deletes records older than seven days', function () {
    $user = $this->makeOwner();

    IdempotencyKey::query()->create([
        'user_id' => $user->id,
        'key' => sha1('POST|/_test/offline/idempotent|old-key'),
        'request_key' => 'old-key',
        'method' => 'POST',
        'path' => '/_test/offline/idempotent',
        'status' => 200,
        'body' => '{}',
        'created_at' => now()->subDays(8),
    ]);

    IdempotencyKey::query()->create([
        'user_id' => $user->id,
        'key' => sha1('POST|/_test/offline/idempotent|fresh-key'),
        'request_key' => 'fresh-key',
        'method' => 'POST',
        'path' => '/_test/offline/idempotent',
        'status' => 200,
        'body' => '{}',
        'created_at' => now()->subDay(),
    ]);

    Artisan::call('offline:prune-idempotency');

    expect(IdempotencyKey::query()->where('request_key', 'old-key')->exists())->toBeFalse()
        ->and(IdempotencyKey::query()->where('request_key', 'fresh-key')->exists())->toBeTrue();
});
