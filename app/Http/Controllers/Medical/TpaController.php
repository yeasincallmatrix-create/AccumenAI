<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\TpaClaim;
use App\Services\Medical\TpaService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * TPA landing page (route: GET tpa → medical.tpa.index).
 *
 * Claim CRUD + approve/reject/settle live on TpaClaimController, which is
 * what the tpa/claims/* routes point at; this is the dashboard shell.
 */
class TpaController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_tpa.view', only: ['index']),
        ];
    }

    protected TpaService $tpaService;

    public function __construct(TpaService $tpaService)
    {
        $this->tpaService = $tpaService;
    }

    public function index()
    {
        $instituteId = $this->instituteId();

        $stats = $this->tpaService->getClaimStats($instituteId);

        $recentClaims = TpaClaim::where('institute_id', $instituteId)
            ->with(['patient', 'invoice'])
            ->orderBy('claim_date', 'desc')
            ->limit(10)
            ->get();

        return view('medical.tpa.dashboard', compact('stats', 'recentClaims'));
    }
}
