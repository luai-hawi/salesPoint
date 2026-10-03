<?php

namespace App\Services\Admin;

use App\Models\ImageOptimization;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ImageOptimizer
{
    /**
     * @param  array{max_width?: int, max_height?: int, quality?: int}  $options
     * @param  list<string>  $paths
     * @return array{
     *   processed: int,
     *   optimized: int,
     *   skipped: int,
     *   missing: int,
     *   errors: list<string>,
     *   saved_bytes: int,
     *   items: list<array<string, mixed>>
     * }
     */
    public function optimize(array $paths, array $options = [], bool $dryRun = false): array
    {
        $disk = Storage::disk('public');
        $maxWidth = max(1, (int) ($options['max_width'] ?? 1200));
        $maxHeight = max(1, (int) ($options['max_height'] ?? 1200));
        $quality = min(95, max(30, (int) ($options['quality'] ?? 78)));
        $results = [
            'processed' => 0,
            'optimized' => 0,
            'skipped' => 0,
            'missing' => 0,
            'errors' => [],
            'saved_bytes' => 0,
            'items' => [],
        ];

        foreach (array_values(array_unique($paths)) as $path) {
            $results['processed']++;

            try {
                $item = $this->optimizeOne($disk, $path, $maxWidth, $maxHeight, $quality, $dryRun);
                $results['items'][] = $item;

                if ($item['status'] === 'optimized') {
                    $results['optimized']++;
                    $results['saved_bytes'] += (int) $item['saved_bytes'];
                } elseif ($item['status'] === 'missing') {
                    $results['missing']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = $path . ': ' . $e->getMessage();
                $results['items'][] = [
                    'path' => $path,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'saved_bytes' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array{max_width?: int, max_height?: int, quality?: int}  $options
     * @param  list<string>  $paths
     * @return array{
     *   processed: int,
     *   optimizable: int,
     *   estimated_saved_bytes: int,
     *   items: list<array<string, mixed>>
     * }
     */
    public function estimate(array $paths, array $options = []): array
    {
        $result = $this->optimize($paths, $options, true);

        return [
            'processed' => $result['processed'],
            'optimizable' => $result['optimized'],
            'estimated_saved_bytes' => $result['saved_bytes'],
            'items' => $result['items'],
        ];
    }

    private function optimizeOne($disk, string $path, int $maxWidth, int $maxHeight, int $quality, bool $dryRun): array
    {
        if (! $this->isSafeManagedExistingPath($disk, $path)) {
            return [
                'path' => $path,
                'status' => 'missing',
                'message' => 'missing',
                'saved_bytes' => 0,
            ];
        }

        $absolutePath = $disk->path($path);
        $originalBytes = (int) filesize($absolutePath);
        $currentMtime = @filemtime($absolutePath) ?: null;

        if ($this->wasAlreadyOptimized($path, $originalBytes, $currentMtime)) {
            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'already_optimized',
                'saved_bytes' => 0,
            ];
        }

        $imageInfo = @getimagesize($absolutePath);
        if (! is_array($imageInfo) || ! isset($imageInfo['mime'])) {
            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'invalid_image',
                'saved_bytes' => 0,
            ];
        }

        $mime = (string) $imageInfo['mime'];
        if (! $this->canDecodeSafely($imageInfo)) {
            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'memory_limit',
                'saved_bytes' => 0,
            ];
        }

        $resource = $this->createResource($absolutePath, $mime);

        if (! $resource) {
            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'unsupported_format',
                'saved_bytes' => 0,
            ];
        }

        $oriented = $this->applyOrientation($absolutePath, $resource, $mime);
        if ($oriented !== $resource) {
            $this->destroyImage($resource);
        }

        $optimized = $this->resizeResource($oriented, $maxWidth, $maxHeight);
        if ($optimized !== $oriented) {
            $this->destroyImage($oriented);
        }

        $tempFile = $absolutePath . '.opt_' . uniqid('', true);
        $writeOk = $this->writeResource($optimized, $tempFile, $mime, $quality);

        $this->destroyImage($optimized);

        if (! $writeOk || ! file_exists($tempFile)) {
            @unlink($tempFile);

            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'write_failed',
                'saved_bytes' => 0,
            ];
        }

        $optimizedBytes = (int) filesize($tempFile);
        $savedBytes = max(0, $originalBytes - $optimizedBytes);

        if ($savedBytes === 0 || $optimizedBytes >= $originalBytes) {
            @unlink($tempFile);

            return [
                'path' => $path,
                'status' => 'skipped',
                'message' => 'not_smaller',
                'saved_bytes' => 0,
            ];
        }

        if ($dryRun) {
            @unlink($tempFile);

            return [
                'path' => $path,
                'status' => 'optimized',
                'message' => 'dry_run',
                'saved_bytes' => $savedBytes,
                'original_bytes' => $originalBytes,
                'optimized_bytes' => $optimizedBytes,
            ];
        }

        $backupFile = $absolutePath . '.bak_' . uniqid('', true);
        if (! @rename($absolutePath, $backupFile)) {
            @unlink($tempFile);
            throw new \RuntimeException('backup_swap_failed');
        }

        if (! @rename($tempFile, $absolutePath)) {
            @rename($backupFile, $absolutePath);
            @unlink($tempFile);
            throw new \RuntimeException('replace_failed');
        }

        @unlink($backupFile);

        $this->storeOptimization($path, $originalBytes, $optimizedBytes, @filemtime($absolutePath) ?: time());

        return [
            'path' => $path,
            'status' => 'optimized',
            'message' => 'optimized',
            'saved_bytes' => $savedBytes,
            'original_bytes' => $originalBytes,
            'optimized_bytes' => $optimizedBytes,
        ];
    }

    private function createResource(string $path, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => null,
        };
    }

    private function applyOrientation(string $path, $resource, string $mime)
    {
        if ($mime !== 'image/jpeg') {
            return $resource;
        }

        if (! function_exists('exif_read_data')) {
            throw new \RuntimeException('orientation_unavailable');
        }

        $exif = @exif_read_data($path);
        if ($exif === false) {
            if (! $this->jpegContainsExifMarker($path)) {
                return $resource;
            }

            throw new \RuntimeException('orientation_unreadable');
        }

        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flipResource($resource, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($resource, 180, 0),
            4 => $this->flipResource($resource, IMG_FLIP_VERTICAL),
            5 => $this->flipResource(imagerotate($resource, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($resource, -90, 0),
            7 => $this->flipResource(imagerotate($resource, -90, 0), IMG_FLIP_VERTICAL),
            8 => imagerotate($resource, 90, 0),
            default => $resource,
        };
    }

    private function resizeResource($resource, int $maxWidth, int $maxHeight)
    {
        $width = imagesx($resource);
        $height = imagesy($resource);

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $resource;
        }

        $ratio = min($maxWidth / max(1, $width), $maxHeight / max(1, $height));
        $newWidth = max(1, (int) floor($width * $ratio));
        $newHeight = max(1, (int) floor($height * $ratio));
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($newImage, $resource, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $newImage;
    }

    private function writeResource($resource, string $destination, string $mime, int $quality): bool
    {
        return match ($mime) {
            'image/jpeg' => imagejpeg($resource, $destination, $quality),
            'image/png' => imagepng($resource, $destination, 9),
            default => false,
        };
    }

    private function flipResource($resource, int $mode)
    {
        if (function_exists('imageflip')) {
            imageflip($resource, $mode);

            return $resource;
        }

        return $resource;
    }

    private function destroyImage($image): void
    {
        if ($image instanceof \GdImage) {
            imagedestroy($image);
        }
    }

    /**
     * @param  array<int|string, mixed>  $imageInfo
     */
    private function canDecodeSafely(array $imageInfo): bool
    {
        $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : 0;
        $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : 0;
        $channels = isset($imageInfo['channels']) ? max(3, (int) $imageInfo['channels']) : 4;
        $bits = isset($imageInfo['bits']) ? max(8, (int) $imageInfo['bits']) : 8;
        $estimatedBytes = (int) ceil($width * $height * $channels * ($bits / 8) * 2.5);

        $memoryLimit = $this->memoryLimitBytes();
        if ($memoryLimit === null) {
            return true;
        }

        $available = max(0, $memoryLimit - memory_get_usage(true));

        return $estimatedBytes > 0 && $estimatedBytes < $available;
    }

    private function memoryLimitBytes(): ?int
    {
        $value = ini_get('memory_limit');
        if (! is_string($value) || $value === '' || $value === '-1') {
            return null;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => (int) $value,
        };
    }

    private function jpegContainsExifMarker(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 65536);
        fclose($handle);

        return is_string($sample) && str_contains($sample, 'Exif');
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

    private function storeOptimization(string $path, int $originalBytes, int $optimizedBytes, int $optimizedMtime): void
    {
        if (! Schema::hasTable('image_optimizations')) {
            return;
        }

        ImageOptimization::query()->updateOrCreate(
            ['path' => $path],
            [
                'original_bytes' => $originalBytes,
                'optimized_bytes' => $optimizedBytes,
                'optimized_mtime' => $optimizedMtime,
                'optimized_at' => now(),
            ]
        );
    }

    private function wasAlreadyOptimized(string $path, int $currentBytes, ?int $mtime): bool
    {
        if (! Schema::hasTable('image_optimizations')) {
            return false;
        }

        $record = ImageOptimization::query()->where('path', $path)->first();

        return $record !== null
            && (int) $record->optimized_bytes === $currentBytes
            && (int) $record->optimized_mtime === (int) $mtime;
    }
}
