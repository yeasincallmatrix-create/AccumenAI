<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ImageCompressorService
{
    protected int $maxSize = 2 * 1024 * 1024; // 2 MB
    protected int $quality = 85;
    protected int $maxWidth = 2000;
    protected int $maxHeight = 2000;

    public function compress(UploadedFile $file): UploadedFile
    {
        // If file is already under 2 MB, return as is
        if ($file->getSize() <= $this->maxSize) {
            return $file;
        }

        // For non-image files (PDF, etc.), skip compression
        $mime = $file->getMimeType() ?? '';
        if (!str_starts_with($mime, 'image/')) {
            // Fallback check by extension
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                return $file;
            }
        }

        $tempPath = $file->getPathname();

        // Try Intervention Image if available (v3)
        if (class_exists(\Intervention\Image\ImageManager::class)) {
            try {
                return $this->compressViaIntervention($file, $tempPath);
            } catch (\Throwable $e) {
                Log::warning('Intervention compress failed, falling back to GD', ['error' => $e->getMessage()]);
            }
        }

        // Fallback to GD
        try {
            return $this->compressViaGd($file, $tempPath);
        } catch (\Throwable $e) {
            Log::warning('GD compress failed', ['error' => $e->getMessage()]);
            return $file;
        }
    }

    private function compressViaIntervention(UploadedFile $file, string $tempPath): UploadedFile
    {
        // Intervention v3 API
        $manager = null;
        // Try GD driver, fallback to Imagick
        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
        } catch (\Throwable $e) {
            $manager = \Intervention\Image\ImageManager::gd();
        }

        $image = $manager->read($tempPath);

        if ($image->width() > $this->maxWidth || $image->height() > $this->maxHeight) {
            $image->scale(width: $this->maxWidth, height: $this->maxHeight);
        }

        $compressedPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.jpg';
        // Intervention v3 save with quality
        $image->save($compressedPath, quality: $this->quality);

        // If still over 2MB, try lower quality iteratively
        $quality = $this->quality;
        while (filesize($compressedPath) > $this->maxSize && $quality > 40) {
            $quality -= 10;
            $image->save($compressedPath, quality: $quality);
        }

        return new UploadedFile(
            $compressedPath,
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg',
            'image/jpeg',
            filesize($compressedPath),
            UPLOAD_ERR_OK,
            true
        );
    }

    private function compressViaGd(UploadedFile $file, string $tempPath): UploadedFile
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
            return $file;
        }

        $info = @getimagesize($tempPath);
        if ($info === false) {
            return $file;
        }

        [$width, $height, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tempPath),
            IMAGETYPE_PNG => @imagecreatefrompng($tempPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tempPath) : null,
            IMAGETYPE_GIF => @imagecreatefromgif($tempPath),
            default => null,
        };

        if ($src === null || $src === false) {
            return $file;
        }

        // Calculate scaled dimensions
        $newWidth = $width;
        $newHeight = $height;
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
        }

        if ($newWidth !== $width || $newHeight !== $height) {
            $dst = imagecreatetruecolor($newWidth, $newHeight);
            // Preserve transparency for PNG/WebP by filling white background for JPEG output
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($src);
            $src = $dst;
            $width = $newWidth;
            $height = $newHeight;
        }

        $compressedPath = tempnam(sys_get_temp_dir(), 'ocr_') . '.jpg';

        // Try quality 85, then step down if still >2MB
        $quality = $this->quality;
        imagejpeg($src, $compressedPath, $quality);
        while (filesize($compressedPath) > $this->maxSize && $quality > 40) {
            $quality -= 10;
            imagejpeg($src, $compressedPath, $quality);
        }

        imagedestroy($src);

        // If compressed is not smaller, return original
        if (filesize($compressedPath) >= $file->getSize()) {
            @unlink($compressedPath);
            return $file;
        }

        return new UploadedFile(
            $compressedPath,
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg',
            'image/jpeg',
            filesize($compressedPath),
            UPLOAD_ERR_OK,
            true
        );
    }
}
