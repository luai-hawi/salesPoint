<?php

use App\Models\Bill;
use App\Models\PurchaseBill;
use App\Services\Admin\BackupManager;
use App\Services\Admin\ImageOptimizer;
use App\Services\Admin\JunkCleaner;
use App\Services\Admin\ShopStorageService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Builds;

uses(Builds::class);

beforeEach(function (): void {
    Storage::fake('public');
});

test('admin storage index renders with invalid and null pictures data', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner(['image_limit' => 10, 'entry_limit' => 20]);
    $this->makeProduct($owner, ['pictures' => null]);
    $this->makeProduct($owner, ['pictures' => 'not-json']);

    $response = $this->actingAs($admin)->get(route('admin.storage.index'));

    $response->assertOk()
        ->assertSeeText((string) $owner->email)
        ->assertSeeText(__('admin_storage.title'));
});

test('admin storage index is robust with legacy mixed shop rows and default shop sorting', function () {
    $admin = $this->makeAdmin();

    $activeOwnerId = DB::table('users')->insertGetId([
        'name' => 'Alpha Shop',
        'owner_name' => 'Alpha Owner',
        'email' => 'alpha@example.test',
        'password' => bcrypt('password'),
        'role' => 'shop_owner',
        'account_type' => 'temp',
        'image_limit' => 5,
        'entry_limit' => 9,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $disabledOwnerId = DB::table('users')->insertGetId([
        'name' => 'Disabled Shop',
        'owner_name' => null,
        'email' => 'disabled@example.test',
        'password' => bcrypt('password'),
        'role' => 'disabled',
        'image_limit' => 0,
        'entry_limit' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('users')->insert([
        'name' => 'Staff Account',
        'owner_name' => null,
        'email' => 'staff@example.test',
        'password' => bcrypt('password'),
        'role' => 'employee',
        'shop_owner_id' => $activeOwnerId,
        'permissions' => json_encode(['view_products']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Storage::disk('public')->put('products/shared.jpg', makeJpegImage(1200, 900));

    DB::table('products')->insert([
        [
            'name' => 'Legacy Shared 1',
            'barcode' => 'LEG-001',
            'pictures' => json_encode(['products/shared.jpg', 'products/missing.jpg']),
            'quantity' => 1,
            'cost_price' => 5,
            'selling_price' => 10,
            'user_id' => $activeOwnerId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Legacy Shared 2',
            'barcode' => 'LEG-002',
            'pictures' => json_encode(['products/shared.jpg']),
            'quantity' => 2,
            'cost_price' => 5,
            'selling_price' => 12,
            'user_id' => $activeOwnerId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Broken JSON',
            'barcode' => 'LEG-003',
            'pictures' => 'broken-json',
            'quantity' => 2,
            'cost_price' => 7,
            'selling_price' => 13,
            'user_id' => $disabledOwnerId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'name' => 'Null Pictures',
            'barcode' => 'LEG-004',
            'pictures' => null,
            'quantity' => 2,
            'cost_price' => 7,
            'selling_price' => 13,
            'user_id' => $disabledOwnerId,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.storage.index'));

    $response->assertOk()
        ->assertSeeText('alpha@example.test')
        ->assertSeeText('disabled@example.test');
});

test('shop owner cannot access admin storage routes', function () {
    $owner = $this->makeOwner();

    $this->actingAs($owner)
        ->get(route('admin.storage.index'))
        ->assertForbidden();
});

test('admin storage shop page renders with fake images', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();

    Storage::disk('public')->put('products/demo.jpg', makeJpegImage(1200, 800));
    $this->makeProduct($owner, ['pictures' => json_encode(['products/demo.jpg'])]);

    $this->actingAs($admin)
        ->get(route('admin.storage.shop', $owner))
        ->assertOk()
        ->assertSeeText('products/demo.jpg');
});

test('entry usage aggregation matches user helper', function () {
    $owner = $this->makeOwner(['entry_limit' => 20]);
    $supplier = $this->makeSupplier($owner);
    $this->makeProduct($owner);
    $this->makeCustomer($owner);
    Bill::withoutGlobalScopes()->create([
        'total_price' => 15,
        'user_id' => $owner->id,
        'created_by' => $owner->id,
    ]);
    PurchaseBill::withoutGlobalScopes()->create([
        'supplier_id' => $supplier->id,
        'total_amount' => 25,
        'purchase_date' => now()->toDateString(),
        'user_id' => $owner->id,
        'created_by' => $owner->id,
    ]);

    $service = app(ShopStorageService::class);
    $usage = $service->entryUsageForShops([$owner->id]);

    expect($usage[$owner->id])->toBe($owner->fresh()->getEntryUsage());
});

test('legacy compression routes still respond for admins', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();

    Storage::disk('public')->put('products/legacy.jpg', makeJpegImage(1600, 1200));
    $this->makeProduct($owner, ['pictures' => json_encode(['products/legacy.jpg'])]);

    $this->actingAs($admin)
        ->get(route('compress.cleanup.images'))
        ->assertOk()
        ->assertSeeText(__('admin_storage.legacy_page_title'));

    $this->actingAs($admin)
        ->post('/compress-and-cleanup-images', ['step' => 'compress', 'offset' => 0, 'batch' => 10, 'confirm' => true])
        ->assertOk()
        ->assertJsonStructure(['compressed', 'errors', 'hasMore', 'nextOffset', 'progress']);

    $this->actingAs($admin)
        ->get(route('quick.compress.images'))
        ->assertRedirect(route('compress.cleanup.images'));

    $this->actingAs($admin)
        ->post('/quick-compress-images')
        ->assertOk()
        ->assertJsonStructure(['compressed', 'errors']);
});

test('legacy get routes do not mutate files', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();

    Storage::disk('public')->put('products/legacy-safe.jpg', makeJpegImage(900, 700));
    $this->makeProduct($owner, ['pictures' => json_encode(['products/legacy-safe.jpg'])]);

    $before = Storage::disk('public')->size('products/legacy-safe.jpg');

    $this->actingAs($admin)->get('/compress-and-cleanup-images?step=compress&offset=0&batch=10')->assertOk();
    $this->actingAs($admin)->get('/quick-compress-images')->assertRedirect(route('compress.cleanup.images'));

    expect(Storage::disk('public')->size('products/legacy-safe.jpg'))->toBe($before);
});

test('cleanup services handle orphan detection and reference counted deletion', function () {
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();

    Storage::disk('public')->put('products/shared.jpg', makeJpegImage(1200, 900));
    Storage::disk('public')->put('products/orphan.jpg', makeJpegImage(800, 600));

    $this->makeProduct($owner, ['pictures' => json_encode(['products/shared.jpg'])]);
    $this->makeProduct($otherOwner, ['pictures' => json_encode(['products/shared.jpg'])]);

    $cleaner = app(JunkCleaner::class);
    $orphans = $cleaner->orphanImages();

    expect(array_column($orphans, 'path'))->toContain('products/orphan.jpg');

    $result = $cleaner->deleteShopImages($owner->id, ['products/shared.jpg']);

    expect($result['deleted_files'])->toBe([])
        ->and(Storage::disk('public')->exists('products/shared.jpg'))->toBeTrue()
        ->and($owner->fresh()->getEntryUsage())->toBeGreaterThan(0);
});

test('storage image mutations ignore unrelated and invalid paths', function () {
    $admin = $this->makeAdmin();
    $owner = $this->makeOwner();
    $otherOwner = $this->makeOwner();

    Storage::disk('public')->put('products/owner-a.jpg', makeJpegImage(800, 600));
    Storage::disk('public')->put('products/owner-b.jpg', makeJpegImage(800, 600));

    $this->makeProduct($owner, ['pictures' => json_encode(['products/owner-a.jpg'])]);
    $otherProduct = $this->makeProduct($otherOwner, ['pictures' => json_encode(['products/owner-b.jpg'])]);

    $this->actingAs($admin)
        ->post(route('admin.storage.shop.images.delete', $owner), [
            'paths' => ['products/owner-b.jpg', '../secrets.txt', 'products/owner-a.jpg'],
        ])
        ->assertRedirect();

    $otherPictures = json_decode((string) $otherProduct->fresh()->pictures, true);

    expect(Storage::disk('public')->exists('products/owner-b.jpg'))->toBeTrue()
        ->and($otherPictures)->toContain('products/owner-b.jpg')
        ->and(Storage::disk('public')->exists('products/owner-a.jpg'))->toBeFalse();

    $this->actingAs($admin)
        ->postJson(route('admin.storage.compression.run'), [
            'scope' => 'selected',
            'shop_id' => $owner->id,
            'paths' => ['products/owner-b.jpg'],
            'max_width' => 800,
            'max_height' => 800,
            'quality' => 75,
        ])
        ->assertOk()
        ->assertJsonPath('totalPaths', 0);
});

test('temp upload cleanup respects age cutoff and explicit selection and failed jobs retention', function () {
    $directory = storage_path('framework/testing/admin-storage-temp-' . uniqid());
    File::ensureDirectoryExists($directory);
    File::put($directory . DIRECTORY_SEPARATOR . 'old.tmp', 'old');
    File::put($directory . DIRECTORY_SEPARATOR . 'fresh.tmp', 'fresh');
    touch($directory . DIRECTORY_SEPARATOR . 'old.tmp', now()->subDays(2)->timestamp);
    touch($directory . DIRECTORY_SEPARATOR . 'fresh.tmp', now()->subHours(2)->timestamp);

    $cleaner = new JunkCleaner(app(ShopStorageService::class), '', '', '', [$directory]);
    $leftovers = $cleaner->tempUploadLeftovers();

    expect(array_column($leftovers, 'path'))->toContain($directory . DIRECTORY_SEPARATOR . 'old.tmp')
        ->not->toContain($directory . DIRECTORY_SEPARATOR . 'fresh.tmp');

    expect($cleaner->clearTempUploads([])['deleted'])->toBe(0);
    expect($cleaner->clearTempUploads([$directory . DIRECTORY_SEPARATOR . 'old.tmp'])['deleted'])->toBe(1);
    expect(file_exists($directory . DIRECTORY_SEPARATOR . 'fresh.tmp'))->toBeTrue();

    DB::table('failed_jobs')->insert([
        [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Old job',
            'failed_at' => now()->subDays(40),
        ],
        [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Fresh job',
            'failed_at' => now()->subDay(),
        ],
    ]);

    expect($cleaner->clearFailedJobs()['deleted'])->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(1);

    File::deleteDirectory($directory);
});

test('backup manager rejects traversal and requires successful new backups', function () {
    $directory = storage_path('framework/testing/admin-storage-backups-' . uniqid());
    File::ensureDirectoryExists($directory);
    File::put($directory . DIRECTORY_SEPARATOR . 'backup_existing.sql.gz', gzencode('-- Database: test'));

    $manager = new BackupManager($directory);
    expect($manager->resolve('../backup_existing.sql.gz'))->toBeNull()
        ->and($manager->resolve('backup_existing.sql.gz'))->not->toBeNull();

    Artisan::shouldReceive('call')->once()->andReturn(1);

    $result = $manager->createBackup(7);

    expect($result['ok'])->toBeFalse()
        ->and($result['filename'])->toBeNull();

    File::deleteDirectory($directory);
});

test('image optimizer compresses images, preserves transparency, and dry run is non destructive', function () {
    $optimizer = app(ImageOptimizer::class);

    Storage::disk('public')->put('products/photo.jpg', makeJpegImage(2000, 1600, 90));
    Storage::disk('public')->put('products/transparent.png', makeTransparentPngImage(900, 900));

    $beforeJpeg = Storage::disk('public')->size('products/photo.jpg');
    $beforePng = Storage::disk('public')->size('products/transparent.png');

    $dryRun = $optimizer->optimize(['products/photo.jpg'], ['max_width' => 1200, 'max_height' => 1200, 'quality' => 70], true);
    expect(Storage::disk('public')->size('products/photo.jpg'))->toBe($beforeJpeg)
        ->and($dryRun['optimized'])->toBe(1);

    $jpegResult = $optimizer->optimize(['products/photo.jpg'], ['max_width' => 1200, 'max_height' => 1200, 'quality' => 70], false);
    $pngResult = $optimizer->optimize(['products/transparent.png'], ['max_width' => 600, 'max_height' => 600, 'quality' => 78], false);

    expect($jpegResult['optimized'])->toBe(1)
        ->and(Storage::disk('public')->size('products/photo.jpg'))->toBeLessThan($beforeJpeg)
        ->and(getimagesize(Storage::disk('public')->path('products/photo.jpg'))[0])->toBeLessThanOrEqual(1200)
        ->and($pngResult['optimized'])->toBe(1)
        ->and(Storage::disk('public')->size('products/transparent.png'))->toBeLessThanOrEqual($beforePng)
        ->and(getimagesize(Storage::disk('public')->path('products/transparent.png'))['mime'])->toBe('image/png');
});

function makeJpegImage(int $width, int $height, int $quality = 85): string
{
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 50, 120, 200);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);
    for ($x = 0; $x < $width; $x += 20) {
        $lineColor = imagecolorallocate($image, ($x * 3) % 255, 40, 90);
        imageline($image, $x, 0, $width - $x - 1, $height - 1, $lineColor);
    }

    ob_start();
    imagejpeg($image, null, $quality);
    $data = (string) ob_get_clean();
    imagedestroy($image);

    return $data;
}

function makeTransparentPngImage(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
    $color = imagecolorallocatealpha($image, 255, 0, 0, 60);
    imagefilledellipse($image, (int) floor($width / 2), (int) floor($height / 2), (int) floor($width * 0.8), (int) floor($height * 0.8), $color);

    ob_start();
    imagepng($image);
    $data = (string) ob_get_clean();
    imagedestroy($image);

    return $data;
}
