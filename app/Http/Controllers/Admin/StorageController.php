<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Admin\BackupManager;
use App\Services\Admin\ImageOptimizer;
use App\Services\Admin\JunkCleaner;
use App\Services\Admin\ShopStorageService;
use App\Services\Admin\StorageOverviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageController extends Controller
{
    public function __construct(
        private readonly ShopStorageService $shopStorage,
        private readonly ImageOptimizer $imageOptimizer,
        private readonly JunkCleaner $junkCleaner,
        private readonly BackupManager $backupManager,
        private readonly StorageOverviewService $overviewService,
    ) {
    }

    public function index(Request $request)
    {
        [$shops, $filters] = $this->shopTable($request);

        return view('admin.storage.index', [
            'shops' => $shops,
            'filters' => $filters,
            'sortOptions' => [
                'shop' => __('admin_storage.sort.shop'),
                'status' => __('admin_storage.sort.status'),
                'entries_used' => __('admin_storage.sort.entries_used'),
                'images_unique' => __('admin_storage.sort.images_unique'),
                'images_bytes' => __('admin_storage.sort.images_bytes'),
                'potential_savings' => __('admin_storage.sort.potential_savings'),
            ],
            'backups' => $this->backupManager->listBackups((int) config('admin-storage.keep_backups', 7)),
            'logs' => $this->junkCleaner->logFiles(),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh');

        if ($fresh) {
            $this->overviewService->forget();
        }

        $overview = $this->overviewService->overview($fresh);

        return response()->json([
            'overview' => $overview,
        ]);
    }

    public function shop(Request $request, User $shop)
    {
        $shop = $this->ownerAccount($shop);
        $allImages = collect($this->shopStorage->images($shop->id));
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $allImages = $allImages->filter(function (array $image) use ($search): bool {
                if (Str::contains(Str::lower($image['path']), Str::lower($search))) {
                    return true;
                }

                foreach ($image['products'] as $product) {
                    if (Str::contains(Str::lower($product['name']), Str::lower($search))) {
                        return true;
                    }
                }

                return false;
            });
        }

        $imageRows = $allImages->values()->all();
        usort($imageRows, function (array $left, array $right): int {
            if ($left['exists'] !== $right['exists']) {
                return $left['exists'] ? -1 : 1;
            }

            return strcmp($left['path'], $right['path']);
        });

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(12, min(48, (int) $request->query('per_page', 24)));
        $items = array_slice($imageRows, ($page - 1) * $perPage, $perPage);
        $images = new Paginator($items, count($imageRows), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $products = collect($imageRows)
            ->flatMap(fn ($image) => $image['products'])
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('admin.storage.shop', [
            'shop' => $shop,
            'summary' => $this->shopStorage->summaryForShops([$shop->id], $request->boolean('fresh'))[$shop->id] ?? null,
            'images' => $images,
            'products' => $products,
            'missingReferences' => $this->junkCleaner->missingReferences($shop->id),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function recalculateShop(User $shop): JsonResponse
    {
        $shop = $this->ownerAccount($shop);
        $this->shopStorage->forget($shop->id);

        return response()->json([
            'message' => __('admin_storage.messages.recalculated'),
            'stats' => $this->shopStorage->summaryForShops([$shop->id], true)[$shop->id] ?? [],
        ]);
    }

    public function previewCompression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => 'required|in:all,shop,selected',
            'shop_id' => 'nullable|integer|exists:users,id',
            'paths' => 'array',
            'paths.*' => 'string',
            'max_width' => 'nullable|integer|min:1|max:5000',
            'max_height' => 'nullable|integer|min:1|max:5000',
            'quality' => 'nullable|integer|min:30|max:95',
        ]);

        $paths = $this->resolveCompressionPaths($validated);
        $estimate = $this->imageOptimizer->estimate($paths, $validated);

        return response()->json([
            'message' => __('admin_storage.messages.preview_ready'),
            'total_paths' => count($paths),
            'estimate' => $estimate,
        ]);
    }

    public function runCompression(Request $request): JsonResponse
    {
        @set_time_limit(120);

        $validated = $request->validate([
            'scope' => 'required|in:all,shop,selected',
            'shop_id' => 'nullable|integer|exists:users,id',
            'paths' => 'array',
            'paths.*' => 'string',
            'max_width' => 'nullable|integer|min:1|max:5000',
            'max_height' => 'nullable|integer|min:1|max:5000',
            'quality' => 'nullable|integer|min:30|max:95',
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
            'dry_run' => 'nullable|boolean',
        ]);

        $paths = $this->resolveCompressionPaths($validated);
        $offset = (int) ($validated['offset'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 25);
        $batch = array_slice($paths, $offset, $limit);
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $results = $this->imageOptimizer->optimize($batch, $validated, $dryRun);

        if (! $dryRun) {
            $affectedShopIds = $validated['scope'] === 'shop' && ! empty($validated['shop_id'])
                ? [(int) $validated['shop_id']]
                : array_values(array_unique(collect($this->shopStorage->referenceMap())->filter(
                    fn ($reference, $path) => in_array($path, $batch, true)
                )->flatMap(fn ($reference) => $reference['owner_ids'])->all()));

            foreach ($affectedShopIds as $ownerId) {
                $this->shopStorage->forget($ownerId);
            }

            ActivityLogger::record(
                'admin.storage.compress',
                'storage',
                null,
                [
                    'scope' => $validated['scope'],
                    'shop_id' => $validated['shop_id'] ?? null,
                    'paths' => $batch,
                    'saved_bytes' => $results['saved_bytes'],
                    'dry_run' => false,
                ],
                null,
                __('admin_storage.activity.image_compression')
            );
        }

        return response()->json([
            'message' => $dryRun ? __('admin_storage.messages.preview_ready') : __('admin_storage.messages.compression_complete'),
            'results' => $results,
            'hasMore' => ($offset + $limit) < count($paths),
            'nextOffset' => $offset + $limit,
            'progress' => count($paths) === 0 ? 100 : min(100, (int) floor((($offset + count($batch)) / count($paths)) * 100)),
            'totalPaths' => count($paths),
        ]);
    }

    public function deleteImages(Request $request, User $shop): RedirectResponse|JsonResponse
    {
        $shop = $this->ownerAccount($shop);
        $validated = $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
        ]);

        $result = $this->junkCleaner->deleteShopImages(
            $shop->id,
            $this->intersectManagedPaths($validated['paths'], array_keys($this->shopStorage->images($shop->id)))
        );
        $this->shopStorage->forget($shop->id);

        ActivityLogger::record(
            'admin.storage.delete-images',
            'user',
            $shop,
            $result,
            null,
            __('admin_storage.activity.delete_images'),
            $shop->id
        );

        return $this->wantsJson($request)
            ? response()->json(['message' => __('admin_storage.messages.images_deleted'), 'result' => $result])
            : back()->with('success', __('admin_storage.messages.images_deleted'));
    }

    public function cleanMissingReferences(Request $request, User $shop): RedirectResponse|JsonResponse
    {
        $shop = $this->ownerAccount($shop);
        $validated = $request->validate([
            'paths' => 'array',
            'paths.*' => 'string',
        ]);

        $allowed = array_column($this->junkCleaner->missingReferences($shop->id), 'path');
        $result = $this->junkCleaner->cleanMissingReferences(
            $this->intersectManagedPaths($validated['paths'] ?? [], $allowed),
            $shop->id
        );
        $this->shopStorage->forget($shop->id);

        ActivityLogger::record(
            'admin.storage.clean-missing-references',
            'user',
            $shop,
            $result,
            null,
            __('admin_storage.activity.clean_missing_references'),
            $shop->id
        );

        return $this->wantsJson($request)
            ? response()->json(['message' => __('admin_storage.messages.references_cleaned'), 'result' => $result])
            : back()->with('success', __('admin_storage.messages.references_cleaned'));
    }

    public function downloadImages(Request $request, User $shop)
    {
        $shop = $this->ownerAccount($shop);
        $validated = $request->validate([
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
        ]);

        $available = collect($this->shopStorage->images($shop->id))->keyBy('path');
        $paths = collect($this->intersectManagedPaths($validated['paths'], $available->keys()->all()))
            ->filter(fn ($path) => ($available[$path]['exists'] ?? false) && $this->isSafeManagedDownloadPath($path))
            ->values()
            ->all();

        abort_if($paths === [], 404);

        $disk = Storage::disk('public');
        if (count($paths) === 1) {
            $path = $paths[0];

            return response()->download($disk->path($path), basename($path));
        }

        $zipDirectory = storage_path('app/admin-storage-downloads');
        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0755, true);
        }

        $zipPath = $zipDirectory . DIRECTORY_SEPARATOR . 'shop-' . $shop->id . '-' . now()->format('YmdHis') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($paths as $path) {
            $zip->addFile($disk->path($path), basename($path));
        }

        $zip->close();

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    public function previewCleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:orphan-images,missing-references,empty-folders,old-backups,logs,expired-sessions,failed-jobs,cache,compiled-views,temp-uploads,delete-shop-images',
            'shop_id' => 'nullable|integer|exists:users,id',
        ]);

        return response()->json([
            'items' => $this->cleanupPreviewItems($validated['action'], $validated['shop_id'] ?? null),
            'label' => $this->cleanupActionLabel($validated['action']),
        ]);
    }

    public function runCleanup(Request $request): JsonResponse|RedirectResponse
    {
        @set_time_limit(120);

        $validated = $request->validate([
            'action' => 'required|string|in:orphan-images,missing-references,empty-folders,old-backups,logs,expired-sessions,failed-jobs,cache,compiled-views,temp-uploads,delete-shop-images',
            'shop_id' => 'nullable|integer|exists:users,id',
            'paths' => 'array',
            'paths.*' => 'string',
            'filenames' => 'array',
            'filenames.*' => 'string',
            'confirmation' => 'nullable|string',
        ]);

        if ($validated['action'] === 'delete-shop-images') {
            $shop = $this->ownerAccount(User::query()->findOrFail((int) $validated['shop_id']));
            abort_unless(($validated['confirmation'] ?? '') === $shop->email, 422, __('admin_storage.messages.confirmation_mismatch'));
        }

        $result = match ($validated['action']) {
            'orphan-images' => $this->junkCleaner->deleteOrphanImages($this->intersectManagedPaths($validated['paths'] ?? [], array_column($this->junkCleaner->orphanImages(), 'path'))),
            'missing-references' => $this->junkCleaner->cleanMissingReferences($this->intersectManagedPaths($validated['paths'] ?? [], array_column($this->junkCleaner->missingReferences(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null), 'path')), isset($validated['shop_id']) ? (int) $validated['shop_id'] : null),
            'empty-folders' => $this->junkCleaner->deleteEmptyFolders($validated['paths'] ?? []),
            'old-backups' => $this->backupManager->deleteBackups($validated['filenames'] ?? []),
            'logs' => $this->junkCleaner->truncateLogs($validated['filenames'] ?? []),
            'expired-sessions' => $this->junkCleaner->clearExpiredSessions(),
            'failed-jobs' => $this->junkCleaner->clearFailedJobs(),
            'cache' => $this->junkCleaner->clearCacheTable(),
            'compiled-views' => $this->junkCleaner->clearCompiledViews(),
            'temp-uploads' => $this->junkCleaner->clearTempUploads($validated['paths'] ?? []),
            'delete-shop-images' => $this->junkCleaner->deleteAllImagesForShop((int) $validated['shop_id']),
        };

        if (isset($validated['shop_id'])) {
            $this->shopStorage->forget((int) $validated['shop_id']);
        }

        $this->overviewService->forget();
        ActivityLogger::record(
            'admin.storage.cleanup',
            'storage',
            null,
            array_merge(['action' => $validated['action']], $result),
            null,
            __('admin_storage.activity.cleanup')
        );

        return $this->wantsJson($request)
            ? response()->json(['message' => __('admin_storage.messages.cleanup_complete'), 'result' => $result])
            : back()->with('success', __('admin_storage.messages.cleanup_complete'));
    }

    public function createBackup(): RedirectResponse
    {
        $result = $this->backupManager->createBackup((int) config('admin-storage.keep_backups', 7));
        ActivityLogger::record('admin.storage.backup-create', 'storage', null, $result, null, __('admin_storage.activity.create_backup'));

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok']
            ? __('admin_storage.messages.backup_created')
            : __('admin_storage.messages.backup_failed'));
    }

    public function downloadBackup(string $filename)
    {
        $path = $this->backupManager->resolve($filename);
        abort_if(! $path, 404);

        return response()->download($path, basename($path));
    }

    public function downloadLog(string $filename)
    {
        $log = collect($this->junkCleaner->logFiles())->firstWhere('filename', $filename);
        abort_if(! $log, 404);

        return response()->download($log['path'], $log['filename']);
    }

    public function legacy(Request $request)
    {
        return view('admin.image-compression', [
            'legacyCompressionUrl' => url('/compress-and-cleanup-images'),
            'legacyQuickUrl' => url('/quick-compress-images'),
        ]);
    }

    public function quickCompress()
    {
        return redirect()->route('compress.cleanup.images');
    }

    public function legacyMutate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => 'required|string|in:compress,cleanup',
            'offset' => 'nullable|integer|min:0',
            'batch' => 'nullable|integer|min:1|max:100',
            'confirm' => 'nullable|boolean',
        ]);

        if (! ($validated['confirm'] ?? false)) {
            return response()->json(['errors' => [__('admin_storage.messages.confirm_required')]], 422);
        }

        if ($validated['step'] === 'compress') {
            $response = $this->runCompression(new Request([
                'scope' => 'all',
                'offset' => (int) ($validated['offset'] ?? 0),
                'limit' => (int) ($validated['batch'] ?? 10),
                'max_width' => 800,
                'max_height' => 800,
                'quality' => 75,
            ]));
            $payload = $response->getData(true);

            return response()->json([
                'compressed' => (int) ($payload['results']['optimized'] ?? 0),
                'deleted' => 0,
                'errors' => $payload['results']['errors'] ?? [],
                'deleted_files' => [],
                'hasMore' => (bool) ($payload['hasMore'] ?? false),
                'nextOffset' => (int) ($payload['nextOffset'] ?? 0),
                'progress' => (int) ($payload['progress'] ?? 0),
                'results' => $payload['results'] ?? [],
            ]);
        }

        $orphans = $this->junkCleaner->orphanImages();
        $cleanup = $this->runCleanup(new Request([
            'action' => 'orphan-images',
            'paths' => array_column($orphans, 'path'),
        ]));
        $payload = $cleanup->getData(true);

        return response()->json([
            'deleted' => (int) ($payload['result']['deleted'] ?? 0),
            'deleted_files' => $payload['result']['deleted_paths'] ?? [],
            'errors' => [],
            'hasMore' => false,
            'nextOffset' => 0,
        ]);
    }

    public function quickCompressMutate()
    {
        $paths = ProductImagePathResolver::fromFirstProducts(5);
        $results = $this->imageOptimizer->optimize($paths, [
            'max_width' => 800,
            'max_height' => 800,
            'quality' => 75,
        ], false);

        ActivityLogger::record(
            'admin.storage.compress',
            'storage',
            null,
            [
                'scope' => 'legacy-quick',
                'paths' => $paths,
                'saved_bytes' => $results['saved_bytes'],
                'dry_run' => false,
            ],
            null,
            __('admin_storage.activity.image_compression')
        );

        return response()->json([
            'compressed' => $results['optimized'],
            'errors' => $results['errors'],
        ]);
    }

    private function ownerAccount(User $shop): User
    {
        abort_unless(in_array($shop->role, ['shop_owner', 'restaurant', 'merchant', 'disabled'], true), 404);

        return $shop;
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array<string, mixed>}
     */
    private function shopTable(Request $request): array
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|in:shop,status,entries_used,images_unique,images_bytes,potential_savings',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $filters['sort'] = $filters['sort'] ?? 'shop';
        $filters['direction'] = $filters['direction'] ?? 'asc';
        $filters['per_page'] = (int) ($filters['per_page'] ?? 25);

        $usersQuery = User::query()
            ->whereIn('role', ['shop_owner', 'restaurant', 'merchant', 'disabled'])
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $term = '%' . trim((string) $filters['search']) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('owner_name', 'like', $term);
                });
            });

        if (in_array($filters['sort'], ['shop', 'status'], true)) {
            if ($filters['sort'] === 'shop') {
                $direction = $filters['direction'] === 'desc' ? 'DESC' : 'ASC';
                $usersQuery->orderByRaw("COALESCE(NULLIF(owner_name, ''), name, email) {$direction}");
            } else {
                $usersQuery->orderBy('role', $filters['direction']);
            }
        } else {
            $usersQuery->orderBy('name');
        }

        $shops = $usersQuery->paginate($filters['per_page'])->withQueryString();
        $users = collect($shops->items());
        $ownerIds = $users->pluck('id')->all();
        $entryUsage = $this->shopStorage->entryUsageForShops($ownerIds);
        $imageUsage = $this->shopStorage->imageUsageForShops($ownerIds);
        $imageSummary = $this->shopStorage->summaryForShops($ownerIds);

        $rows = $users->map(function (User $user) use ($entryUsage, $imageUsage, $imageSummary): array {
            $summary = $imageSummary[$user->id] ?? [
                'count' => 0,
                'bytes' => 0,
                'missing' => 0,
                'average_bytes' => 0,
                'potential_savings' => 0,
                'last_compressed' => null,
            ];
            $entriesUsed = (int) ($entryUsage[$user->id] ?? 0);
            $entryLimit = $user->entry_limit !== null ? (int) $user->entry_limit : null;
            $imageLimit = $user->image_limit !== null ? (int) $user->image_limit : null;
            $imageSlotsUsed = (int) ($imageUsage[$user->id] ?? 0);

            return [
                'id' => $user->id,
                'shop' => $user->owner_name ?: $user->name ?: $user->email,
                'name' => $user->name,
                'owner_name' => $user->owner_name,
                'email' => $user->email,
                'status' => $user->role,
                'entries_used' => $entriesUsed,
                'entry_limit' => $entryLimit,
                'entry_remaining' => $entryLimit === null ? null : max(0, $entryLimit - $entriesUsed),
                'images_unique' => (int) $summary['count'],
                'images_bytes' => (int) $summary['bytes'],
                'images_average' => (int) $summary['average_bytes'],
                'images_missing' => (int) $summary['missing'],
                'image_limit' => $imageLimit,
                'image_slots_used' => $imageSlotsUsed,
                'image_slots_remaining' => $imageLimit === null ? null : max(0, $imageLimit - $imageSlotsUsed),
                'potential_savings' => (int) $summary['potential_savings'],
                'last_compressed' => $summary['last_compressed'],
            ];
        })->all();

        if (! in_array($filters['sort'], ['shop', 'status'], true)) {
            usort($rows, function (array $a, array $b) use ($filters): int {
                $sort = $filters['sort'];
                $direction = $filters['direction'] === 'desc' ? -1 : 1;
                $left = $a[$sort] ?? null;
                $right = $b[$sort] ?? null;

                if ($left instanceof \Carbon\CarbonInterface) {
                    $left = $left?->timestamp ?? 0;
                }
                if ($right instanceof \Carbon\CarbonInterface) {
                    $right = $right?->timestamp ?? 0;
                }

                if (is_string($left) || is_string($right)) {
                    return $direction * strcmp((string) $left, (string) $right);
                }

                return $direction * (($left <=> $right) ?: ($a['id'] <=> $b['id']));
            });
        }

        $shops->setCollection(collect($rows));

        return [$shops, $filters];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function resolveCompressionPaths(array $validated): array
    {
        return match ($validated['scope']) {
            'shop' => array_keys($this->shopStorage->images((int) $validated['shop_id'])),
            'selected' => ! empty($validated['shop_id'])
                ? $this->intersectManagedPaths($validated['paths'] ?? [], array_keys($this->shopStorage->images((int) $validated['shop_id'])))
                : $this->intersectManagedPaths($validated['paths'] ?? [], array_keys($this->shopStorage->referenceMap())),
            default => array_keys($this->shopStorage->referenceMap()),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cleanupPreviewItems(string $action, ?int $shopId = null): array
    {
        return match ($action) {
            'orphan-images' => $this->junkCleaner->orphanImages(),
            'missing-references' => $this->junkCleaner->missingReferences($shopId),
            'empty-folders' => $this->junkCleaner->emptyFolders(),
            'old-backups' => array_values(array_filter($this->backupManager->listBackups((int) config('admin-storage.keep_backups', 7)), fn ($backup) => $backup['deletable'])),
            'logs' => $this->junkCleaner->logFiles(),
            'expired-sessions' => [['count' => Schema::hasTable('sessions') ? DB::table('sessions')->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)->count() : 0]],
            'failed-jobs' => [[
                'count' => Schema::hasTable('failed_jobs')
                    ? DB::table('failed_jobs')->when(
                        Schema::hasColumn('failed_jobs', 'failed_at'),
                        fn ($query) => $query->where('failed_at', '<', now()->subDays(30))
                    )->count()
                    : 0,
            ]],
            'cache' => [['count' => Schema::hasTable('cache') ? DB::table('cache')->count() : 0]],
            'compiled-views' => [['path' => storage_path('framework/views')]],
            'temp-uploads' => $this->junkCleaner->tempUploadLeftovers(),
            'delete-shop-images' => collect($this->shopStorage->images((int) $shopId))->values()->all(),
        };
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson();
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function intersectManagedPaths(array $paths, array $allowed): array
    {
        $allowedLookup = array_fill_keys($allowed, true);

        return array_values(array_filter(
            ShopStorageService::filterManagedProductPaths($paths),
            fn (string $path) => isset($allowedLookup[$path])
        ));
    }

    private function cleanupActionLabel(string $action): string
    {
        return match ($action) {
            'orphan-images' => __('admin_storage.cleanup.orphan_images'),
            'missing-references' => __('admin_storage.cleanup.missing_references'),
            'empty-folders' => __('admin_storage.cleanup.empty_folders'),
            'old-backups' => __('admin_storage.cleanup.old_backups'),
            'logs' => __('admin_storage.cleanup.logs'),
            'expired-sessions' => __('admin_storage.cleanup.expired_sessions'),
            'failed-jobs' => __('admin_storage.cleanup.failed_jobs'),
            'cache' => __('admin_storage.cleanup.cache'),
            'compiled-views' => __('admin_storage.cleanup.compiled_views'),
            'temp-uploads' => __('admin_storage.cleanup.temp_uploads'),
            'delete-shop-images' => __('admin_storage.cleanup.delete_shop_images'),
            default => $action,
        };
    }

    private function isSafeManagedDownloadPath(string $path): bool
    {
        $disk = Storage::disk('public');
        $productsRoot = realpath($disk->path('products'));
        $absolute = realpath($disk->path($path));

        return $productsRoot !== false
            && $absolute !== false
            && str_starts_with($absolute, $productsRoot . DIRECTORY_SEPARATOR);
    }
}

final class ProductImagePathResolver
{
    /**
     * @return list<string>
     */
    public static function fromFirstProducts(int $limit): array
    {
        $paths = [];

        \App\Models\Product::withoutGlobalScopes()
            ->whereNotNull('pictures')
            ->where('pictures', '!=', '')
            ->orderBy('id')
            ->take($limit)
            ->get(['pictures'])
            ->each(function ($product) use (&$paths): void {
                foreach (ShopStorageService::decodePictures($product->pictures) as $path) {
                    $paths[$path] = $path;
                }
            });

        return array_values($paths);
    }
}
