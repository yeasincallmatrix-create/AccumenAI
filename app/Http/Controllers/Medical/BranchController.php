<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\BranchRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InstituteUser;
use App\Models\Medical\Doctor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Phase 18.1 — minimal branch administration + doctor assignment.
 *
 * Institute administrators only (medical_branches.view/manage; owners
 * bypass via the standard permission check). No hard-delete path:
 * lifecycle is active/inactive, and historical clinical rows keep their
 * branch_id untouched by deactivation. Doctor assignment reuses the
 * existing doctor_branch pivot — no duplicate identities, no new table.
 */
class BranchController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_branches.view', only: ['index', 'show']),
            new Middleware('permission:medical_branches.manage', only: [
                'create', 'store', 'edit', 'update', 'toggleStatus',
                'assignDoctor', 'removeDoctor',
            ]),
        ];
    }

    public function index()
    {
        $instituteId = $this->instituteId();
        $branches = Branch::where('institute_id', $instituteId)
            ->orderByDesc('is_principal')
            ->orderBy('name')
            ->paginate(20);

        return view('medical.branches.index', compact('branches'));
    }

    public function create()
    {
        $managers = InstituteUser::where('institute_id', $this->instituteId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('medical.branches.create', compact('managers'));
    }

    public function store(BranchRequest $request)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();

        if (! empty($data['manager_user_id'])) {
            $manager = InstituteUser::where('id', $data['manager_user_id'])->first();
            if (! $manager || (int) $manager->institute_id !== (int) $instituteId) {
                return redirect()->back()
                    ->with('error', 'The selected manager does not belong to this institute.')
                    ->withInput();
            }
        }

        $branch = Branch::create(array_merge($data, [
            'institute_id' => $instituteId,
            'status' => $data['status'] ?? 'active',
        ]));
        $this->auditBranch($branch->id, 'branch_created', null, ['name' => $branch->name]);

        return redirect()->route('medical.branches.show', $branch)
            ->with('status', 'Branch created successfully!');
    }

    public function show(Branch $branch)
    {
        $this->ensureSameInstitute($branch, 'branch');

        $branch->load(['manager']);
        $assignments = DB::table('doctor_branch')
            ->where('doctor_branch.institute_id', $branch->institute_id)
            ->where('doctor_branch.branch_id', $branch->id)
            ->join('medical_doctors', 'medical_doctors.id', '=', 'doctor_branch.doctor_id')
            ->leftJoin('users', 'users.id', '=', 'medical_doctors.user_id')
            ->select('doctor_branch.doctor_id', 'doctor_branch.is_active', 'users.name as doctor_name',
                'medical_doctors.registration_number')
            ->orderBy('users.name')
            ->get();
        $doctors = Doctor::where('institute_id', $branch->institute_id)
            ->with('user')
            ->orderBy('id')
            ->get();
        $usage = $this->branchUsage($branch);

        return view('medical.branches.show', compact('branch', 'assignments', 'doctors', 'usage'));
    }

    public function edit(Branch $branch)
    {
        $this->ensureSameInstitute($branch, 'branch');
        $managers = InstituteUser::where('institute_id', $branch->institute_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('medical.branches.edit', compact('branch', 'managers'));
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $this->ensureSameInstitute($branch, 'branch');
        $data = $request->validated();

        if (! empty($data['manager_user_id'])) {
            $manager = InstituteUser::where('id', $data['manager_user_id'])->first();
            if (! $manager || (int) $manager->institute_id !== (int) $branch->institute_id) {
                return redirect()->back()
                    ->with('error', 'The selected manager does not belong to this institute.')
                    ->withInput();
            }
        }

        // Institute ownership never moves between institutes.
        unset($data['institute_id']);
        $old = ['name' => $branch->name, 'status' => $branch->status];
        $branch->update($data);
        $this->auditBranch($branch->id, 'branch_updated', $old, [
            'name' => $branch->name, 'status' => $branch->status,
        ]);

        return redirect()->route('medical.branches.show', $branch)
            ->with('status', 'Branch updated successfully!');
    }

    /**
     * Activate/deactivate. Lifecycle-safe by design: clinical rows keep
     * their branch_id (nothing is nullified or deleted); inactive
     * branches only stop accepting NEW clinical records.
     */
    public function toggleStatus(Branch $branch)
    {
        $this->ensureSameInstitute($branch, 'branch');

        $old = $branch->status;
        $branch->update(['status' => $old === 'active' ? 'inactive' : 'active']);
        $this->auditBranch($branch->id, 'branch_status_changed',
            ['status' => $old], ['status' => $branch->status]);

        return redirect()->back()->with('status', 'Branch status updated.');
    }

    public function assignDoctor(Request $request, Branch $branch)
    {
        $this->ensureSameInstitute($branch, 'branch');

        $request->validate(['doctor_id' => ['required', 'integer']]);
        $doctor = Doctor::where('institute_id', $branch->institute_id)
            ->find($request->doctor_id);
        if (! $doctor) {
            return redirect()->back()
                ->with('error', 'The selected doctor does not belong to this institute.')
                ->withInput();
        }

        $exists = DB::table('doctor_branch')
            ->where('branch_id', $branch->id)
            ->where('doctor_id', $doctor->id)
            ->exists();
        if ($exists) {
            return redirect()->back()->with('error', 'This doctor is already assigned to the branch.');
        }

        DB::table('doctor_branch')->insert([
            'institute_id' => $branch->institute_id,
            'branch_id' => $branch->id,
            'doctor_id' => $doctor->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->auditBranch($branch->id, 'doctor_assigned',
            null, ['doctor_id' => $doctor->id]);

        return redirect()->back()->with('status', 'Doctor assigned to branch.');
    }

    public function removeDoctor(Branch $branch, int $doctor)
    {
        $this->ensureSameInstitute($branch, 'branch');

        $deleted = DB::table('doctor_branch')
            ->where('institute_id', $branch->institute_id)
            ->where('branch_id', $branch->id)
            ->where('doctor_id', $doctor)
            ->delete();
        if (! $deleted) {
            return redirect()->back()->with('error', 'Assignment not found.');
        }
        // Removing the last assignment restores legacy institute-wide
        // semantics for that doctor (documented, never a lockout).
        $this->auditBranch($branch->id, 'doctor_unassigned',
            ['doctor_id' => $doctor], null);

        return redirect()->back()->with('status', 'Doctor removed from branch.');
    }

    /**
     * Clinical usage counts for the lifecycle-safety display. Bounded
     * counts only — no record loading.
     */
    private function branchUsage(Branch $branch): array
    {
        $instituteId = $branch->institute_id;
        $usage = [];
        foreach ([
            'appointments' => \App\Models\Medical\Appointment::class,
            'encounters' => \App\Models\Medical\Encounter::class,
            'admissions' => \App\Models\Medical\Admission::class,
            'prescriptions' => \App\Models\Medical\Prescription::class,
            'lab orders' => \App\Models\Medical\LabOrder::class,
            'invoices' => \App\Models\Medical\Invoice::class,
        ] as $label => $model) {
            $usage[$label] = $model::where('institute_id', $instituteId)
                ->where('branch_id', $branch->id)
                ->count();
        }

        return $usage;
    }

    private function auditBranch(int $branchId, string $action, ?array $old, ?array $new): void
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();
        try {
            AuditLog::create([
                'institute_id' => $this->instituteId(),
                'user_type' => 'institute_user',
                'user_id' => $staff?->getKey(),
                'action' => $action,
                'module' => 'hms_branches',
                'record_id' => $branchId,
                'old_values' => $old !== null ? json_encode($old) : null,
                'new_values' => $new !== null ? json_encode($new) : null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Admin audit must never break branch administration itself.
        }
    }
}
