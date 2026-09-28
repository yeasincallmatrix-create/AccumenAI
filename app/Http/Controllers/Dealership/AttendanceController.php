<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\Attendance;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $rows = Attendance::query()
            ->when($request->filled('sr'), fn ($q) => $q->where('sales_force_id', $request->input('sr')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('attendance_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('attendance_date', '<=', $request->input('to')))
            ->latest('attendance_date')
            ->paginate(20)
            ->withQueryString();

        return view('dealership.attendance.index', [
            'institute' => $institute,
            'rows' => $rows,
            'filters' => $request->only(['sr', 'from', 'to']),
            'statuses' => config('dealership.attendance_statuses', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'sales_force_id' => ['required', 'integer', 'min:1'],
            'attendance_date' => ['required', 'date'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'beat_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:present,absent,half_day,leave'],
            'notes' => ['nullable', 'string'],
        ]);

        $exists = Attendance::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id ?? TenantContext::id())
            ->where('sales_force_id', $data['sales_force_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['attendance_date' => 'Attendance already recorded for this SR and date.'])->withInput();
        }

        Attendance::create([
            'sales_force_id' => $data['sales_force_id'],
            'attendance_date' => $data['attendance_date'],
            'check_in_at' => $data['check_in_at'] ?? null,
            'check_out_at' => $data['check_out_at'] ?? null,
            'beat_id' => $data['beat_id'] ?? null,
            'status' => $data['status'] ?? 'present',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('dealership.attendance.index')->with('success', 'Attendance recorded.');
    }
}
