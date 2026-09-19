<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAnalyzerMessage;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use App\Models\LabIntegration\LabWorklist;
use App\Services\LabIntegration\MessageHasher;
use Illuminate\Http\Request;

class LabGatewayController extends Controller
{
    /**
     * POST /api/lab-gateway/results
     * Body: { message_id?: string, payload: string }
     */
    public function receiveResult(Request $request)
    {
        $request->validate([
            'payload' => 'required|string|max:1048576', // 1MB
            'message_id' => 'nullable|string|max:100',
        ]);

        /** @var LabAnalyzer $analyzer */
        $analyzer = $request->attributes->get('lab_analyzer');
        $payload = $request->input('payload');
        $externalMessageId = $request->input('message_id');

        // Idempotency
        $hash = MessageHasher::hash($payload, $analyzer->id);
        $existing = LabMessage::where('institute_id', $analyzer->institute_id)
            ->where('idempotency_hash', $hash)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'duplicate',
                'message_id' => $existing->id,
                'message' => 'Duplicate message — already received.',
            ], 200);
        }

        // Persist raw
        $message = LabMessage::create([
            'institute_id' => $analyzer->institute_id,
            'analyzer_id' => $analyzer->id,
            'direction' => 'inbound',
            'protocol' => $analyzer->protocol,
            'adapter_key' => $analyzer->adapter_key,
            'adapter_version' => $analyzer->adapter_version,
            'message_id' => $externalMessageId,
            'idempotency_hash' => $hash,
            'raw_payload' => $payload,
            'status' => 'received',
            'source_ip' => $request->ip(),
            'source_host' => $request->header('User-Agent'),
            'received_at' => now(),
        ]);

        // Dispatch async
        ProcessAnalyzerMessage::dispatch($message->id, $analyzer->id);

        return response()->json([
            'status' => 'accepted',
            'message_id' => $message->id,
        ], 202);
    }

    /**
     * GET /api/lab-gateway/results/check?message_id=X
     * Phase 6 reconciliation: lets a reconnecting gateway ask whether an
     * already-uploaded (but un-ACKed) external message_id exists server-side.
     */
    public function checkResult(Request $request)
    {
        $request->validate(['message_id' => 'required|string']);
        /** @var LabAnalyzer $analyzer */
        $analyzer = $request->attributes->get('lab_analyzer');

        $exists = LabMessage::where('institute_id', $analyzer->institute_id)
            ->where('message_id', $request->input('message_id'))
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * GET /api/lab-gateway/worklist?analyzer_id=X
     */
    public function worklist(Request $request)
    {
        /** @var LabAnalyzer $analyzer */
        $analyzer = $request->attributes->get('lab_analyzer');

        if (! $analyzer->supports('worklist')) {
            return response()->json([
                'code' => 'WORKLIST_NOT_SUPPORTED',
                'message' => 'This analyzer does not support worklist.',
                'retryable' => false,
            ], 400);
        }

        $worklists = LabWorklist::where('analyzer_id', $analyzer->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(function ($w) {
                $w->update(['status' => 'sent', 'sent_at' => now()]);

                return [
                    'worklist_id' => $w->id,
                    'accession_number' => $w->order_snapshot['accession_number'] ?? null,
                    'tests' => $w->tests_snapshot ?? [],
                ];
            });

        return response()->json([
            'analyzer_id' => $analyzer->id,
            'count' => $worklists->count(),
            'worklists' => $worklists,
        ]);
    }

    /**
     * POST /api/lab-gateway/ack
     * Body: { worklist_id: int }
     */
    public function ack(Request $request)
    {
        $request->validate(['worklist_id' => 'required|integer']);
        /** @var LabAnalyzer $analyzer */
        $analyzer = $request->attributes->get('lab_analyzer');

        $worklist = LabWorklist::where('id', $request->input('worklist_id'))
            ->where('analyzer_id', $analyzer->id)
            ->first();

        if (! $worklist) {
            return response()->json([
                'code' => 'WORKLIST_NOT_FOUND',
                'retryable' => false,
            ], 404);
        }

        $worklist->update(['status' => 'acked', 'acked_at' => now()]);

        return response()->json(['status' => 'acked', 'worklist_id' => $worklist->id]);
    }

    /**
     * POST /api/lab-gateway/health
     * Body: { gateway_version?: string, uptime_seconds?: int, queue_size?: int }
     */
    public function health(Request $request)
    {
        /** @var LabAnalyzer $analyzer */
        $analyzer = $request->attributes->get('lab_analyzer');

        $analyzer->update([
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'analyzer' => [
                'id' => $analyzer->id,
                'code' => $analyzer->code,
                'name' => $analyzer->name,
            ],
        ]);
    }
}
