<?php

namespace App\Services\Admin;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class JunkCleaner
{
    private const TEMP_UPLOAD_MAX_AGE_HOURS = 24;
    private const FAILED_JOB_RETENTION_DAYS = 30;

    /**
     * @param  list<string>|null  $tempDirectories
     */
    public function __construct(
        private readonly ShopStorageService $shopStorage,
        private readonly string $backupDirectory = '',
        private readonly string $logsDirectory = '',
        private readonly string $compiledViewsDirectory = '',
        private readonly ?array $tempDirectories = null,
    ) {
    }

    /**
     * @return list<array{path: string, bytes: int}>
     */
    public function orphanImages(): array
    {
        $disk = Storage::disk('public');
        $referenced = $this->shopStorage->referenceMap();
        $orphans = [];

        foreach ($disk->allFiles('products') as $path) {
            if (! $this->isImagePath($path)) {
                continue;
            }

            if (! isset($referenced[$path])) {
                $orphans[] = [
                    'path' => $path,
                    'bytes' => (int) $disk->size($path),
                ];
            }
        }

        usort($orphans, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return $orphans;
    }

    /**
     * @return list<array{path: string, product_id: int, product_name: string, owner_id: int}>
     */
    public function missingReferences(?int $ownerId = null): array
    {
        $disk = Storage::disk('public');
        $references = $this->shopStorage->referenceMap($ownerId ? [$ownerId] : null);
        $missing = [];

        foreach ($references as $path => $reference) {
            if ($disk->exists($path)) {
                continue;
            }

            foreach ($reference['products'] as $product) {
                $missing[] = [
                    'path' => $path,
                    'product_id' => (int) $product['id'],
                    'product_name' => (string) $product['name'],
                    'owner_id' => (int) $product['user_id'],
                ];
            }
        }

        usort($missing, fn ($a, $b) => [$a['path'], $a['product_id']] <=> [$b['path'], $b['product_id']]);

        return $missing;
    }

    /**
     * @return list<array{path: string}>
     */
    public function emptyFolders(): array
    {
        $disk = Storage::disk('public');
        $directories = $disk->allDirectories('products');
        rsort($directories);
        $empty = [];

        foreach ($directories as $directory) {
            if ($disk->files($directory) === [] && $disk->directories($directory) === []) {
                $empty[] = ['path' => $directory];
            }
        }

        return $empty;
    }

    /**
     * @return list<array{path: string, bytes: int, modified_at: int}>
     */
    public function tempUploadLeftovers(): array
    {
        $leftovers = [];
        $cutoff = now()->subHours(self::TEMP_UPLOAD_MAX_AGE_HOURS)->timestamp;
        $directories = $this->tempDirectories ?? [
            storage_path('app/livewire-tmp'),
            storage_path('app/tmp'),
            storage_path('framework/cache/data/tmp-uploads'),
        ];

        foreach ($directories as $directory) {
            if (! $directory || ! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ((int) $file->getMTime() >= $cutoff) {
                    continue;
                }

                $leftovers[] = [
                    'path' => $file->getPathname(),
                    'bytes' => (int) $file->getSize(),
                    'modified_at' => (int) $file->getMTime(),
                ];
            }
        }

        return $leftovers;
    }

    /**
     * @param  list<string>  $paths
     * @return array{deleted: int, deleted_bytes: int, deleted_paths: list<string>}
     */
    public function deleteOrphanImages(array $paths): array
    {
        $disk = Storage::disk('public');
        $orphans = collect($this->orphanImages())->keyBy('path');
        $deleted = 0;
        $deletedBytes = 0;
        $deletedPaths = [];

        foreach (array_values(array_unique($paths)) as $path) {
            $entry = $orphans->get($path);
            if (! $entry || ! $this->isSafeManagedExistingPath($disk, $path)) {
                continue;
            }

            $deletedBytes += (int) ($entry['bytes'] ?? 0);
            $disk->delete($path);
            $deleted++;
            $deletedPaths[] = $path;
        }

        $this->deleteEmptyFolders(collect($deletedPaths)->map(fn ($path) => dirname($path))->unique()->all());

        return [
            'deleted' => $deleted,
            'deleted_bytes' => $deletedBytes,
            'deleted_paths' => $deletedPaths,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{products_updated: int, paths_cleaned: list<string>}
     */
    public function cleanMissingReferences(array $paths = [], ?int $ownerId = null): array
    {
        $wantedPaths = ShopStorageService::filterManagedProductPaths($paths);
        if ($wantedPaths === []) {
            return [
                'products_updated' => 0,
                'paths_cleaned' => [],
            ];
        }

        $wanted = array_fill_keys($wantedPaths, true);
        $updated = 0;
        $cleanedPaths = [];

        Product::withoutGlobalScopes()
            ->when($ownerId, fn ($query) => $query->where('user_id', $ownerId))
            ->whereNotNull('pictures')
            ->select('id', 'pictures')
            ->chunkById(200, function ($products) use (&$updated, &$cleanedPaths, $wanted): void {
                foreach ($products as $product) {
                    $pictures = ShopStorageService::decodePictures($product->pictures);
                    if ($pictures === []) {
                        continue;
                    }

                    $filtered = [];
                    $changed = false;

                    foreach ($pictures as $path) {
                        $shouldClean = $wanted === [] || isset($wanted[$path]);
                        $exists = Storage::disk('public')->exists($path);

                        if ($shouldClean && ! $exists) {
                            $changed = true;
                            $cleanedPaths[$path] = $path;
                            continue;
                        }

                        $filtered[] = $path;
                    }

                    if (! $changed) {
                        continue;
                    }

                    $product->pictures = $filtered === [] ? null : json_encode(array_values($filtered));
                    $product->save();
                    $updated++;
                }
            }, 'id');

        return [
            'products_updated' => $updated,
            'paths_cleaned' => array_values($cleanedPaths),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array{
     *   affected_products: int,
     *   removed_references: int,
     *   deleted_files: list<string>,
     *   kept_files: list<string>
     * }
     */
    public function deleteShopImages(int $ownerId, array $paths): array
    {
        $paths = ShopStorageService::filterManagedProductPaths($paths);
        if ($paths === []) {
            return [
                'affected_products' => 0,
                'removed_references' => 0,
                'deleted_files' => [],
                'kept_files' => [],
            ];
        }

        $removeLookup = array_fill_keys($paths, true);
        $affectedProducts = 0;
        $removedReferences = 0;

        Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNotNull('pictures')
            ->select('id', 'pictures')
            ->chunkById(200, function ($products) use ($removeLookup, &$affectedProducts, &$removedReferences): void {
                foreach ($products as $product) {
                    $pictures = ShopStorageService::decodePictures($product->pictures);
                    if ($pictures === []) {
                        continue;
                    }

                    $filtered = [];
                    $changed = false;

                    foreach ($pictures as $path) {
                        if (isset($removeLookup[$path])) {
                            $removedReferences++;
                            $changed = true;
                            continue;
                        }

                        $filtered[] = $path;
                    }

                    if (! $changed) {
                        continue;
                    }

                    $product->pictures = $filtered === [] ? null : json_encode(array_values($filtered));
                    $product->save();
                    $affectedProducts++;
                }
            }, 'id');

        $globalCounts = $this->globalReferenceCounts($paths);
        $disk = Storage::disk('public');
        $deletedFiles = [];
        $keptFiles = [];

        foreach ($paths as $path) {
            if (($globalCounts[$path] ?? 0) === 0) {
                if ($this->isSafeManagedExistingPath($disk, $path)) {
                    $disk->delete($path);
                    $deletedFiles[] = $path;
                }
            } else {
                $keptFiles[] = $path;
            }
        }

        $this->deleteEmptyFolders(collect($deletedFiles)->map(fn ($path) => dirname($path))->unique()->all());

        return [
            'affected_products' => $affectedProducts,
            'removed_references' => $removedReferences,
            'deleted_files' => $deletedFiles,
            'kept_files' => $keptFiles,
        ];
    }

    public function deleteAllImagesForShop(int $ownerId): array
    {
        return $this->deleteShopImages($ownerId, array_keys($this->shopStorage->images($ownerId)));
    }

    /**
     * @param  list<string>  $directories
     * @return array{deleted: int, deleted_paths: list<string>}
     */
    public function deleteEmptyFolders(array $directories): array
    {
        $disk = Storage::disk('public');
        $deleted = 0;
        $deletedPaths = [];
        rsort($directories);

        foreach (array_values(array_unique($directories)) as $directory) {
            if ($directory === '.' || $directory === 'products' || ! $disk->directoryExists($directory)) {
                continue;
            }

            if ($disk->files($directory) === [] && $disk->directories($directory) === []) {
                $disk->deleteDirectory($directory);
                $deleted++;
                $deletedPaths[] = $directory;
            }
        }

        return ['deleted' => $deleted, 'deleted_paths' => $deletedPaths];
    }

    /**
     * @return array{deleted: int}
     */
    public function clearExpiredSessions(): array
    {
        if (! Schema::hasTable('sessions')) {
            return ['deleted' => 0];
        }

        $threshold = now()->subMinutes((int) config('session.lifetime', 120))->timestamp;

        return [
            'deleted' => DB::table('sessions')->where('last_activity', '<', $threshold)->delete(),
        ];
    }

    /**
     * @return array{deleted: int}
     */
    public function clearFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['deleted' => 0];
        }

        $query = DB::table('failed_jobs');
        if (Schema::hasColumn('failed_jobs', 'failed_at')) {
            $query->where('failed_at', '<', now()->subDays(self::FAILED_JOB_RETENTION_DAYS));
        }

        $deleted = (int) $query->count();
        $query->delete();

        return ['deleted' => $deleted];
    }

    /**
     * @return array{deleted: int}
     */
    public function clearCacheTable(): array
    {
        if (! Schema::hasTable('cache')) {
            return ['deleted' => 0];
        }

        $deleted = (int) DB::table('cache')->count();
        DB::table('cache')->delete();

        return ['deleted' => $deleted];
    }

    /**
     * @return array{deleted: int}
     */
    public function clearCompiledViews(): array
    {
        $directory = $this->compiledViewsDirectory !== '' ? $this->compiledViewsDirectory : storage_path('framework/views');
        if (! is_dir($directory)) {
            return ['deleted' => 0];
        }

        $deleted = 0;
        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'php' && @unlink($file->getPathname())) {
                $deleted++;
            }
        }

        return ['deleted' => $deleted];
    }

    /**
     * @return list<array{filename: string, path: string, bytes: int}>
     */
    public function logFiles(): array
    {
        $directory = $this->logsDirectory !== '' ? $this->logsDirectory : storage_path('logs');
        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => $file->isFile())
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'filename' => $file->getFilename(),
                'path' => $file->getPathname(),
                'bytes' => (int) $file->getSize(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $filenames
     * @return array{cleared: int}
     */
    public function truncateLogs(array $filenames): array
    {
        $directory = $this->logsDirectory !== '' ? $this->logsDirectory : storage_path('logs');
        $allowed = collect($this->logFiles())->keyBy('filename');
        $cleared = 0;

        foreach (array_values(array_unique($filenames)) as $filename) {
            $file = $allowed->get($filename);
            if (! $file) {
                continue;
            }

            if (@file_put_contents($file['path'], '') !== false) {
                $cleared++;
            }
        }

        return ['cleared' => $cleared];
    }

    /**
     * @param  list<string>  $paths
     * @return array{deleted: int}
     */
    public function clearTempUploads(array $paths): array
    {
        if ($paths === []) {
            return ['deleted' => 0];
        }

        $allowed = collect($this->tempUploadLeftovers())
            ->keyBy('path');
        $selected = array_fill_keys(array_values(array_unique($paths)), true);
        $deleted = 0;

        foreach ($allowed as $path => $item) {
            if (! isset($selected[$path])) {
                continue;
            }

            if (@unlink($item['path'])) {
                $deleted++;
            }
        }

        return ['deleted' => $deleted];
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, int>
     */
    private function globalReferenceCounts(array $paths): array
    {
        $counts = array_fill_keys($paths, 0);

        if ($paths === []) {
            return $counts;
        }

        Product::withoutGlobalScopes()
            ->whereNotNull('pictures')
            ->select('pictures')
            ->orderBy('id')
            ->chunk(500, function ($products) use (&$counts): void {
                foreach ($products as $product) {
                    foreach (ShopStorageService::decodePictures($product->pictures) as $path) {
                        if (array_key_exists($path, $counts)) {
                            $counts[$path]++;
                        }
                    }
                }
            });

        return $counts;
    }

    private function isImagePath(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    private function isSafeManagedExistingPath($disk, string $path): bool
    {
        if (! $disk->exists($path)) {
            return false;
        }

        $productsRoot = realpath($disk->path('products'));
        $absolute = realpath($disk->path($path));

        return $productsRoot !== false
            && $absolute !== false
            && str_starts_with($absolute, $productsRoot . DIRECTORY_SEPARATOR);
    }
}
