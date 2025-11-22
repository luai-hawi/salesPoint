<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DeactivateOldProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:deactivate-old {--dry-run : Show what would be deactivated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically deactivate products that haven\'t been sold for the configured period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No products will be actually deactivated');
            $this->newLine();
        }

        $this->info('🚀 Starting automatic product deactivation process...');
        $this->newLine();

        // Get all users with their deactivation settings
        $users = User::whereNotNull('product_deactivation_period')->get();

        if ($users->isEmpty()) {
            $this->warn('⚠️  No users found with deactivation settings configured.');
            return;
        }

        $totalDeactivated = 0;
        $totalImagesDeleted = 0;

        foreach ($users as $user) {
            $this->info("👤 Processing user: {$user->name} (ID: {$user->id})");

            $deactivationMonths = $user->product_deactivation_period ?? 6;
            $deactivationCutoff = now()->subMonths($deactivationMonths);

            $this->info("   📅 Deactivation period: {$deactivationMonths} months (cutoff: {$deactivationCutoff->format('Y-m-d')})");

            // Find products to deactivate
            $productsToDeactivate = Product::where('user_id', $user->id)
                ->where('quantity', 0)
                ->where('is_active', true)
                ->whereNotNull('last_sale_date')
                ->where('last_sale_date', '<=', $deactivationCutoff)
                ->where(function ($query) {
                    // Don't deactivate products that have been extended
                    $query->whereNull('extended_until')
                          ->orWhere('extended_until', '<=', now());
                })
                ->get();

            if ($productsToDeactivate->isEmpty()) {
                $this->info("   ✅ No products to deactivate for this user");
                $this->newLine();
                continue;
            }

            $this->info("   📦 Found {$productsToDeactivate->count()} products to deactivate:");
            $this->table(
                ['ID', 'Name', 'Last Sale', 'Months Since Sale'],
                $productsToDeactivate->map(function ($product) {
                    $lastSaleDate = $product->last_sale_date ? \Carbon\Carbon::parse($product->last_sale_date) : null;
                    return [
                        $product->id,
                        $product->name,
                        $lastSaleDate ? $lastSaleDate->format('Y-m-d') : 'Never',
                        $lastSaleDate ? now()->diffInMonths($lastSaleDate) : 'N/A'
                    ];
                })
            );

            if (!$isDryRun) {
                // Actually deactivate the products
                foreach ($productsToDeactivate as $product) {
                    // Delete product images to free storage
                    $imagesDeleted = $this->deleteProductImages($product);

                    // Deactivate the product
                    $product->is_active = false;
                    $product->pictures = null; // Clear picture references
                    $product->save();

                    $totalDeactivated++;
                    $totalImagesDeleted += $imagesDeleted;

                    Log::info("Product automatically deactivated: {$product->name} (ID: {$product->id}) for user {$user->name}");
                }

                $this->info("   ✅ Deactivated {$productsToDeactivate->count()} products");
            } else {
                $this->info("   🔍 Would deactivate {$productsToDeactivate->count()} products (dry run)");
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('📊 SUMMARY:');
        if ($isDryRun) {
            $this->info("   🔍 Dry run completed - {$totalDeactivated} products would be deactivated");
        } else {
            $this->info("   ✅ Process completed - {$totalDeactivated} products deactivated");
            $this->info("   🗑️  {$totalImagesDeleted} images deleted to free storage");
        }

        Log::info("Automatic product deactivation completed: {$totalDeactivated} products deactivated, {$totalImagesDeleted} images deleted");

        return Command::SUCCESS;
    }

    /**
     * Delete all images associated with a product
     */
    private function deleteProductImages(Product $product): int
    {
        $imagesDeleted = 0;

        if ($product->pictures) {
            $pictures = json_decode($product->pictures, true);

            if (is_array($pictures)) {
                foreach ($pictures as $picture) {
                    // Delete from public disk
                    if (Storage::disk('public')->exists($picture)) {
                        Storage::disk('public')->delete($picture);
                        $imagesDeleted++;
                    }
                }
            }
        }

        return $imagesDeleted;
    }
}
