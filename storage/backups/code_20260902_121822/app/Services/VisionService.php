<?php

namespace App\Services;

use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Support\AiConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class VisionService
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function extractText(UploadedFile $file, AiContext $context): string
    {
        if (!AiConfig::enabled()) {
            throw new \Exception('AI is disabled. Please enable AI in settings.');
        }

        $provider = AiConfig::provider();
        if (!in_array($provider, ['gemini', 'openai'], true)) {
            throw new \Exception("Provider '{$provider}' does not support vision. Please use Gemini or OpenAI.");
        }

        try {
            $imageData = base64_encode(file_get_contents($file->getPathname()));
            $mimeType = $file->getMimeType() ?: 'image/jpeg';

            $prompt = "Extract all text from this image. Return only the extracted text. Do not include any commentary, formatting, or markdown.";

            $response = $this->aiService->askWithImage($prompt, $imageData, $mimeType, $context);

            if (isset($response['content']) && !empty(trim($response['content']))) {
                return trim($response['content']);
            }

            throw new \Exception('AI Vision returned empty response.');
        } catch (\Exception $e) {
            Log::error('AI Vision extraction failed: ' . $e->getMessage());
            throw new \Exception('AI Vision extraction failed: ' . $e->getMessage());
        }
    }
}
