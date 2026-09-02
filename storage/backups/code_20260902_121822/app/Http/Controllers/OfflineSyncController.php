<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectOfflineSyncRequest;
use App\Http\Requests\StoreOfflineSyncRequest;
use App\Models\OfflineSyncQueue;
use App\Models\Student;
use App\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OfflineSyncController extends Controller
{
    public function __construct(private readonly OfflineSyncService $syncService) {}

    /**
     * Review queue: pending records awaiting approval / rejection.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending_review');

        $records = OfflineSyncQueue::query()
            ->with(['creator'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $payloads = $records->getCollection()->map(function (OfflineSyncQueue $queue) {
            $queue->setAttribute('payload_data', (array) $queue->payload);

            return $queue;
        });

        $studentIds = $payloads->pluck('payload_data.student_id')->filter()->unique()->all();
        $students = $studentIds === []
            ? collect()
            : Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');

        $counts = [];
        foreach (['pending_review', 'approved', 'rejected'] as $state) {
            $counts[$state] = OfflineSyncQueue::query()->where('status', $state)->count();
        }

        return view('sync.index', [
            'records' => $records,
            'students' => $students,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Bulk ingestion from the offline client. Idempotent by client_uuid.
     */
    public function store(StoreOfflineSyncRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $records = $request->input('records');

        $existingUuids = OfflineSyncQueue::query()
            ->whereIn('client_uuid', collect($records)->pluck('client_uuid'))
            ->pluck('client_uuid')
            ->all();

        $created = 0;
        $duplicates = 0;

        DB::transaction(function () use ($records, $user, $existingUuids, &$created, &$duplicates) {
            foreach ($records as $record) {
                if (in_array($record['client_uuid'], $existingUuids, true)) {
                    $duplicates++;

                    continue;
                }

                OfflineSyncQueue::create([
                    'client_uuid' => $record['client_uuid'],
                    'entity_type' => $record['entity_type'],
                    'institute_id' => $user->institute_id,
                    'created_by' => $user->id,
                    'payload' => $record['payload'],
                    'created_offline_at' => $record['created_offline_at'],
                ]);

                $created++;
            }
        });

        $message = "Sync complete: {$created} record(s) queued, {$duplicates} duplicate(s) skipped.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'created' => $created, 'duplicates' => $duplicates]);
        }

        return redirect()->route('sync.index', ['status' => 'pending_review'])
            ->with('status', $message);
    }

    public function approve(Request $request, OfflineSyncQueue $queue): RedirectResponse
    {
        try {
            $memo = $this->syncService->materialize($queue, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\LogicException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sync.index', ['status' => 'approved'])
            ->with('status', "Cash memo {$memo->memo_number} approved and synced ({$memo->amount} BDT).");
    }

    public function reject(RejectOfflineSyncRequest $request, OfflineSyncQueue $queue): RedirectResponse
    {
        try {
            $this->syncService->reject($queue, $request->user(), $request->validated()['reject_reason']);
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('sync.index', ['status' => 'rejected'])
            ->with('status', 'Record rejected.');
    }
}
