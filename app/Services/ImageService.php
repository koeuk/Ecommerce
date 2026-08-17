<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Product photos are the heaviest asset on the site, and the storefront was
 * being served full-size camera originals.
 *
 * Every upload is converted to WebP at two sizes: a display image capped at
 * 1600px and a 400px thumbnail for grids and cart lines. Both are scaled
 * down only — a small image is never upscaled into a bigger file.
 */
class ImageService
{
    private const MAX_WIDTH = 1600;

    private const THUMB_WIDTH = 400;

    private const QUALITY = 82;

    public function __construct(private readonly ImageManager $images) {}

    public static function make(): self
    {
        return new self(new ImageManager(new Driver));
    }

    /**
     * Stores an upload as WebP plus a thumbnail.
     *
     * @return array{path: string, thumbnail_path: string}
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): array
    {
        $name = Str::random(20);

        $path = "{$directory}/{$name}.webp";
        $thumbnailPath = "{$directory}/{$name}-thumb.webp";

        $image = $this->images->decodePath($file->getRealPath());

        // scaleDown never enlarges, so a 200px logo stays 200px.
        Storage::disk($disk)->put(
            $path,
            (string) $image->scaleDown(width: self::MAX_WIDTH)->encode(new WebpEncoder(self::QUALITY))
        );

        Storage::disk($disk)->put(
            $thumbnailPath,
            (string) $this->images->decodePath($file->getRealPath())
                ->scaleDown(width: self::THUMB_WIDTH)
                ->encode(new WebpEncoder(self::QUALITY))
        );

        return ['path' => $path, 'thumbnail_path' => $thumbnailPath];
    }

    /** Removes a stored image and its thumbnail, ignoring remote URLs. */
    public function delete(?string $path, ?string $thumbnailPath = null, string $disk = 'public'): void
    {
        foreach ([$path, $thumbnailPath] as $target) {
            if (blank($target) || Str::startsWith($target, ['http://', 'https://'])) {
                continue;
            }

            Storage::disk($disk)->delete($target);
        }
    }
}
