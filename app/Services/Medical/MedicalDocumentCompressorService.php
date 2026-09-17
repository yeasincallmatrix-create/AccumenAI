<?php

namespace App\Services\Medical;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Compress medical document uploads toward a ~100 KB target.
 *
 * - Images (jpg/jpeg/png/webp/gif/bmp): re-encoded to JPEG with iterative
 *   quality + downscale loop until <= 100 KB (GD only, always available).
 * - PDFs: recompressed via Ghostscript (if installed) with progressively
 *   stronger settings until <= 100 KB.
 * - Office docs / txt / dicom: already compressed or not safely re-encodable,
 *   stored as-is (still capped at 20 MB by validation).
 *
 * Files already at/below the target are returned untouched.
 */
class MedicalDocumentCompressorService
{
    public const TARGET_BYTES = 100 * 1024;

    public const MAX_IMAGE_DIMENSION = 1600;

    /**
     * @return array{file: UploadedFile, compressed: bool, originalSize: int, finalSize: int, note: ?string}
     */
    public function compress(UploadedFile $file): array
    {
        $originalSize = (int) ($file->getSize() ?? filesize($file->getRealPath()));
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) ($file->getMimeType() ?? '');

        $isImage = str_starts_with($mime, 'image/')
            || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true);
        $isPdf = $mime === 'application/pdf' || $ext === 'pdf';

        if ($originalSize <= self::TARGET_BYTES) {
            return [
                'file' => $file,
                'compressed' => false,
                'originalSize' => $originalSize,
                'finalSize' => $originalSize,
                'note' => null,
            ];
        }

        try {
            if ($isImage) {
                $compressed = $this->compressImage($file);
                if ($compressed !== null) {
                    $finalSize = (int) filesize($compressed->getRealPath());

                    return [
                        'file' => $compressed,
                        'compressed' => true,
                        'originalSize' => $originalSize,
                        'finalSize' => $finalSize,
                        'note' => $finalSize <= self::TARGET_BYTES
                            ? null
                            : 'Image reduced as far as possible but still exceeds ~100 KB.',
                    ];
                }

                return $this->passthrough($file, $originalSize, 'Image could not be re-encoded; stored original.');
            }

            if ($isPdf) {
                $compressed = $this->compressPdf($file);
                if ($compressed !== null) {
                    $finalSize = (int) filesize($compressed->getRealPath());

                    return [
                        'file' => $compressed,
                        'compressed' => true,
                        'originalSize' => $originalSize,
                        'finalSize' => $finalSize,
                        'note' => $finalSize <= self::TARGET_BYTES
                            ? null
                            : 'PDF reduced as far as possible but still exceeds ~100 KB (install Ghostscript for stronger compression).',
                    ];
                }

                return $this->passthrough(
                    $file,
                    $originalSize,
                    'PDF compression needs Ghostscript on the server; stored original.'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Medical document compression failed; storing original.', [
                'error' => $e->getMessage(),
                'mime' => $mime,
                'ext' => $ext,
            ]);
        }

        // Office docs / txt / dicom: no safe lossy path to 100 KB — store as-is.
        return $this->passthrough($file, $originalSize, null);
    }

    /** @return array{file: UploadedFile, compressed: bool, originalSize: int, finalSize: int, note: ?string} */
    private function passthrough(UploadedFile $file, int $originalSize, ?string $note): array
    {
        return [
            'file' => $file,
            'compressed' => false,
            'originalSize' => $originalSize,
            'finalSize' => $originalSize,
            'note' => $note,
        ];
    }

    // ------------------------------------------------------------------
    // Images (GD → JPEG)
    // ------------------------------------------------------------------

    private function compressImage(UploadedFile $file): ?UploadedFile
    {
        $realPath = $file->getRealPath();
        $info = @getimagesize($realPath);
        if ($info === false) {
            return null;
        }

        [$width, $height, $type] = $info;
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG => @imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($realPath),
            IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($realPath) : false,
            default => false,
        };

        if ($src === false || $src === null) {
            return null;
        }

        // Flatten onto white so JPEG output has no black/transparent artifacts.
        $flat = $this->flattenOnWhite($src, $width, $height);
        if ($flat !== $src) {
            imagedestroy($src);
            $src = $flat;
        }

        $best = null; // [data, w, h, quality]

        // Iteratively shrink dimensions, trying qualities 82 → 35 at each step.
        $w = min($width, self::MAX_IMAGE_DIMENSION);
        $h = (int) round($height * ($w / $width));

        for ($step = 0; $step < 8; $step++) {
            $resized = $this->resample($src, $width, $height, $w, $h);

            foreach ([82, 75, 68, 60, 52, 45, 38, 32] as $quality) {
                ob_start();
                $ok = @imagejpeg($resized, null, $quality);
                $data = ob_get_clean();
                if (! $ok || ! is_string($data) || $data === '') {
                    continue;
                }
                $best = [$data, $w, $h, $quality];
                if (strlen($data) <= self::TARGET_BYTES) {
                    break 2;
                }
            }

            imagedestroy($resized);

            // Target missed: shrink ~20% and retry. Stop at tiny thumbnails.
            $w = (int) round($w * 0.8);
            $h = (int) round($h * 0.8);
            if ($w < 320 || $h < 320) {
                break;
            }
        }

        imagedestroy($src);

        if ($best === null) {
            return null;
        }

        [$data] = $best;

        $tmp = tempnam(sys_get_temp_dir(), 'meddoc_');
        if ($tmp === false) {
            return null;
        }
        @unlink($tmp);
        $compressedPath = $tmp . '.jpg';
        if (@file_put_contents($compressedPath, $data) === false) {
            return null;
        }

        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return new UploadedFile(
            $compressedPath,
            $base . '.jpg',
            'image/jpeg',
            filesize($compressedPath),
            UPLOAD_ERR_OK,
            true
        );
    }

    private function flattenOnWhite($src, int $w, int $h)
    {
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

        return $dst;
    }

    private function resample($src, int $srcW, int $srcH, int $dstW, int $dstH)
    {
        if ($dstW === $srcW && $dstH === $srcH) {
            // Still return an independent copy so callers can always destroy it.
            $copy = imagecreatetruecolor($dstW, $dstH);
            $white = imagecolorallocate($copy, 255, 255, 255);
            imagefill($copy, 0, 0, $white);
            imagecopy($copy, $src, 0, 0, 0, 0, $srcW, $srcH);

            return $copy;
        }

        $dst = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        return $dst;
    }

    // ------------------------------------------------------------------
    // PDFs (Ghostscript)
    // ------------------------------------------------------------------

    private function compressPdf(UploadedFile $file): ?UploadedFile
    {
        $gs = $this->findGhostscript();
        if ($gs === null) {
            Log::warning('Ghostscript not found; PDF stored without compression.');

            return null;
        }

        $input = $file->getRealPath();

        // Progressively stronger presets until the target is met.
        $attempts = [
            ['preset' => '/ebook', 'extra' => []],
            ['preset' => '/screen', 'extra' => []],
            ['preset' => '/screen', 'extra' => [
                '-dDownsampleColorImages=true', '-dColorImageResolution=72',
                '-dDownsampleGrayImages=true', '-dGrayImageResolution=72',
                '-dDownsampleMonoImages=true', '-dMonoImageResolution=72',
            ]],
        ];

        $bestPath = null;
        $bestSize = PHP_INT_MAX;

        foreach ($attempts as $attempt) {
            $tmp = tempnam(sys_get_temp_dir(), 'medpdf_');
            if ($tmp === false) {
                continue;
            }
            @unlink($tmp);
            $out = $tmp . '.pdf';

            $cmd = array_merge(
                [$gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
                    '-dPDFSETTINGS=' . $attempt['preset'],
                    '-dNOPAUSE', '-dQUIET', '-dBATCH',
                    '-sOutputFile=' . $out, $input],
                $attempt['extra']
            );
            $escaped = implode(' ', array_map('escapeshellarg', $cmd));

            @exec($escaped . ' 2>&1', $output, $code);

            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                $size = filesize($out);
                if ($size < $bestSize) {
                    if ($bestPath !== null) {
                        @unlink($bestPath);
                    }
                    $bestPath = $out;
                    $bestSize = $size;
                } else {
                    @unlink($out);
                }

                if ($size <= self::TARGET_BYTES) {
                    break;
                }
            } else {
                @unlink($out);
            }
        }

        if ($bestPath === null) {
            return null;
        }

        return new UploadedFile(
            $bestPath,
            $file->getClientOriginalName(),
            'application/pdf',
            filesize($bestPath),
            UPLOAD_ERR_OK,
            true
        );
    }

    private function findGhostscript(): ?string
    {
        $candidates = ['gswin64c', 'gswin32c', 'gs'];

        foreach ($candidates as $bin) {
            $which = (DIRECTORY_SEPARATOR === '\\' ? 'where' : 'command -v') . ' ' . escapeshellarg($bin) . ' 2>NUL';
            $out = [];
            $code = 1;
            @exec($which, $out, $code);
            if ($code === 0 && ! empty($out)) {
                $path = trim((string) $out[0]);
                if ($path !== '' && is_file($path)) {
                    return $path;
                }
                // `where` may echo the bare name when on PATH.
                return $bin;
            }
        }

        // Common Windows install locations.
        foreach (glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe') ?: [] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        foreach (glob('C:\\Program Files (x86)\\gs\\gs*\\bin\\gswin32c.exe') ?: [] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
