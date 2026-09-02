<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiService;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        return view('ai.assistant', [
            'institute' => $this->activeInstitute($request->user()),
        ]);
    }

    public function send(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['nullable', 'array', 'max:20'],
        ]);

        $user = $request->user();
        $institute = $this->activeInstitute($user);

        $context = AiContext::resolve($user, $institute);
        $result = $ai->ask($data['message'], $context, $data['history'] ?? []);

        $status = $result['status'] ?? 'error';
        $success = $status === 'ok';
        $content = (string) ($result['content'] ?? '');
        $tools = array_values($result['tools'] ?? []);

        if ($success) {
            $message = $content !== '' ? 'AI response ready.' : 'The AI could not produce an answer.';
        } elseif ($status === 'blocked') {
            $message = $result['error'] ?? 'This request was blocked. Please contact the administrator.';
        } else {
            $message = 'The AI service is temporarily unavailable. Please try again.';
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => [
                'answer' => $content,
                'status' => $status,
                'tools' => $tools,
                'tool_used' => $tools !== [],
                'conversation_id' => null,
            ],
            'errors' => $success ? [] : ($status === 'blocked'
                ? ['ai' => [$result['error'] ?? 'Request blocked.']]
                : ['ai' => ['The AI service is temporarily unavailable.']]),
        ]);
    }

    protected function activeInstitute(InstituteUser|User $user): ?Institute
    {
        $instituteId = $user instanceof InstituteUser
            ? $user->institute_id
            : (TenantContext::id() ?? Workspace::id());

        return $instituteId !== null ? Institute::find($instituteId) : null;
    }
}
