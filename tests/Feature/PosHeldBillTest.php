<?php

use App\Models\HeldBill;
use App\Models\User;
use Tests\Support\Builds;

uses(Builds::class);

function posSnapshot(array $overrides = []): array
{
    return array_replace_recursive([
        'rows' => [[
            'product_id' => 1,
            'name' => 'Widget',
            'quantity' => 2,
            'selling_price' => 10,
            'cost_price' => 5,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => ['Gift@1.00'],
            'imeis' => [],
        ]],
        'customer' => ['id' => 1, 'name' => 'Customer One'],
        'note' => 'Sample note',
        'bill_discount_percent' => 0,
        'is_damaged' => false,
        'is_returned' => false,
        'bill_date' => now()->toDateString(),
        'paid_amount' => 5,
        'payment_method' => 'cash',
        'client_uuid' => 'held-test-uuid',
    ], $overrides);
}

test('owner can create and list held bills', function () {
    $owner = $this->makeOwner();
    $customer = $this->makeCustomer($owner);
    $product = $this->makeProduct($owner);

    $payload = posSnapshot([
        'rows' => [[
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 2,
            'selling_price' => 10,
            'cost_price' => 5,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => [],
            'imeis' => [],
        ]],
        'customer' => ['id' => $customer->id, 'name' => $customer->name],
    ]);

    $response = $this->actingAs($owner)->postJson(route('pos.held.store'), [
        'label' => 'Table 4',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'payload' => $payload,
        'client_uuid' => 'held-owner-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('held_bill.label', 'Table 4')
        ->assertJsonPath('held_bill.items_count', 1);

    expect(HeldBill::withoutGlobalScopes()->where('user_id', $owner->id)->count())->toBe(1);

    $this->actingAs($owner)
        ->getJson(route('pos.held.index'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.label', 'Table 4');
});

test('held bill can be renamed resumed and deleted', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $this->actingAs($owner);

    $heldBill = new HeldBill([
        'created_by' => $owner->id,
        'label' => 'Walk-in',
        'items_count' => 1,
        'total' => 10,
        'payload' => posSnapshot([
            'rows' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'selling_price' => 10,
                'cost_price' => 5,
                'discount' => 0,
                'discount_type' => 'total',
                'tags' => [],
                'imeis' => [],
            ]],
        ]),
        'client_uuid' => 'held-owner-2',
    ]);
    $heldBill->save();

    $this->actingAs($owner)
        ->putJson(route('pos.held.update', $heldBill), ['label' => 'Renamed cart'])
        ->assertOk()
        ->assertJsonPath('held_bill.label', 'Renamed cart');

    $resume = $this->actingAs($owner)
        ->postJson(route('pos.held.resume', $heldBill));

    $resume->assertOk()
        ->assertJsonPath('snapshot.client_uuid', 'held-test-uuid')
        ->assertJsonPath('held_bill_id', $heldBill->id);

    $this->assertDatabaseHas('held_bills', ['id' => $heldBill->id]);

    $this->actingAs($owner)
        ->postJson(route('pos.held.acknowledge', $heldBill))
        ->assertOk();

    $this->assertDatabaseMissing('held_bills', ['id' => $heldBill->id]);

    $replacement = HeldBill::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'label' => 'Delete me',
        'items_count' => 1,
        'total' => 10,
        'payload' => posSnapshot(),
        'client_uuid' => 'held-owner-3',
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('pos.held.destroy', $replacement))
        ->assertOk();

    $this->assertDatabaseMissing('held_bills', ['id' => $replacement->id]);
});

test('held bills enforce the per shop limit', function () {
    $owner = $this->makeOwner();

    foreach (range(1, 50) as $index) {
        HeldBill::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'label' => 'Held ' . $index,
            'items_count' => 1,
            'total' => 10,
            'payload' => posSnapshot(['client_uuid' => 'held-limit-' . $index]),
            'client_uuid' => 'held-limit-' . $index,
        ]);
    }

    $this->actingAs($owner)
        ->postJson(route('pos.held.store'), [
            'label' => 'Overflow',
            'payload' => posSnapshot(['client_uuid' => 'held-limit-overflow']),
            'client_uuid' => 'held-limit-overflow',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['held_bills']);
});

test('held bills reject oversized payloads and invalid rows', function () {
    $owner = $this->makeOwner();
    $hugeRows = [];
    foreach (range(1, 3500) as $index) {
        $hugeRows[] = [
            'product_id' => $index,
            'name' => 'Product ' . $index,
            'quantity' => 1,
            'selling_price' => 10,
            'cost_price' => 5,
            'discount' => 0,
            'discount_type' => 'total',
            'tags' => [],
            'imeis' => [],
        ];
    }

    $this->actingAs($owner)
        ->postJson(route('pos.held.store'), [
            'label' => 'Broken',
            'payload' => ['rows' => []],
            'client_uuid' => 'held-invalid',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payload.rows']);

    $this->actingAs($owner)
        ->postJson(route('pos.held.store'), [
            'label' => 'Huge',
            'payload' => posSnapshot(['rows' => $hugeRows]),
            'client_uuid' => 'held-huge',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payload']);
});

test('held bills are tenant scoped and require create bills permission', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();
    $product = $this->makeProduct($owner);
    $heldBill = HeldBill::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'label' => 'Scoped',
        'items_count' => 1,
        'total' => 10,
        'payload' => posSnapshot([
            'rows' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'selling_price' => 10,
                'cost_price' => 5,
                'discount' => 0,
                'discount_type' => 'total',
                'tags' => [],
                'imeis' => [],
            ]],
        ]),
        'client_uuid' => 'held-scoped',
    ]);

    $employee = $this->makeEmployee($owner, ['view_bills']);

    $this->actingAs($employee)
        ->getJson(route('pos.held.index'))
        ->assertForbidden();

    $this->actingAs($otherOwner)
        ->postJson(route('pos.held.resume', $heldBill))
        ->assertNotFound();
});

test('held bills page renders for owners', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)
        ->get(route('pos.held.index'))
        ->assertOk();
});

test('html resume redirects to dashboard and keeps recovery until acknowledge', function () {
    $owner = $this->makeOwner();
    $product = $this->makeProduct($owner);

    $heldBill = HeldBill::withoutGlobalScopes()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'label' => 'HTML resume',
        'items_count' => 1,
        'total' => 10,
        'payload' => posSnapshot([
            'rows' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'selling_price' => 10,
                'cost_price' => 5,
                'discount' => 0,
                'discount_type' => 'total',
                'return_cost' => 4,
                'tags' => [],
                'imeis' => [],
            ]],
        ]),
        'client_uuid' => 'held-html-resume',
    ]);

    $this->actingAs($owner)
        ->post(route('pos.held.resume', $heldBill))
        ->assertRedirect(route('dashboard'));

    expect((float) session('pos_held_recovery.rows.0.return_cost'))->toBe(4.0)
        ->and(session('pos_held_recovery_id'))->toBe($heldBill->id);

    $this->assertDatabaseHas('held_bills', ['id' => $heldBill->id]);
});
