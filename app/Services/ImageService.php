<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Re-encodes every upload server-side: auto-orients from EXIF, strips all
 * metadata (incl. GPS), downsizes to max 1600px wide and stores a
 * progressive JPEG at quality ~70. The original file is never stored.
 */
class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $driver = config('beercheck.images.driver') === 'imagick' && extension_loaded('imagick')
            ? new ImagickDriver
            : new GdDriver;

        $this->manager = new ImageManager($driver, autoOrientation: true, strip: true);
    }

    /** @return string path relative to the public disk */
    public function storeCompressed(UploadedFile $file, ?string $directory = null): string
    {
        $cfg = config('beercheck.images');

        $encoded = $this->manager->read($file->getRealPath())
            ->scaleDown(width: $cfg['max_width'], height: $cfg['max_height'])
            ->toJpeg(quality: $cfg['jpeg_quality'], progressive: true, strip: true);

        $path = sprintf('%s/%s/%s.jpg', $directory ?? $cfg['directory'], now()->format('Y/m'), Str::uuid());

        Storage::disk('public')->put($path, (string) $encoded, ['visibility' => 'public']);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
