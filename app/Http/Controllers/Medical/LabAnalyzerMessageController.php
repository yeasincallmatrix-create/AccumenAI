<?php

namespace App\Http\Controllers\Medical;

use App\Jobs\ProcessAnalyzerMessage;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;
use Illuminate\Http\Request;

class LabAnalyzerMessageController extends MedicalController
{
    protected function analyzer(LabAnalyzer $analyzer): LabAnalyzer
    {
        $this->ensureSameInstitute($analyzer);

        return $analyzer;
    }

    public function index(Request $request, LabAnalyzer $analyzer)
    {
        $this->analyzer($analyzer);
        $query = $analyzer->messages()->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('message_id')) {
            $query->where('message_id', 'like', '%'.$request->input('message_id').'%');
        }
        if ($request->filled('accession_number')) {
            $query->where('accession_number', 'like', '%'.$request->input('accession_number').'%');
        }
        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->input('to_date'));
        }

        $messages = $query->paginate(20)->withQueryString();

        return view('medical.lab.analyzers.messages.index', compact('analyzer', 'messages'));
    }

    public function show(LabAnalyzer $analyzer, LabMessage $message)
    {
        $this->analyzer($analyzer);
        if ((int) $message->analyzer_id !== (int) $analyzer->id || (int) $message->institute_id !== $this->instituteId()) {
            abort(404);
        }

        $linkedResults = $message->lab_order_id
            ? \App\Models\Medical\LabResult::where('lab_order_id', $message->lab_order_id)->with('labTest')->get()
            : collect();

        return view('medical.lab.analyzers.messages.show', compact('analyzer', 'message', 'linkedResults'));
    }

    public function retry(LabAnalyzer $analyzer, LabMessage $message)
    {
        $this->analyzer($analyzer);
        if ((int) $message->analyzer_id !== (int) $analyzer->id || (int) $message->institute_id !== $this->instituteId()) {
            abort(404);
        }

        if (! in_array($message->status, ['error', 'dead', 'received', 'parsed'], true)) {
            return back()->with('warning', 'Only failed or pending messages can be retried.');
        }

        $message->update([
            'status' => 'received',
            'attempts' => 0,
            'error_code' => null,
            'error_message' => null,
        ]);
        ProcessAnalyzerMessage::dispatch($message->id, $analyzer->id);

        return back()->with('success', "Message #{$message->id} re-queued for processing.");
    }
}
