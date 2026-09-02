<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Services\ImageCompressorService;
use App\Services\OcrService;
use App\Services\VisionService;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentScanController extends Controller
{
    public function __construct(
        private readonly OcrService $ocrService,
        private readonly ImageCompressorService $compressor,
    ) {}

    public function scan(Request $request): JsonResponse
    {
        set_time_limit(120);
        // This file size limit applies only to OCR document scanning; it does not affect profile photos or other uploads.
        $maxKb = config('services.ocr.max_file_size', 20480);
        $request->validate([
            'document' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:' . $maxKb],
        ]);

        $file = $request->file('document');

        // Auto-compress if it's an image >2MB — PDFs and small images are left as-is
        $processedFile = $this->compressor->compress($file);

        $tempPath = $processedFile->store('tmp/scans', 'local');

        try {
            // AI ONLY VISION: Tesseract NOT used in this phase
            try {
                $visionService = app(VisionService::class);
                $userTmp = auth()->user();
                $instituteTmp = null;
                try {
                    if ($userTmp && isset($userTmp->institute)) $instituteTmp = $userTmp->institute;
                } catch (\Throwable $e) {}
                if (!$instituteTmp) {
                    try {
                        $tidTmp = \App\Support\TenantContext::id();
                        if ($tidTmp) $instituteTmp = \App\Models\Institute::find($tidTmp);
                    } catch (\Throwable $e) {}
                }
                if (!$instituteTmp && $userTmp) {
                    try {
                        $midTmp = \App\Support\Workspace::membership();
                        if ($midTmp && isset($midTmp->institution)) $instituteTmp = $midTmp->institution;
                    } catch (\Throwable $e) {}
                }
                $visionContext = new AiContext(
                    actor: $userTmp,
                    institute: $instituteTmp,
                    industry: 'training',
                    aiEnabled: true,
                    enabledFeatures: ['assistant']
                );
                $rawText = $visionService->extractText($processedFile, $visionContext);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'AI Vision extraction failed: ' . $e->getMessage(),
                    'data' => [],
                ], 500);
            }

            $parseResult = $this->parseWithAI($rawText);
            $parsed = $parseResult['data'];
            $parserUsed = $parseResult['parser'];

            // Add file info for debugging + compression meta (spec)
            $parsed['_meta'] = [
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'ocr_available' => 'ai-vision',
                'raw_text_preview' => mb_substr($rawText, 0, 500),
                'original_size' => $file->getSize(),
                'compressed_size' => $processedFile->getSize(),
                'compressed' => $processedFile->getSize() < $file->getSize(),
                'parser' => $parserUsed,
                'vision_provider' => AiConfig::provider(),
            ];

            return response()->json([
                'success' => true,
                'data' => $parsed,
                'message' => empty($rawText) ? 'No text extracted. Please ensure the image is clear or install Tesseract OCR.' : 'Document scanned successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Document scan failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process document: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        } finally {
            // Cleanup stored temp file
            if ($tempPath && Storage::disk('local')->exists($tempPath)) {
                try {
                    Storage::disk('local')->delete($tempPath);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            // Clean up compressed temp file if different from original
            if ($processedFile->getPathname() !== $file->getPathname()) {
                @unlink($processedFile->getPathname());
            }
        }
    }

    /**
     * Parse OCR text via AI with regex fallback.
     * Returns ['data' => array, 'parser' => 'ai'|'regex']
     */
    private function parseWithAI(string $rawText): array
    {
        if (!AiConfig::enabled()) {
            return ['data' => $this->ocrService->parseText($rawText), 'parser' => 'regex'];
        }

        if (trim($rawText) === '') {
            return ['data' => $this->ocrService->parseText($rawText), 'parser' => 'regex'];
        }

        $prompt = "Extract student information from the following OCR text. Return ONLY valid JSON with these keys: first_name, last_name, father_name, mother_name, email, phone, date_of_birth (Y-m-d format), gender, nationality, nid_number, blood_group, present_address, permanent_address, guardian_name, guardian_phone. If a field is not found, set it to null. Do not include any other text in the response.\n\nText: " . $rawText;

        try {
            $user = auth()->user();
            $institute = null;
            try {
                if ($user && isset($user->institute)) {
                    $institute = $user->institute;
                }
            } catch (\Throwable $e) {}
            if (!$institute) {
                try {
                    $tid = \App\Support\TenantContext::id();
                    if ($tid) $institute = \App\Models\Institute::find($tid);
                } catch (\Throwable $e) {}
            }
            if (!$institute && $user) {
                try {
                    $mid = \App\Support\Workspace::membership();
                    if ($mid && isset($mid->institution)) $institute = $mid->institution;
                } catch (\Throwable $e) {}
            }

            $context = new AiContext(
                actor: $user,
                institute: $institute,
                industry: 'training',
                aiEnabled: true,
                enabledFeatures: ['assistant']
            );
            $response = app(AiService::class)->ask($prompt, $context);

            if (isset($response['content']) && !empty($response['content'])) {
                $content = trim($response['content']);
                // strip markdown fences if present
                if (str_starts_with($content, '```')) {
                    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
                    $content = preg_replace('/\s*```$/', '', $content);
                }
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    // normalize to expected shape, keep raw_text
                    $data['raw_text'] = $rawText;
                    // map date_of_birth -> dob for form compatibility
                    if (isset($data['date_of_birth']) && !isset($data['dob'])) {
                        $data['dob'] = $data['date_of_birth'];
                    }
                    if (isset($data['dob']) && !isset($data['date_of_birth'])) {
                        $data['date_of_birth'] = $data['dob'];
                    }
                    return ['data' => $data, 'parser' => 'ai'];
                }
                // try to extract JSON object from content if wrapped
                if (preg_match('/\{.*\}/s', $content, $m)) {
                    $data = json_decode($m[0], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        $data['raw_text'] = $rawText;
                        if (isset($data['date_of_birth']) && !isset($data['dob'])) $data['dob'] = $data['date_of_birth'];
                        if (isset($data['dob']) && !isset($data['date_of_birth'])) $data['date_of_birth'] = $data['dob'];
                        return ['data' => $data, 'parser' => 'ai'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AI parsing failed, falling back to regex', ['error' => $e->getMessage()]);
        }

        return ['data' => $this->ocrService->parseText($rawText), 'parser' => 'regex'];
    }
}
