<?php

namespace App\Services {
    final class ProductExifStub
    {
        public static array $orientations = [];
    }

    function exif_read_data(string $path): array|false
    {
        return ProductExifStub::$orientations[$path] ?? false;
    }
}

namespace Tests\Feature {

    use App\Services\ImageProcessor;
    use App\Services\ProductExifStub;
    use Illuminate\Http\UploadedFile;
    use Illuminate\Support\Facades\Storage;
    use Tests\Support\Builds;

    uses(Builds::class);

    beforeEach(function () {
        Storage::fake('public');
        ProductExifStub::$orientations = [];
    });

    test('image processor rescales large png uploads and keeps png output', function () {
        $file = UploadedFile::fake()->image('photo.png', 2400, 1800);

        $path = app(ImageProcessor::class)->storeProductImage($file);

        expect($path)->toStartWith('products/')
            ->and($path)->toEndWith('.png');

        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

        expect(max($width, $height))->toBeLessThanOrEqual(1600);
    });

    test('image processor auto orients jpeg uploads when exif rotation is present', function () {
        $file = UploadedFile::fake()->image('rotated.jpg', 120, 60);
        ProductExifStub::$orientations[$file->getRealPath()] = ['Orientation' => 6];

        $path = app(ImageProcessor::class)->storeProductImage($file);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));

        expect($width)->toBe(60)
            ->and($height)->toBe(120);
    });

    test('image processor rejects invalid files', function () {
        $file = UploadedFile::fake()->createWithContent('note.txt', 'not-an-image');

        expect(fn () => app(ImageProcessor::class)->storeProductImage($file))
            ->toThrow(\InvalidArgumentException::class, 'products_ui.validation.invalid_image');
    });

    test('image processor rejects oversized source dimensions before decoding', function () {
        $file = UploadedFile::fake()->createWithContent('bomb.png', fakeHugePngHeader(13001, 40));

        expect(fn () => app(ImageProcessor::class)->storeProductImage($file))
            ->toThrow(\InvalidArgumentException::class, 'products_ui.validation.image_dimensions');
    });

    test('image processor rejects images that would exceed the memory budget before decoding', function () {
        $previousLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '64M');

        try {
            $file = UploadedFile::fake()->createWithContent('memory-bomb.png', fakeHugePngHeader(9000, 9000));

            expect(fn () => app(ImageProcessor::class)->storeProductImage($file))
                ->toThrow(\InvalidArgumentException::class, 'products_ui.validation.image_memory');
        } finally {
            if (is_string($previousLimit) && $previousLimit !== '') {
                @ini_set('memory_limit', $previousLimit);
            }
        }
    });

    function fakeHugePngHeader(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdrData = pack('N', $width) . pack('N', $height) . "\x08\x02\x00\x00\x00";
        $ihdr = pack('N', 13) . 'IHDR' . $ihdrData . pack('N', crc32('IHDR' . $ihdrData));

        return $signature . $ihdr;
    }
}
