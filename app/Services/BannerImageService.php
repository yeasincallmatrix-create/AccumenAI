<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Banner image processing — non-destructive, isolated from ProfileImageService.
 *
 * Profile pictures stay untouched (350x450 7:9 JPEG 100KB→50KB).
 * Banners are processed separately:
 *  - Accept any common image (jpeg/png/webp, gif excluded per spec)
 *  - Auto-convert to WebP (preferred) or JPEG fallback
 *  - Resize to max 1920x1080 preserving aspect (no crop)
 *  - Iterative quality until ≤200KB, regardless of original upload size
 */
class BannerImageService
{
    public const TARGET_BYTES = 200 * 1024;

    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // accept up to 5MB before compress

    public const MAX_DIMENSION = 6000;

    public const MAX_WIDTH = 1920;

    public const MAX_HEIGHT = 1080;

    /**
     * Validate, resize, transcode to WebP/JPEG and store.
     *
     * @return string stored relative path (public disk)
     */
    public function processAndStore(UploadedFile $file, int $instituteId): string
    {
        $this->assertValidFile($file);

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw new InvalidArgumentException('Please select a valid JPG or WebP image.');
        }

        $mimeMap = [
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $type = $mimeMap[$info['mime']] ?? null;
        if ($type === null) {
            throw new InvalidArgumentException('Please select a valid JPG or WebP image (PNG will be auto-converted).');
        }

        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('The image dimensions are too large (max 6000px).');
        }

        $src = $this->load($file->getRealPath(), $type);
        if (!$src) {
            throw new InvalidArgumentException('Please select a valid JPG or WebP image.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        [$dstW, $dstH, $resized] = $this->boundedSize($srcW, $srcH);

        if ($resized !== null) {
            $resizedImg = imagecreatetruecolor($dstW, $dstH);
            // For WebP output preserve transparency during resize.
            imagealphablending($resizedImg, false);
            imagesavealpha($resizedImg, true);
            $white = imagecolorallocate($resizedImg, 255, 255, 255);
            imagefill($resizedImg, 0, 0, $white);
            // If source has transparency, blend onto white before copy to keep JPEG clean.
            // For WebP path we keep alpha, but white fill does not hurt (WebP supports alpha).
            imagecopyresampled($resizedImg, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($src);
            $src = $resizedImg;
            $srcW = $dstW;
            $srcH = $dstH;
        }

        $encoded = $this->encodeUnderLimit($src);
        imagedestroy($src);

        if ($encoded === null) {
            throw new InvalidArgumentException('The image could not be compressed below 200 KB. Please try a smaller image.');
        }

        [$data, $ext] = $encoded;

        $path = 'course-banners/'.$instituteId.'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $data, 'public');

        return $path;
    }

    private function assertValidFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is invalid.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Please upload an image smaller than 5 MB (it will be compressed to 200 KB).');
        }
    }

    /**
     * Compute bounded size preserving aspect ratio. Returns [w,h, needsResize?]
     * @return array{int,int,?bool}
     */
    private function boundedSize(int $w, int $h): array
    {
        if ($w <= self::MAX_WIDTH && $h <= self::MAX_HEIGHT) {
            return [$w, $h, null];
        }

        $ratio = $w / $h;
        $maxRatio = self::MAX_WIDTH / self::MAX_HEIGHT;

        if ($ratio > $maxRatio) {
            $newW = self::MAX_WIDTH;
            $newH = (int) round($newW / $ratio);
        } else {
            $newH = self::MAX_HEIGHT;
            $newW = (int) round($newH * $ratio);
        }

        return [$newW, $newH, true];
    }

    /**
     * Try WebP first (smaller), fallback to JPEG. Iterative quality 90→40.
     * @return array{string,string}|null [binary, ext]
     */
    private function encodeUnderLimit($image): ?array
    {
        $supportsWebP = function_exists('imagewebp') && function_exists('imagecreatefromwebp');

        if ($supportsWebP) {
            for ($q = 90; $q >= 40; $q -= 10) {
                ob_start();
                $ok = @imagewebp($image, null, $q);
                $data = ob_get_clean();
                if (!$ok || $data === false || $data === '') {
                    continue;
                }
                if (strlen($data) <= self::TARGET_BYTES) {
                    return [$data, 'webp'];
                }
            }
        }

        // JPEG fallback (also tried if WebP did not meet target)
        for ($q = 85; $q >= 40; $q -= 10) {
            ob_start();
            @imagejpeg($image, null, $q);
            $data = ob_get_clean();
            if ($data === false || $data === '') {
                continue;
            }
            if (strlen($data) <= self::TARGET_BYTES) {
                return [$data, 'jpg'];
            }
        }

        return null;
    }

    private function load(string $path, string $type)
    {
        return match ($type) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
        };
    }
}
