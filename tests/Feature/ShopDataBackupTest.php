<?php

use App\Models\Bill;
use Tests\Support\Builds;

uses(Builds::class);

function backupZipContents($response): array
{
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $files[$name] = $zip->getFromName($name);
    }
    $zip->close();
    @unlink($path);

    return $files;
}

it('lets the shop owner download a full backup of only their own data', function () {
    $owner = $this->makeOwner();
    $owner->forceFill(['admin_notes' => 'PRIVATE-ADMIN-NOTE'])->save();
    $product = $this->makeProduct($owner, ['name' => 'Backup Coffee', 'quantity' => 50]);
    $customer = $this->makeCustomer($owner, ['name' => 'Backup Customer']);
    $this->actingAs($owner)->postJson('/bills', [
        'product_ids' => [$product->id], 'quantities' => [2], 'discounts' => [0],
        'cost_prices' => [(float) $product->cost_price], 'selling_prices' => [(float) $product->selling_price],
        'discount_types' => ['total'], 'product_tags' => [null], 'customer_id' => $customer->id, 'paid_amount' => 1,
    ])->assertOk();
    $this->makeEmployee($owner, ['create_bills']);

    $other = $this->makeOwner();
    $this->makeProduct($other, ['name' => 'Foreign Product']);

    $response = $this->actingAs($owner)->get(route('dashboard.backup-data'))->assertOk();
    $files = backupZipContents($response);

    expect(array_keys($files))->toContain('products.csv', 'bills.csv', 'bill_product.csv', 'customers.csv', 'customer_payments.csv', 'program_accounts.csv', 'backup-info.json')
        ->and($files['products.csv'])->toStartWith("\xEF\xBB\xBF")->toContain('Backup Coffee')->not->toContain('Foreign Product')
        ->and($files['customers.csv'])->toContain('Backup Customer')
        ->and(substr_count(trim($files['bill_product.csv']), "\n"))->toBe(1)
        ->and($files['program_accounts.csv'])->not->toContain('password')->not->toContain('PRIVATE-ADMIN-NOTE')->not->toContain('$2y$');

    $info = json_decode($files['backup-info.json'], true);
    expect($info['tables']['bills'])->toBe(Bill::withoutGlobalScopes()->where('user_id', $owner->id)->count())
        ->and($info['tables']['program_accounts'])->toBe(2);
});

it('does not let employees or admins download the shop backup', function () {
    $owner = $this->makeOwner();
    $employee = $this->makeEmployee($owner, ['view_financial']);

    $this->actingAs($employee)->get(route('dashboard.backup-data'))->assertForbidden();
    $this->actingAs($this->makeAdmin())->get(route('dashboard.backup-data'))->assertForbidden();
});

it('shows the backup button to owners on the financial dashboard and profile page', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)->get(route('dashboard.financial'))->assertOk()
        ->assertSee(route('dashboard.backup-data'), false)
        ->assertSee(route('dashboard.export-data'), false);
    $this->actingAs($owner)->get(route('profile.edit'))->assertOk()
        ->assertSee(route('dashboard.backup-data'), false);

    $employee = $this->makeEmployee($owner, ['view_financial']);
    $this->actingAs($employee)->get(route('dashboard.financial'))->assertOk()
        ->assertDontSee(route('dashboard.backup-data'), false);
});
