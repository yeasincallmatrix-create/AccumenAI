<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\VaccineMaster;
use App\Models\Medical\VaccineStock;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VaccineStockController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.vaccination.view', only: ['index']),
            new Middleware('permission:medical.vaccination.stock.manage', only: ['create', 'store', 'edit', 'update']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = VaccineStock::where('institute_id', $instituteId);
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vaccine_master_id')) {
            $query->where('vaccine_master_id', $request->vaccine_master_id);
        }

        $stocks = $query->with(['vaccineMaster'])->orderByDesc('created_at')->paginate(25)->withQueryString();
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();

        return view('medical.vaccination.stocks.index', compact('stocks', 'vaccines'));
    }

    public function create()
    {
        $instituteId = $this->instituteId();
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();

        return view('medical.vaccination.stocks.create', compact('vaccines'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vaccine_master_id' => 'required|exists:vaccine_masters,id',
            'batch_number' => 'required|string|max:50',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'required|date|after:today',
            'quantity_received' => 'required|integer|min:1',
            'storage_location' => 'nullable|string|max:100',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $this->resolveBranchId($request->branch_id);
        $data['quantity_available'] = $data['quantity_received'];
        $data['quantity_used'] = 0;
        $data['status'] = 'available';

        $stock = VaccineStock::create($data);

        ClinicalAuditLog::record($stock, 'created');

        return redirect()
            ->route('medical.vaccination.stocks.index')
            ->with('status', 'Vaccine stock added: ' . $stock->batch_number);
    }

    public function edit(VaccineStock $stock)
    {
        $instituteId = $this->instituteId();
        $vaccines = VaccineMaster::forInstitute($instituteId)->active()->orderBy('name')->get();
        $statuses = VaccineStock::STATUSES;

        return view('medical.vaccination.stocks.edit', [
            'stock' => $stock,
            'vaccines' => $vaccines,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, VaccineStock $stock)
    {
        $request->validate([
            'storage_location' => 'nullable|string|max:100',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric',
            'status' => 'nullable|string|in:' . implode(',', array_keys(VaccineStock::STATUSES)),
        ]);

        $original = ClinicalAuditLog::snapshot($stock);

        $stock->update($request->only([
            'storage_location', 'temperature_min', 'temperature_max', 'status',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($stock->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($stock, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.vaccination.stocks.index')
            ->with('status', 'Vaccine stock updated.');
    }
}
