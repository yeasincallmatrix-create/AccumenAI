<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(private BackupService $service) {}

    public function index()
    {
        $tenantId = auth()->user()->institute_id;
        $backups = Backup::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('tenant.backup.index', compact('backups'));
    }

    public function store(Request $request)
    {
        try {
            $backup = $this->service->createBackup(
                auth()->user()->institute_id,
                auth()->id()
            );

            return back()->with('success',
                "Backup completed — " . number_format($backup->size_bytes / 1048576, 2) . " MB");

        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download(int $id)
    {
        $backup = Backup::where('tenant_id', auth()->user()->institute_id)->findOrFail($id);
        $path = storage_path("app/backups/{$backup->filename}");
        abort_unless(file_exists($path), 404);
        return response()->download($path, $backup->filename);
    }
}
