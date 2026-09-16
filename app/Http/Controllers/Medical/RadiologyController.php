<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\RadiologyImage;
use App\Models\Medical\RadiologyOrder;
use App\Services\Medical\NumberSequenceService;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RadiologyController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_radiology.view', only: ['index', 'show', 'dashboard']),
            new Middleware('permission:medical_radiology.create', only: ['create', 'store']),
            new Middleware('permission:medical_radiology.edit', only: ['edit', 'update', 'schedule', 'startPerforming', 'markPerformed']),
            new Middleware('permission:medical_radiology.report', only: ['report']),
            new Middleware('permission:medical_radiology.verify', only: ['verify']),
            new Middleware('permission:medical_radiology.delete', only: ['destroy', 'deleteImage']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
    ) {}

    public function dashboard()
    {
        $instituteId = $this->instituteId();

        $query = RadiologyOrder::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        $active = $query->pending()->with(['patient', 'doctor', 'radiologist'])->orderByDesc('is_urgent')->orderBy('scheduled_at')->get();

        $byStatus = [
            'ordered' => $active->where('status', 'ordered'),
            'scheduled' => $active->where('status', 'scheduled'),
            'in_progress' => $active->where('status', 'in_progress'),
            'completed' => $active->where('status', 'completed'),
        ];

        $todayStats = [
            'total' => RadiologyOrder::where('institute_id', $instituteId)->today()->count(),
            'ordered' => $byStatus['ordered']->count(),
            'scheduled' => $byStatus['scheduled']->count(),
            'in_progress' => $byStatus['in_progress']->count(),
            'completed' => RadiologyOrder::where('institute_id', $instituteId)->where('status', 'completed')->whereDate('performed_at', today())->count(),
            'reported' => RadiologyOrder::where('institute_id', $instituteId)->where('status', 'reported')->whereDate('reported_at', today())->count(),
        ];

        return view('medical.radiology.dashboard', compact('byStatus', 'todayStats'));
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = RadiologyOrder::where('institute_id', $instituteId)
            ->with(['patient', 'doctor', 'radiologist', 'performedBy']);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('modality')) {
            $query->where('modality', $request->modality);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('body_part', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return view('medical.radiology.index', compact('orders'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $orderNumber = $this->sequences->peek(NumberSequence::TYPE_RADIOLOGY, $instituteId);
        $doctors = MedicalScope::instituteDoctors($instituteId);
        $modalities = RadiologyOrder::MODALITIES;
        $bodyParts = RadiologyOrder::BODY_PARTS;

        return view('medical.radiology.create', compact('orderNumber', 'doctors', 'modalities', 'bodyParts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'modality' => 'required|string|in:' . implode(',', array_keys(RadiologyOrder::MODALITIES)),
            'body_part' => 'required|string|max:100',
            'laterality' => 'nullable|string|in:left,right,bilateral',
            'clinical_indication' => 'nullable|string',
            'is_contrast' => 'nullable|boolean',
            'contrast_type' => 'nullable|string|max:100',
            'is_urgent' => 'nullable|boolean',
            'is_fasting_required' => 'nullable|boolean',
            'doctor_id' => 'nullable|exists:users,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'fee' => 'nullable|numeric|min:0',
            'scheduled_at' => 'nullable|date',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $request->branch_id ?? $this->branchContextId();
        $data['order_number'] = $this->sequences->next(NumberSequence::TYPE_RADIOLOGY, $instituteId);
        $data['status'] = $request->scheduled_at ? 'scheduled' : 'ordered';
        $data['fee'] = $data['fee'] ?? 0;

        $order = RadiologyOrder::create($data);

        ClinicalAuditLog::record($order, 'created');

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Radiology order created: ' . $order->order_number);
    }

    public function show(RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');
        $order->load(['patient', 'doctor', 'radiologist', 'performedBy', 'verifiedBy', 'images', 'appointment']);

        return view('medical.radiology.show', ['order' => $order]);
    }

    public function edit(RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');
        $order->load(['patient', 'doctor']);

        $instituteId = $this->instituteId();
        $doctors = MedicalScope::instituteDoctors($instituteId);
        $modalities = RadiologyOrder::MODALITIES;
        $bodyParts = RadiologyOrder::BODY_PARTS;

        return view('medical.radiology.edit', [
            'order' => $order,
            'doctors' => $doctors,
            'modalities' => $modalities,
            'bodyParts' => $bodyParts,
        ]);
    }

    public function update(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $request->validate([
            'modality' => 'required|string|in:' . implode(',', array_keys(RadiologyOrder::MODALITIES)),
            'body_part' => 'required|string|max:100',
            'laterality' => 'nullable|string|in:left,right,bilateral',
            'clinical_indication' => 'nullable|string',
            'is_contrast' => 'nullable|boolean',
            'contrast_type' => 'nullable|string|max:100',
            'is_urgent' => 'nullable|boolean',
            'is_fasting_required' => 'nullable|boolean',
            'doctor_id' => 'nullable|exists:users,id',
            'fee' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:' . implode(',', array_keys(RadiologyOrder::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($order);

        $order->update($request->only([
            'modality', 'body_part', 'laterality', 'clinical_indication',
            'is_contrast', 'contrast_type', 'is_urgent', 'is_fasting_required',
            'doctor_id', 'fee', 'status',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Radiology order updated.');
    }

    public function destroy(RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        ClinicalAuditLog::record($order, 'deleted');

        $order->delete();

        return redirect()
            ->route('medical.radiology.orders.index')
            ->with('status', 'Radiology order deleted.');
    }

    public function schedule(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        $original = ClinicalAuditLog::snapshot($order);

        $order->update([
            'scheduled_at' => $request->scheduled_at,
            'status' => 'scheduled',
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'scheduled', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Order scheduled for ' . $order->scheduled_at->format('d M Y H:i'));
    }

    public function startPerforming(RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $original = ClinicalAuditLog::snapshot($order);

        $order->update([
            'status' => 'in_progress',
            'performed_at' => now(),
            'performed_by' => MedicalScope::recorderId(),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'started', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Study started.');
    }

    public function markPerformed(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $request->validate([
            'technique' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($order);

        $order->update([
            'status' => 'completed',
            'technique' => $request->technique,
            'performed_at' => $order->performed_at ?? now(),
            'performed_by' => $order->performed_by ?? MedicalScope::recorderId(),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'performed', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Technician step completed. Ready for reporting.');
    }

    public function report(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $request->validate([
            'findings' => 'required|string',
            'impression' => 'required|string',
            'recommendations' => 'nullable|string',
            'radiologist_name' => 'nullable|string|max:200',
        ]);

        $original = ClinicalAuditLog::snapshot($order);

        $order->update([
            'status' => 'reported',
            'findings' => $request->findings,
            'impression' => $request->impression,
            'recommendations' => $request->recommendations,
            'radiologist_name' => $request->radiologist_name,
            'radiologist_id' => MedicalScope::recorderId(),
            'reported_at' => now(),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'reported', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Report submitted.');
    }

    public function verify(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $original = ClinicalAuditLog::snapshot($order);

        $order->update([
            'verified_at' => now(),
            'verified_by' => MedicalScope::recorderId(),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'verified', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Report verified.');
    }

    public function uploadImage(Request $request, RadiologyOrder $order)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'caption' => 'nullable|string|max:255',
        ]);

        $file = $request->file('image');
        $path = $file->store('radiology/' . $order->id, 'public');

        $image = RadiologyImage::create([
            'radiology_order_id' => $order->id,
            'file_path' => $path,
            'thumbnail_path' => null,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'caption' => $request->caption,
            'uploaded_by' => MedicalScope::recorderId(),
        ]);

        ClinicalAuditLog::record($order, 'image_uploaded', ['new' => ['image_id' => $image->id]]);

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Image uploaded successfully.');
    }

    public function deleteImage(RadiologyOrder $order, RadiologyImage $image)
    {
        $this->ensureSameInstitute($order, 'radiology_order');
        $this->ensureBranchAccess($order, 'branch_id', 'radiology_order');

        if ((int) $image->radiology_order_id !== (int) $order->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->file_path);
        if ($image->thumbnail_path) {
            Storage::disk('public')->delete($image->thumbnail_path);
        }

        ClinicalAuditLog::record($order, 'image_deleted', ['old' => ['image_id' => $image->id]]);

        $image->delete();

        return redirect()
            ->route('medical.radiology.orders.show', $order)
            ->with('status', 'Image deleted.');
    }
}
