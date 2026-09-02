<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Profile picture processing convention.
 *
 * Every profile picture is processed into a consistent passport-style
 * 7:9 portrait image (350 x 450 px), re-encoded as JPEG and kept well
 * under the 100 KB absolute upload limit (50 KB recommended target).
 */
class ProfileImageService
{
    public const RATIO_W = 7;

    public const RATIO_H = 9;

    public const WIDTH = 350;

    public const HEIGHT = 450;

    public const MAX_BYTES = 100 * 1024;

    public const TARGET_BYTES = 50 * 1024;

    public const MAX_DIMENSION = 6000;

    /**
     * Validate, crop to 7:9, resize to 350x450, compress and store.
     *
     * @return string stored relative path (public disk)
     */
    public function processAndStore(UploadedFile $file, string $subdir = 'students'): string
    {
        $this->assertValidFile($file);

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw new InvalidArgumentException('Please select a valid JPG, PNG, or WebP image.');
        }

        $types = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $type = $types[$info['mime']] ?? null;
        if ($type === null) {
            throw new InvalidArgumentException('Please select a valid JPG, PNG, or WebP image.');
        }

        if ($info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('The image dimensions are too large.');
        }

        $src = $this->load($file->getRealPath(), $type);
        if (! $src) {
            throw new InvalidArgumentException('Please select a valid JPG, PNG, or WebP image.');
        }

        $sourceW = imagesx($src);
        $sourceH = imagesy($src);
        [$cropX, $cropY, $cropW, $cropH] = $this->cropBox($sourceW, $sourceH);

        $dst = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, self::WIDTH, self::HEIGHT, $cropW, $cropH);
        imagedestroy($src);

        $encoded = $this->encodeJpeg($dst);
        imagedestroy($dst);

        if ($encoded === null) {
            throw new InvalidArgumentException('The image could not be compressed below 100 KB. Please upload a smaller photo.');
        }

        $subdir = trim($subdir, '/');
        $path = 'profile-images/'.$subdir.'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $encoded, 'public');

        return $path;
    }

    private function assertValidFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is invalid.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Please upload an image smaller than 100 KB.');
        }
    }

    /**
     * Face-centered 7:9 crop box, biased slightly upward to keep the head.
     *
     * @return array{int,int,int,int} [x, y, width, height]
     */
    private function cropBox(int $width, int $height): array
    {
        $targetRatio = self::RATIO_W / self::RATIO_H;

        if ($width / $height > $targetRatio) {
            // Too wide: crop the sides.
            $cropW = (int) round($height * $targetRatio);
            $cropH = $height;
            $cropX = (int) round(($width - $cropW) / 2);
            $cropY = 0;
        } else {
            // Too tall: crop the bottom, keeping the head/top area.
            $cropW = $width;
            $cropH = (int) round($width / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($height - $cropH) * 0.25);
        }

        return [$cropX, $cropY, $cropW, $cropH];
    }

    /**
     * Re-encode as JPEG with progressive quality targeting <= 50 KB.
     */
    private function encodeJpeg($image): ?string
    {
        for ($quality = 85; $quality >= 40; $quality -= 10) {
            ob_start();
            imagejpeg($image, null, $quality);
            $data = ob_get_clean();

            if ($data === false || strlen($data) > self::MAX_BYTES) {
                continue;
            }

            if (strlen($data) <= self::TARGET_BYTES) {
                return $data;
            }

            $candidate = $data;
        }

        return $candidate ?? null;
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
