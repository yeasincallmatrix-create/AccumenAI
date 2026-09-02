<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformAuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = PlatformAuditLog::query()->with('admin')->orderByDesc('created_at');

        if ($request->filled('section')) {
            $query->where('section', $request->input('section'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->input('admin_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $logs = $query->paginate(20)->withQueryString();
        $sections = PlatformAuditLog::query()->distinct()->pluck('section');
        $actions = PlatformAuditLog::query()->distinct()->pluck('action');
        $admins = PlatformAdmin::query()->select('id','email','name')->get();

        return view('admin.platform-audit.index', [
            'logs' => $logs,
            'sections' => $sections,
            'actions' => $actions,
            'admins' => $admins,
        ]);
    }
}
