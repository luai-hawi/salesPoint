<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class CleanupOrphanedImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:cleanup {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned product images in storage/app/public/products directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('Starting image cleanup...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be deleted');
        }

        $storagePath = storage_path('app/public/products');

        // Get all image paths from products table
        $imagePathsFromDb = $this->getAllImagePathsFromDatabase();

        $this->info("Found " . count($imagePathsFromDb) . " image paths in database");

        $deletedCount = 0;
        $deletedSize = 0;

        // Check storage/app/public/products directory only
        if (File::exists($storagePath)) {
            $this->info("Checking: storage/app/public/products/");
            $storageDeleted = $this->cleanupDirectory($storagePath, $imagePathsFromDb, $dryRun);
            $deletedCount += $storageDeleted['count'];
            $deletedSize += $storageDeleted['size'];
        } else {
            $this->info("Directory does not exist: {$storagePath}");
        }

        // Summary
        $this->info("--- Summary ---");
        $this->info("Deleted files: {$deletedCount}");
        $this->info("Freed space: " . $this->formatBytes($deletedSize));

        if ($dryRun) {
            $this->warn("Run without --dry-run to actually delete these files");
        }

        return Command::SUCCESS;
    }

    /**
     * Get all image paths from the database
     */
    private function getAllImagePathsFromDatabase(): array
    {
        $paths = [];

        $products = Product::whereNotNull('pictures')->get();

        foreach ($products as $product) {
            $pictures = json_decode($product->pictures, true);
            if (is_array($pictures)) {
                foreach ($pictures as $picture) {
                    if (!empty($picture)) {
                        $paths[] = $picture;
                    }
                }
            }
        }

        return array_unique($paths);
    }

    /**
     * Cleanup storage/app/public/products directory
     */
    private function cleanupDirectory(string $directory, array $validPaths, bool $dryRun): array
    {
        $deletedCount = 0;
        $deletedSize = 0;

        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $relativePath = 'products/' . $file->getRelativePathname();

            if (!in_array($relativePath, $validPaths)) {
                $size = $file->getSize();

                if ($dryRun) {
                    $this->line("  [WOULD DELETE] {$relativePath} (" . $this->formatBytes($size) . ")");
                } else {
                    if (File::delete($file->getPathname())) {
                        $this->line("  [DELETED] {$relativePath}");
                        $deletedCount++;
                        $deletedSize += $size;
                    }
                }
            }
        }

        return ['count' => $deletedCount, 'size' => $deletedSize];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
