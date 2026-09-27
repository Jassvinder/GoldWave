<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * T-127 (20-09-2026) — Profile Photo / Passbook / Cancelled Cheque images are converted to WebP before they are stored
 * (smaller files, one consistent format). A PDF (bank proof allows PDF) can't be a WebP, so non-image uploads are stored
 * exactly as uploaded; so is an image GD cannot decode or encode, rather than rejecting an upload that already passed validation.
 */
class WebpImageStore
{
    private const CONVERTIBLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

    private const QUALITY = 82;

    /** Returns the stored path on `$disk`, or `false` when nothing could be stored. */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string|false
    {
        $webp = self::convert($file);

        if ($webp === null) {
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';

        return Storage::disk($disk)->put($path, $webp) ? $path : false;
    }

    /** WebP bytes for an image upload, or `null` when the file isn't a convertible image / GD lacks WebP support. */
    private static function convert(UploadedFile $file): ?string
    {
        if (! function_exists('imagewebp') || ! in_array($file->getMimeType(), self::CONVERTIBLE_MIME_TYPES, true)) {
            return null;
        }

        $contents = @file_get_contents($file->getRealPath());
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        $image = self::applyExifOrientation($image, $file);

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $encoded = imagewebp($image, null, self::QUALITY);
        $bytes = ob_get_clean();

        return $encoded && is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag; bake it in, since WebP output drops the metadata. */
    private static function applyExifOrientation(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        return $rotated;
    }
}
