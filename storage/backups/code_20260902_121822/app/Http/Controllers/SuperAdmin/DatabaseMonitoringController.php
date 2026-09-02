<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\System\DatabaseMonitoringService;
use Illuminate\Http\Request;

class DatabaseMonitoringController extends Controller
{
    public function index(Request $request, DatabaseMonitoringService $monitoring)
    {
        $data = $monitoring->snapshot(useCache: true);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($data);
        }

        return view('super-admin.database.monitoring', [
            'monitoring' => $data,
        ]);
    }

    public function refresh(Request $request, DatabaseMonitoringService $monitoring)
    {
        $data = $monitoring->snapshot(useCache: false);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($data);
        }

        return redirect()->route('super-admin.database.monitoring')
            ->with('status', 'Monitoring data refreshed at '.now()->toDateTimeString());
    }
}
