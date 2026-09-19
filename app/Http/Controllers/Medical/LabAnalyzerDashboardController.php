<?php

namespace App\Http\Controllers\Medical;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabMessage;

class LabAnalyzerDashboardController extends MedicalController
{
    public function index()
    {
        $instituteId = $this->instituteId();
        $analyzers = LabAnalyzer::where('institute_id', $instituteId)->orderByDesc('id')->get();

        $since24h = now()->subDay();
        $cards = $analyzers->map(function (LabAnalyzer $analyzer) use ($since24h) {
            $msgs24h = $analyzer->messages()->where('received_at', '>=', $since24h);
            $failed24h = (clone $msgs24h)->whereIn('status', ['error', 'dead'])->count();

            return [
                'analyzer' => $analyzer,
                'online' => $analyzer->last_seen_at && $analyzer->last_seen_at->gt(now()->subMinutes(15)),
                'messages_24h' => (clone $msgs24h)->count(),
                'failed_24h' => $failed24h,
                'last_failed_id' => $analyzer->messages()->whereIn('status', ['error', 'dead'])->orderByDesc('id')->value('id'),
            ];
        });

        $totals = [
            'analyzers' => $analyzers->count(),
            'online' => $cards->where('online', true)->count(),
            'pending' => LabMessage::where('institute_id', $instituteId)->whereIn('status', ['received', 'parsed'])->count(),
            'failed' => LabMessage::where('institute_id', $instituteId)->whereIn('status', ['error', 'dead'])->count(),
        ];

        return view('medical.lab.analyzers.dashboard', compact('cards', 'totals'));
    }
}
