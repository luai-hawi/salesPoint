<?php

namespace App\Services\Admin;

use App\Models\ImageOptimization;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image usage per shop. Product images live on the "public" disk (storage/app/public/products/*)
 * and are referenced by products.pictures (a JSON array of disk-relative paths). Variants of one product
 * share the same file, so every figure here counts UNIQUE files.
 */
class ShopStorageService
{
    private const CACHE_TTL = 600;

    /**
     * Image stats for many shops with a single products query.
     *
     * @param  list<int>  $ownerIds
     * @return array<int, array{count: int, bytes: int, missing: int}>
     */
    public function imageStatsForShops(array $ownerIds, bool $fresh = false): array
    {
        $stats = [];
        $toCompute = [];

        foreach ($ownerIds as $ownerId) {
            $cached = $fresh ? null : Cache::get($this->cacheKey((int) $ownerId));
            if (is_array($cached)) {
                $stats[(int) $ownerId] = $cached;
            } else {
                $toCompute[] = (int) $ownerId;
            }
        }

        if ($toCompute) {
            $paths = $this->collectOwnerPaths($toCompute);
            $disk = Storage::disk('public');

            foreach ($toCompute as $ownerId) {
                $count = 0;
                $bytes = 0;
                $missing = 0;

                foreach (array_keys($paths[$ownerId]) as $path) {
                    $count++;
                    try {
                        if ($disk->exists($path)) {
                            $bytes += (int) $disk->size($path);
                        } else {
                            $missing++;
                        }
                    } catch (\Throwable $e) {
                        $missing++;
                    }
                }

                $stats[$ownerId] = ['count' => $count, 'bytes' => $bytes, 'missing' => $missing];
                Cache::put($this->cacheKey($ownerId), $stats[$ownerId], self::CACHE_TTL);
            }
        }

        return $stats;
    }

    /**
     * @return array{count: int, bytes: int, missing: int}
     */
    public function imageStats(int $ownerId, bool $fresh = false): array
    {
        return $this->imageStatsForShops([$ownerId], $fresh)[$ownerId];
    }

    /**
     * Unique image files of one shop with their size and the products using them.
     *
     * @return array<string, array{
     *   path: string,
     *   bytes: int,
     *   exists: bool,
     *   product_ids: list<int>,
     *   products: list<array{id: int, name: string}>,
     *   width: int|null,
     *   height: int|null,
     *   mime: string|null,
     *   url: string,
     *   savings_bytes: int,
     *   optimized_at: CarbonInterface|null
     * }>
     */
    public function images(int $ownerId): array
    {
        $images = [];
        $disk = Storage::disk('public');

        Product::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->whereNotNull('pictures')
            ->where('pictures', '!=', '')
            ->select('id', 'name', 'pictures')
            ->orderBy('id')
            ->chunk(500, function ($products) use (&$images) {
                foreach ($products as $product) {
                    foreach (self::decodePictures($product->pictures) as $path) {
                        $images[$path]['product_ids'][] = $product->id;
                        $images[$path]['products'][$product->id] = [
                            'id' => (int) $product->id,
                            'name' => (string) $product->name,
                        ];
                    }
                }
            });

        $optimizationMeta = $this->optimizationMeta(array_keys($images));

        foreach ($images as $path => &$image) {
            $image['path'] = $path;
            $image['products'] = array_values($image['products'] ?? []);
            $image['url'] = $disk->url($path);
            $image['width'] = null;
            $image['height'] = null;
            $image['mime'] = null;
            $image['savings_bytes'] = (int) ($optimizationMeta[$path]['savings_bytes'] ?? 0);
            $image['optimized_at'] = $optimizationMeta[$path]['optimized_at'] ?? null;

            try {
                $image['exists'] = $disk->exists($path);
                $image['bytes'] = $image['exists'] ? (int) $disk->size($path) : 0;

                if ($image['exists']) {
                    $dimensions = @getimagesize($disk->path($path));
                    if (is_array($dimensions)) {
                        $image['width'] = isset($dimensions[0]) ? (int) $dimensions[0] : null;
                        $image['height'] = isset($dimensions[1]) ? (int) $dimensions[1] : null;
                        $image['mime'] = isset($dimensions['mime']) && is_string($dimensions['mime']) ? $dimensions['mime'] : null;
                    }
                }
            } catch (\Throwable $e) {
                $image['exists'] = false;
                $image['bytes'] = 0;
            }
        }

        return $images;
    }

    /**
     * @param  list<int>  $ownerIds
     * @return array<int, int>
     */
    public function entryUsageForShops(array $ownerIds): array
    {
        $usage = array_fill_keys($ownerIds, 0);

        if ($ownerIds === []) {
            return $usage;
        }

        foreach ([
            'bills',
            'products',
            'customers',
            'purchase_bills',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->selectRaw('user_id, COUNT(*) as aggregate_count')
                ->whereIn('user_id', $ownerIds)
                ->groupBy('user_id')
                ->get()
                ->each(function ($row) use (&$usage): void {
                    $userId = (int) $row->user_id;
                    $usage[$userId] = ($usage[$userId] ?? 0) + (int) $row->aggregate_count;
                });
        }

        return $usage;
    }

    /**
     * Count image slots the same way ProductsController::countTotalImages() does.
     *
     * @param  list<int>  $ownerIds
     * @return array<int, int>
     */
    public function imageUsageForShops(array $ownerIds): array
    {
        $usage = array_fill_keys($ownerIds, 0);

        if ($ownerIds === []) {
            return $usage;
        }

        DB::table('products')
            ->whereIn('user_id', $ownerIds)
            ->whereNotNull('pictures')
            ->where('pictures', '!=', '')
            ->select('user_id', 'pictures')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$usage): void {
                foreach ($rows as $row) {
                    $usage[(int) $row->user_id] += count(self::decodePictures($row->pictures));
                }
            });

        return $usage;
    }

    /**
     * @param  list<int>  $ownerIds
     * @return array<int, array{
     *   count: int,
     *   bytes: int,
     *   missing: int,
     *   average_bytes: int,
     *   potential_savings: int,
     *   last_compressed: CarbonInterface|null
     * }>
     */
    public function summaryForShops(array $ownerIds, bool $fresh = false): array
    {
        $stats = $this->imageStatsForShops($ownerIds, $fresh);
        $paths = $this->collectOwnerPaths($ownerIds);
        $allPaths = [];
        foreach ($paths as $ownerPaths) {
            foreach (array_keys($ownerPaths) as $path) {
                $allPaths[$path] = $path;
            }
        }
        $optimizations = $this->optimizationMeta(array_values($allPaths));
        $summary = [];

        foreach ($ownerIds as $ownerId) {
            $potentialSavings = 0;
            $lastCompressed = null;

            foreach (array_keys($paths[$ownerId] ?? []) as $path) {
                $meta = $optimizations[$path] ?? null;
                if (! $meta) {
                    continue;
                }

                $potentialSavings += (int) $meta['savings_bytes'];

                if ($meta['optimized_at'] && ($lastCompressed === null || $meta['optimized_at']->greaterThan($lastCompressed))) {
                    $lastCompressed = $meta['optimized_at'];
                }
            }

            $count = (int) ($stats[$ownerId]['count'] ?? 0);
            $bytes = (int) ($stats[$ownerId]['bytes'] ?? 0);

            $summary[$ownerId] = [
                'count' => $count,
                'bytes' => $bytes,
                'missing' => (int) ($stats[$ownerId]['missing'] ?? 0),
                'average_bytes' => $count > 0 ? (int) floor($bytes / $count) : 0,
                'potential_savings' => $potentialSavings,
                'last_compressed' => $lastCompressed,
            ];
        }

        return $summary;
    }

    /**
     * @param  list<int>|null  $ownerIds
     * @return array<string, array{
     *   path: string,
     *   reference_count: int,
     *   owner_ids: list<int>,
     *   product_ids: list<int>,
     *   products: list<array{id: int, user_id: int, name: string}>
     * }>
     */
    public function referenceMap(?array $ownerIds = null): array
    {
        $references = [];

        $query = Product::withoutGlobalScopes()
            ->whereNotNull('pictures')
            ->where('pictures', '!=', '')
            ->select('id', 'user_id', 'name', 'pictures')
            ->orderBy('id');

        if ($ownerIds !== null && $ownerIds !== []) {
            $query->whereIn('user_id', $ownerIds);
        }

        $query->chunk(500, function ($products) use (&$references): void {
            foreach ($products as $product) {
                foreach (self::decodePictures($product->pictures) as $path) {
                    $entry = &$references[$path];
                    $entry['path'] = $path;
                    $entry['reference_count'] = ($entry['reference_count'] ?? 0) + 1;
                    $entry['owner_ids'][$product->user_id] = (int) $product->user_id;
                    $entry['product_ids'][$product->id] = (int) $product->id;
                    $entry['products'][$product->id] = [
                        'id' => (int) $product->id,
                        'user_id' => (int) $product->user_id,
                        'name' => (string) $product->name,
                    ];
                }
            }
        });

        foreach ($references as &$reference) {
            $reference['owner_ids'] = array_values($reference['owner_ids'] ?? []);
            $reference['product_ids'] = array_values($reference['product_ids'] ?? []);
            $reference['products'] = array_values($reference['products'] ?? []);
        }

        return $references;
    }

    /**
     * @param  list<int>  $ownerIds
     * @return array<int, User>
     */
    public function ownerMap(array $ownerIds): array
    {
        return User::query()
            ->whereIn('id', $ownerIds)
            ->get(['id', 'name', 'owner_name', 'role', 'entry_limit', 'image_limit'])
            ->keyBy('id')
            ->all();
    }

    public function forget(int $ownerId): void
    {
        Cache::forget($this->cacheKey($ownerId));
    }

    /**
     * @return list<string>
     */
    public static function decodePictures(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [];
        }

        return self::filterManagedProductPaths($decoded);
    }

    /**
     * @param  list<mixed>  $paths
     * @return list<string>
     */
    public static function filterManagedProductPaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $clean = self::normalizeManagedProductPath($path);
            if ($clean !== null) {
                $normalized[$clean] = $clean;
            }
        }

        return array_values($normalized);
    }

    public static function normalizeManagedProductPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path) ?? '';
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0") || ! Str::startsWith($path, 'products/')) {
            return null;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return implode('/', $segments);
    }

    public static function humanBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(0, (float) $bytes);
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : number_format($bytes, 1)) . ' ' . $units[$i];
    }

    private function cacheKey(int $ownerId): string
    {
        return 'shop_image_stats_' . $ownerId;
    }

    /**
     * @param  list<int>  $ownerIds
     * @return array<int, array<string, true>>
     */
    private function collectOwnerPaths(array $ownerIds): array
    {
        $paths = array_fill_keys($ownerIds, []);

        if ($ownerIds === []) {
            return $paths;
        }

        DB::table('products')
            ->whereIn('user_id', $ownerIds)
            ->whereNotNull('pictures')
            ->where('pictures', '!=', '')
            ->select('user_id', 'pictures')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$paths): void {
                foreach ($rows as $row) {
                    foreach (self::decodePictures($row->pictures) as $path) {
                        $paths[(int) $row->user_id][$path] = true;
                    }
                }
            });

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, array{savings_bytes: int, optimized_at: CarbonInterface|null}>
     */
    private function optimizationMeta(array $paths): array
    {
        if ($paths === [] || ! Schema::hasTable('image_optimizations')) {
            return [];
        }

        return ImageOptimization::query()
            ->whereIn('path', $paths)
            ->get(['path', 'original_bytes', 'optimized_bytes', 'optimized_at'])
            ->mapWithKeys(function (ImageOptimization $optimization): array {
                return [
                    $optimization->path => [
                        'savings_bytes' => max(0, (int) $optimization->original_bytes - (int) $optimization->optimized_bytes),
                        'optimized_at' => $optimization->optimized_at,
                    ],
                ];
            })
            ->all();
    }
}
