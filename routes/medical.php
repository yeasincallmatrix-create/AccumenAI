<?php

use App\Http\Controllers\Medical\AdmissionController;
use App\Http\Controllers\Medical\AppointmentController;
use App\Http\Controllers\Medical\BedController;
use App\Http\Controllers\Medical\BillingController;
use App\Http\Controllers\Medical\CategoryController;
use App\Http\Controllers\Medical\DepartmentController;
use App\Http\Controllers\Medical\DoctorController;
use App\Http\Controllers\Medical\InvoiceController;
use App\Http\Controllers\Medical\LabController;
use App\Http\Controllers\Medical\LabOrderController;
use App\Http\Controllers\Medical\LabTestController;
use App\Http\Controllers\Medical\MedicineController;
use App\Http\Controllers\Medical\PatientController;
use App\Http\Controllers\Medical\PharmacyController;
use App\Http\Controllers\Medical\PharmacyStockController;
use App\Http\Controllers\Medical\PrescriptionController;
use App\Http\Controllers\Medical\ReportController;
use App\Http\Controllers\Medical\TpaClaimController;
use App\Http\Controllers\Medical\TpaController;
use App\Http\Controllers\Medical\VitalSignController;
use App\Http\Controllers\Medical\WardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phase 0 — HMS Foundation: Medical module routes (controllers land in Phase 1)
|--------------------------------------------------------------------------
|
| Controller classes referenced below do not exist yet. This is safe:
| `::class` on a missing class is just a string, resolved only on dispatch.
| The `medical` middleware gates every route to medical-domain institutes.
|
*/

Route::middleware(['auth:institute_user,web', 'tenant', 'medical'])->prefix('medical')->name('medical.')->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return view('medical.dashboard');
    })->name('dashboard');

    // React-powered pages — Blade hosts that mount React components
    // (resources/js/medical/*.js). The Blade + Livewire pages are untouched;
    // React renders only here. JSON feeds are polled every 10 seconds.
    // NOTE: registered before the resources below so `patients-react` etc.
    // are never swallowed by the {patient} / {prescription} wildcards.
    Route::get('queue-react', [AppointmentController::class, 'reactIndex'])->name('queue.react');
    Route::get('queue-react/data', [AppointmentController::class, 'reactQueueData'])->name('queue.react.data');
    Route::get('patients-react', [PatientController::class, 'reactIndex'])->name('patients.react');
    Route::get('patients-react/data', [PatientController::class, 'reactData'])->name('patients.react.data');
    Route::get('prescriptions-react', [PrescriptionController::class, 'reactIndex'])->name('prescriptions.react');
    Route::get('prescriptions-react/data', [PrescriptionController::class, 'reactData'])->name('prescriptions.react.data');

    // Patients — explicit lookup before the resource so it is not
    // swallowed by the {patient} wildcard.
    Route::get('patients/lookup', [PatientController::class, 'lookup'])->name('patients.lookup');
    Route::post('patients/quick-store', [PatientController::class, 'quickStore'])->name('patients.quick-store');
    Route::resource('patients', PatientController::class);
    Route::get('patients/{patient}/history', [PatientController::class, 'history'])->name('patients.history');

    // Appointments (OPD) — the React list feed sits before the resource
    // so it is never swallowed by the {appointment} wildcard.
    Route::get('appointments-react/data', [AppointmentController::class, 'reactAppointmentsData'])->name('appointments.react.data');
    Route::get('appointments/queue/{doctor?}', [AppointmentController::class, 'queue'])->name('appointments.queue');
    Route::resource('appointments', AppointmentController::class);
    Route::post('appointments/{appointment}/checkin', [AppointmentController::class, 'checkin'])->name('appointments.checkin');
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('appointments/{appointment}/transfer', [AppointmentController::class, 'transfer'])->name('appointments.transfer');
    Route::post('appointments/{appointment}/collect-fee', [AppointmentController::class, 'collectFee'])->name('appointments.collect-fee');

    // Admissions (IPD) — explicit GETs before the resource so they are not
    // swallowed by the {admission} wildcard.
    Route::get('admissions/current', [AdmissionController::class, 'current'])->name('admissions.current');
    Route::resource('admissions', AdmissionController::class);
    Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->name('admissions.discharge');

    // Wards & Beds — explicit GETs before the resource for the same reason.
    Route::get('beds/available', [BedController::class, 'available'])->name('beds.available');
    Route::resource('wards', WardController::class);
    Route::resource('beds', BedController::class);
    Route::post('beds/{bed}/allocate', [BedController::class, 'allocate'])->name('beds.allocate');

    // Prescriptions
    Route::resource('prescriptions', PrescriptionController::class);
    Route::post('prescriptions/{prescription}/finalize', [PrescriptionController::class, 'finalize'])->name('prescriptions.finalize');
    Route::get('prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescriptions.print');
    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'downloadPdf'])->name('prescriptions.pdf');

    // Pharmacy
    Route::get('pharmacy/expiry-alerts', [PharmacyController::class, 'expiryAlerts'])->name('pharmacy.expiry-alerts');
    Route::post('pharmacy/dispense/{prescription_item}', [PharmacyController::class, 'dispense'])->name('pharmacy.dispense');
    Route::resource('pharmacy/medicines', MedicineController::class)->names('pharmacy.medicines');
    Route::resource('pharmacy/stock', PharmacyStockController::class)->names('pharmacy.stock');

    // Lab
    Route::resource('lab/tests', LabTestController::class)->names('lab.tests');
    Route::resource('lab/orders', LabOrderController::class)->names('lab.orders');
    Route::post('lab/orders/{order}/collect', [LabOrderController::class, 'collect'])->name('lab.orders.collect');
    Route::post('lab/orders/{order}/result', [LabOrderController::class, 'enterResult'])->name('lab.orders.result');
    Route::get('lab/orders/{order}/report', [LabOrderController::class, 'report'])->name('lab.orders.report');

    // Billing
    Route::resource('billing/invoices', InvoiceController::class)->names('billing.invoices');
    Route::post('billing/invoices/{invoice}/payment', [InvoiceController::class, 'payment'])->name('billing.invoices.payment');
    Route::get('billing/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('billing.invoices.print');

    // TPA (Insurance)
    Route::resource('tpa/claims', TpaClaimController::class)->names('tpa.claims');
    Route::post('tpa/claims/{claim}/approve', [TpaClaimController::class, 'approve'])->name('tpa.claims.approve');
    Route::post('tpa/claims/{claim}/reject', [TpaClaimController::class, 'reject'])->name('tpa.claims.reject');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('daily', [ReportController::class, 'daily'])->name('daily');
        Route::get('monthly', [ReportController::class, 'monthly'])->name('monthly');
        Route::get('revenue', [ReportController::class, 'revenue'])->name('revenue');
        Route::get('clinical', [ReportController::class, 'clinical'])->name('clinical');
        Route::get('pharmacy', [ReportController::class, 'pharmacy'])->name('pharmacy');
        Route::get('lab', [ReportController::class, 'lab'])->name('lab');
        Route::get('tpa', [ReportController::class, 'tpa'])->name('tpa');
        Route::get('regulatory', [ReportController::class, 'regulatory'])->name('regulatory');
    });

    // Backwards-compat aliases for the aggregate controllers named in the
    // original Phase 0 spec (Billing/Lab/Pharmacy/TPA). They resolve to the
    // same URIs once Phase 1 implements them; harmless string references now.
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('lab', [LabController::class, 'index'])->name('lab.index');
    Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
    Route::get('tpa', [TpaController::class, 'index'])->name('tpa.index');

    /*
    |--------------------------------------------------------------------------
    | Phase 2 — IPD extras (appended additively; Phase 0/1 lines untouched)
    |--------------------------------------------------------------------------
    |
    | The Phase 0 resource block covers CRUD. These routes wire the actions
    | the Phase 2 controllers expose that have no resource equivalent:
    * discharge form (GET) next to the Phase 0 discharge action (POST),
    * bed transfer, bed release, discharge-summary PDF, and vitals/notes.
    */

    // Bed release (mirrors the Phase 0 allocate route).
    Route::post('beds/{bed}/release', [BedController::class, 'release'])->name('beds.release');

    // Discharge form (GET) + discharge action (POST, defined in Phase 0).
    Route::get('admissions/{admission}/discharge', [AdmissionController::class, 'dischargeForm'])
        ->name('admissions.discharge.form');
    // Bed transfer between two occupied/available beds.
    Route::get('admissions/{admission}/transfer', [AdmissionController::class, 'transferForm'])
        ->name('admissions.transfer.form');
    Route::post('admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])
        ->name('admissions.transfer');
    // Discharge summary PDF (discharged admissions only).
    Route::get('admissions/{admission}/discharge-summary', [AdmissionController::class, 'dischargeSummary'])
        ->name('admissions.discharge-summary');

    // Vitals & nursing notes. Explicit routes (ordered create → show) so
    // `vitals/create` is never swallowed by the {vital} wildcard.
    Route::get('vitals/create', [VitalSignController::class, 'create'])->name('vitals.create');
    Route::post('vitals', [VitalSignController::class, 'store'])->name('vitals.store');
    Route::get('vitals', [VitalSignController::class, 'index'])->name('vitals.index');
    Route::get('vitals/{vital}', [VitalSignController::class, 'show'])->name('vitals.show');
    Route::delete('vitals/{vital}', [VitalSignController::class, 'destroy'])->name('vitals.destroy');
    Route::post('admissions/{admission}/notes', [VitalSignController::class, 'storeNote'])
        ->name('admissions.notes.store');
    Route::delete('notes/{note}', [VitalSignController::class, 'destroyNote'])
        ->name('notes.destroy');

    /*
    |--------------------------------------------------------------------------
    | Phase 3 — Pharmacy extras (appended additively; earlier lines untouched)
    |--------------------------------------------------------------------------
    |
    | Dispensing lives on PharmacyController (the Phase 0 route block already
    | points pharmacy/dispense/* at it). These routes add the dispense queue,
    | the per-item dispense screen, batch dispensing, stock adjustment and
    * draft prescription item management.
    */

    // Dispense queue + per-item screen (the POST dispense action itself is
    // defined in the Phase 0 block above).
    Route::get('pharmacy/dispense', [PharmacyController::class, 'dispenseQueue'])
        ->name('pharmacy.dispense.index');
    Route::get('pharmacy/dispense/{prescription_item}', [PharmacyController::class, 'dispenseShow'])
        ->name('pharmacy.dispense.show');
    Route::post('pharmacy/batch-dispense/{prescription}', [PharmacyController::class, 'batchDispense'])
        ->name('pharmacy.dispense.batch');

    // Stock adjustment (physical count / damage / write-off).
    Route::post('pharmacy/stock/{stock}/adjust', [PharmacyStockController::class, 'adjust'])
        ->name('pharmacy.stock.adjust');

    // Draft prescription item management.
    Route::post('prescriptions/{prescription}/items', [PrescriptionController::class, 'addItem'])
        ->name('prescriptions.items.store');
    Route::delete('prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'removeItem'])
        ->name('prescriptions.items.destroy');

    /*
    |--------------------------------------------------------------------------
    | Phase 4 — Lab/Billing extras (appended additively; earlier lines untouched)
    |--------------------------------------------------------------------------
    |
    | Payment history, lab result-entry form (GET next to the Phase 0 result
    * POST) and TPA settlement have no resource equivalent, so they are
    * registered explicitly here.
    */

    // Payment history (derived from invoice rows; no payments table exists).
    Route::get('billing/payments', [InvoiceController::class, 'payments'])
        ->name('billing.payments.index');

    // Lab result-entry form (GET) + entry action (POST, defined in Phase 0).
    Route::get('lab/orders/{order}/result', [LabOrderController::class, 'resultForm'])
        ->name('lab.orders.result.form');

    // TPA settlement (approve/reject POSTs are defined in Phase 0).
    Route::post('tpa/claims/{claim}/settle', [TpaClaimController::class, 'settle'])
        ->name('tpa.claims.settle');

    /*
    |--------------------------------------------------------------------------
    | Doctor Management — Department → Specialty → Doctor + weekly availability
    |--------------------------------------------------------------------------
    */
    Route::get('departments/{department}/specialties', [DepartmentController::class, 'getSpecialties'])->name('departments.specialties');
    Route::post('categories/store', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/departments', [CategoryController::class, 'departments'])->name('categories.departments');
    Route::get('doctors/{doctor}/slots', [DoctorController::class, 'getSlots'])->name('doctors.slots');
    Route::post('doctors/quick-user', [DoctorController::class, 'quickUser'])->name('doctors.quick-user');
    Route::resource('doctors', DoctorController::class);
});
