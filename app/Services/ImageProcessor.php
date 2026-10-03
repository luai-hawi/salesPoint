<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageProcessor
{
    private const MAX_DIMENSION = 1600;

    private const MAX_SOURCE_SIDE = 12000;

    private const DEFAULT_MAX_FILES = 12;

    /**
     * @throws \InvalidArgumentException
     */
    public function storeProductImage(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();

        if (! $realPath || ! is_file($realPath)) {
            throw new \InvalidArgumentException('products_ui.validation.invalid_image');
        }

        $info = @getimagesize($realPath);

        if (! is_array($info) || ! isset($info[0], $info[1], $info[2])) {
            throw new \InvalidArgumentException('products_ui.validation.invalid_image');
        }

        [$width, $height, $type] = $info;

        if ($width < 1 || $height < 1 || $width > self::MAX_SOURCE_SIDE || $height > self::MAX_SOURCE_SIDE) {
            throw new \InvalidArgumentException('products_ui.validation.image_dimensions');
        }

        if (! self::canDecodeSafely($info)) {
            throw new \InvalidArgumentException('products_ui.validation.image_memory');
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => @imagecreatefrompng($realPath),
            IMAGETYPE_GIF => @imagecreatefromgif($realPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : null,
            default => null,
        };

        if (! $source) {
            throw new \InvalidArgumentException('products_ui.validation.invalid_image');
        }

        try {
            if ($type === IMAGETYPE_JPEG) {
                $source = $this->autoOrientJpeg($source, $realPath);
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                [$targetWidth, $targetHeight] = $this->scaledDimensions($width, $height, self::MAX_DIMENSION);
                $source = $this->resizeImage($source, $targetWidth, $targetHeight, $this->shouldPreserveAlpha($type, $source));
            }

            $hasAlpha = $this->shouldPreserveAlpha($type, $source);
            [$contents, $extension] = $this->encodeImage($source, $type, $hasAlpha);

            $path = 'products/' . Str::random(40) . '.' . $extension;
            Storage::disk('public')->put($path, $contents);

            return $path;
        } finally {
            if (is_resource($source) || $source instanceof \GdImage) {
                imagedestroy($source);
            }
        }
    }

    /**
     * @return array{
     *   file_bytes:int,
     *   file_kilobytes:int,
     *   request_bytes:int,
     *   max_files:int,
     *   file_label:string,
     *   request_label:string
     * }
     */
    public static function uploadConstraints(): array
    {
        $fileBytes = max(1024, self::effectiveUploadLimitBytes());
        $requestBytes = max($fileBytes, self::postMaxBytes() ?? $fileBytes);
        $maxFiles = max(1, min(self::DEFAULT_MAX_FILES, (int) floor($requestBytes / $fileBytes)));

        return [
            'file_bytes' => $fileBytes,
            'file_kilobytes' => max(1, (int) floor($fileBytes / 1024)),
            'request_bytes' => $requestBytes,
            'max_files' => $maxFiles,
            'file_label' => self::formatBytes($fileBytes),
            'request_label' => self::formatBytes($requestBytes),
        ];
    }

    public static function effectiveUploadLimitBytes(): int
    {
        $uploadMax = self::iniSizeToBytes((string) ini_get('upload_max_filesize')) ?? (8 * 1024 * 1024);
        $postMax = self::postMaxBytes();

        return $postMax === null ? $uploadMax : min($uploadMax, $postMax);
    }

    public static function postMaxBytes(): ?int
    {
        return self::iniSizeToBytes((string) ini_get('post_max_size'));
    }

    public static function memoryLimitBytes(): ?int
    {
        return self::iniSizeToBytes((string) ini_get('memory_limit'));
    }

    private function autoOrientJpeg(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private function scaledDimensions(int $width, int $height, int $maxSide): array
    {
        $ratio = min($maxSide / $width, $maxSide / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function resizeImage(\GdImage $source, int $targetWidth, int $targetHeight, bool $preserveAlpha): \GdImage
    {
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($preserveAlpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        }

        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($source),
            imagesy($source),
        );

        if (is_resource($source) || $source instanceof \GdImage) {
            imagedestroy($source);
        }

        return $target;
    }

    private function shouldPreserveAlpha(int $type, \GdImage $image): bool
    {
        if ($type === IMAGETYPE_PNG) {
            return true;
        }

        if ($type === IMAGETYPE_GIF) {
            return imagecolortransparent($image) >= 0;
        }

        return false;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function encodeImage(\GdImage $image, int $sourceType, bool $preserveAlpha): array
    {
        ob_start();

        if ($preserveAlpha) {
            imagepng($image, null, 6);
            $contents = (string) ob_get_clean();

            return [$contents, 'png'];
        }

        if ($sourceType === IMAGETYPE_WEBP && function_exists('imagewebp')) {
            imagewebp($image, null, 82);
            $contents = (string) ob_get_clean();

            return [$contents, 'webp'];
        }

        imagejpeg($image, null, 82);
        $contents = (string) ob_get_clean();

        return [$contents, 'jpg'];
    }

    /**
     * @param  array<int|string, mixed>  $imageInfo
     */
    private static function canDecodeSafely(array $imageInfo): bool
    {
        $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : 0;
        $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : 0;
        $channels = isset($imageInfo['channels']) ? max(3, (int) $imageInfo['channels']) : 4;
        $bits = isset($imageInfo['bits']) ? max(8, (int) $imageInfo['bits']) : 8;
        $estimatedBytes = (int) ceil($width * $height * $channels * ($bits / 8) * 2.0);
        $memoryLimit = self::memoryLimitBytes();

        if ($estimatedBytes <= 0 || $memoryLimit === null) {
            return $estimatedBytes > 0;
        }

        $available = max(0, $memoryLimit - memory_get_usage(true));

        return $estimatedBytes < $available;
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }

    private static function iniSizeToBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
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
}
