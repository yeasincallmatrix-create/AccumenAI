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
        if ($request->filled('resolution')) {
            $resolution = $request->input('resolution');
            if ($resolution === 'unresolved') {
                $query->whereIn('status', ['error', 'dead'])->whereNull('resolution_status');
            } else {
                $query->where('resolution_status', $resolution);
            }
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

    public function retry(LabAnalyzer $analyzer, LabMessage $message, \App\Services\LabIntegration\DeadLetterService $service)
    {
        $this->analyzer($analyzer);
        if ((int) $message->analyzer_id !== (int) $analyzer->id || (int) $message->institute_id !== $this->instituteId()) {
            abort(404);
        }

        $ok = $service->retry($message, (int) auth()->id(), request()->input('notes'));

        return back()->with($ok ? 'success' : 'warning', $ok ? "Message #{$message->id} re-queued for processing." : 'Only failed messages can be retried.');
    }

    public function resolveManually(Request $request, LabAnalyzer $analyzer, LabMessage $message, \App\Services\LabIntegration\DeadLetterService $service)
    {
        $this->analyzer($analyzer);
        $this->ensureMessage($analyzer, $message);
        $request->validate(['notes' => 'required|string|min:5|max:500']);
        $service->resolveManually($message, (int) auth()->id(), $request->input('notes'));

        return back()->with('success', 'Message resolved manually.');
    }

    public function discard(Request $request, LabAnalyzer $analyzer, LabMessage $message, \App\Services\LabIntegration\DeadLetterService $service)
    {
        $this->analyzer($analyzer);
        $this->ensureMessage($analyzer, $message);
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        $service->discard($message, (int) auth()->id(), $request->input('reason'));

        return back()->with('success', 'Message discarded.');
    }

    public function escalate(Request $request, LabAnalyzer $analyzer, LabMessage $message, \App\Services\LabIntegration\DeadLetterService $service)
    {
        $this->analyzer($analyzer);
        $this->ensureMessage($analyzer, $message);
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        $service->escalate($message, (int) auth()->id(), $request->input('reason'));

        return back()->with('success', 'Message escalated for pathologist review.');
    }

    protected function ensureMessage(LabAnalyzer $analyzer, LabMessage $message): void
    {
        if ((int) $message->analyzer_id !== (int) $analyzer->id || (int) $message->institute_id !== $this->instituteId()) {
            abort(404);
        }
    }
}
