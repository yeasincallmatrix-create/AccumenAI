<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Extract raw text from an uploaded image/PDF.
     * Tries TesseractOCR if available, else falls back to basic handling.
     */
    public function extractText(UploadedFile $file): string
    {
        $path = $file->getPathname();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        // PDF handling: try pdftotext if available, else treat as image via Tesseract
        if ($extension === 'pdf' || str_contains($mime, 'pdf')) {
            // Try pdftotext (poppler) if binary exists
            $textFromPdf = $this->tryPdfToText($path);
            if (!empty($textFromPdf)) {
                return $textFromPdf;
            }
            // If Tesseract available, it can handle PDF via imagick if needed
        }

        // Try Tesseract OCR if package is installed
        if (class_exists(\thiagoalessio\TesseractOCR\TesseractOCR::class)) {
            try {
                $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($path);
                // 🔧 Fallback: set executable explicitly if PATH is missing (Windows UB-Mannheim default)
                $tesseractPath = config('services.tesseract.path', env('TESSERACT_PATH', 'C:/Program Files/Tesseract-OCR/tesseract.exe'));
                if ($tesseractPath && file_exists($tesseractPath)) {
                    try {
                        $ocr->executable($tesseractPath);
                    } catch (\Throwable $e) {
                        // ignore, will try default PATH
                    }
                }
                // Support English and Bengali, fallback gracefully if language data missing
                try {
                    $ocr->lang('eng', 'ben');
                } catch (\Throwable $e) {
                    $ocr->lang('eng');
                }
                // Optional: improve config for documents
                // $ocr->psm(6);
                return (string) $ocr->run();
            } catch (\Throwable $e) {
                Log::warning('Tesseract OCR failed', ['error' => $e->getMessage()]);
                // Fall through to fallback
            }
        }

        // Fallback: if file is actually text-based, try to read it
        // For images without OCR, return empty string and let parse handle it
        if (in_array($extension, ['txt', 'csv'])) {
            try {
                return (string) file_get_contents($path);
            } catch (\Throwable $e) {
                return '';
            }
        }

        // Last resort: return empty string - caller will handle no data
        // To still support demo without Tesseract, try to extract from image metadata or filename
        return '';
    }

    /**
     * Parse raw OCR text into structured student fields using regex.
     */
    public function parseText(string $text): array
    {
        // Normalize whitespace and line breaks
        $normalized = preg_replace('/\r\n|\r/', "\n", $text);
        $normalized = trim($normalized);

        $data = [
            'first_name' => null,
            'last_name' => null,
            'dob' => null,
            'date_of_birth' => null,
            'email' => null,
            'phone' => null,
            'nid_number' => null,
            'address' => null,
            'blood_group' => null,
            'father_name' => null,
            'mother_name' => null,
            'raw_text' => $text,
        ];

        if (empty($normalized)) {
            return $data;
        }

        // First/last name: "Name: John Doe" — capture until newline, then split
        if (preg_match('/(?:Full\s+)?Name\s*:?\s*([^\n\r]{2,60})/i', $normalized, $m)) {
            $full = trim($m[1]);
            // Remove any trailing label that might be on same line after name
            $full = preg_split('/\s{2,}(?:DOB|Email|Phone|NID|Address|Father|Mother|Blood)/i', $full)[0];
            $full = trim($full);
            // Only allow letters, spaces, dot, hyphen, apostrophe
            if (preg_match('/^([A-Za-z][A-Za-z \.\'-]*)$/', $full)) {
                $parts = preg_split('/\s+/', $full);
                if (count($parts) >= 2) {
                    $data['first_name'] = $parts[0];
                    $data['last_name'] = implode(' ', array_slice($parts, 1));
                } elseif (count($parts) === 1) {
                    $data['first_name'] = $parts[0];
                }
            }
        }

        // Father/mother — until newline
        if (preg_match('/Father(?:\'s)?\s*Name\s*:?\s*([^\n\r]{2,60})/i', $normalized, $m)) {
            $val = trim($m[1]);
            $val = preg_split('/\s{2,}/', $val)[0];
            $data['father_name'] = trim($val);
        }
        if (preg_match('/Mother(?:\'s)?\s*Name\s*:?\s*([^\n\r]{2,60})/i', $normalized, $m)) {
            $val = trim($m[1]);
            $val = preg_split('/\s{2,}/', $val)[0];
            $data['mother_name'] = trim($val);
        }

        // DOB variations
        if (preg_match('/(?:DOB|Date\s*of\s*Birth|Birth\s*Date)\s*:?\s*([\d]{4}[-\/][\d]{1,2}[-\/][\d]{1,2}|[\d]{1,2}[-\/][\d]{1,2}[-\/][\d]{2,4})/i', $normalized, $m)) {
            $rawDob = trim($m[1]);
            $parsed = $this->normalizeDate($rawDob);
            $data['dob'] = $parsed;
            $data['date_of_birth'] = $parsed;
        }

        // Email
        if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $normalized, $m)) {
            $data['email'] = strtolower(trim($m[0]));
        }

        // Phone: Bangladesh format +880 or 01#########
        if (preg_match('/(?:Phone|Mobile|Contact)\s*:?\s*(\+?880[-\s]?1\d{9}|\+?\d{10,15}|01[3-9]\d{8})/i', $normalized, $m)) {
            $data['phone'] = trim($m[1]);
        } elseif (preg_match('/(\+8801\d{9}|01[3-9]\d{8})/', $normalized, $m)) {
            $data['phone'] = trim($m[1]);
        }

        // NID
        if (preg_match('/(?:NID|National\s*ID|NID\s*No)\s*:?\s*(\d{10,17})/i', $normalized, $m)) {
            $data['nid_number'] = trim($m[1]);
        }

        // Blood group
        if (preg_match('/(?:Blood\s*Group|Blood)\s*:?\s*(A\+|A-|B\+|B-|AB\+|AB-|O\+|O-)/i', $normalized, $m)) {
            $data['blood_group'] = strtoupper(trim($m[1]));
        }

        // Address
        if (preg_match('/Address\s*:?\s*([^\n]{5,120})/i', $normalized, $m)) {
            $addr = trim($m[1]);
            // Stop at next label
            $addr = preg_split('/\s+(Phone|Email|NID|DOB|Name|Father|Mother|Blood)/i', $addr)[0];
            $data['address'] = trim($addr);
        }

        // Clean up: trim all
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = trim($v);
                if ($data[$k] === '') $data[$k] = null;
            }
        }

        return $data;
    }

    private function tryPdfToText(string $path): string
    {
        // Check if pdftotext binary exists
        $binary = null;
        foreach (['pdftotext', 'pdftotext.exe'] as $candidate) {
            $which = trim(shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null') ?? '');
            if (!empty($which) && is_file($which)) {
                $binary = $which;
                break;
            }
            // Windows check
            $where = trim(shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul') ?? '');
            if (!empty($where)) {
                $binary = strtok($where, "\r\n");
                if (is_file($binary)) break;
            }
        }

        if ($binary === null) {
            // Try common Windows path for poppler
            foreach (['C:\\poppler\\bin\\pdftotext.exe', 'C:\\xampp\\poppler\\bin\\pdftotext.exe'] as $p) {
                if (is_file($p)) {
                    $binary = $p;
                    break;
                }
            }
        }

        if ($binary === null) {
            return '';
        }

        $tmpTxt = $path . '.txt';
        $cmd = escapeshellarg($binary) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($tmpTxt) . ' -layout 2>&1';
        @shell_exec($cmd);
        if (is_file($tmpTxt)) {
            $content = file_get_contents($tmpTxt);
            @unlink($tmpTxt);
            return $content !== false ? $content : '';
        }

        return '';
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        // Try multiple formats
        $formats = ['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y', 'd-m-y', 'd/m/y', 'Y-m-d H:i:s'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt && $dt->format($fmt) === $raw) {
                return $dt->format('Y-m-d');
            }
        }
        // Try strtotime fallback
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return $raw;
    }
}
