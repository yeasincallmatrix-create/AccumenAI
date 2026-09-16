<?php

use App\Http\Controllers\Medical\AdmissionController;
use App\Http\Controllers\Medical\AppointmentController;
use App\Http\Controllers\Medical\BedController;
use App\Http\Controllers\Medical\BillingController;
use App\Http\Controllers\Medical\BranchController;
use App\Http\Controllers\Medical\CategoryController;
use App\Http\Controllers\Medical\DepartmentController;
use App\Http\Controllers\Medical\DiagnosisController;
use App\Http\Controllers\Medical\DoctorController;
use App\Http\Controllers\Medical\EncounterController;
use App\Http\Controllers\Medical\FollowUpController;
use App\Http\Controllers\Medical\InvoiceController;
use App\Http\Controllers\Medical\LabController;
use App\Http\Controllers\Medical\LabOrderController;
use App\Http\Controllers\Medical\LabTestController;
use App\Http\Controllers\Medical\MedicineController;
use App\Http\Controllers\Medical\PatientController;
use App\Http\Controllers\Medical\PharmacyController;
use App\Http\Controllers\Medical\PharmacyStockController;
use App\Http\Controllers\Medical\PrescriptionController;
use App\Http\Controllers\Medical\ProblemController;
use App\Http\Controllers\Medical\ReportController;
use App\Http\Controllers\Medical\TpaClaimController;
use App\Http\Controllers\Medical\TpaController;
use App\Http\Controllers\Medical\VitalSignController;
use App\Http\Controllers\Medical\WardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Medical module routes — Sub-module grouped hierarchy
|--------------------------------------------------------------------------
|
| EXISTING routes preserved unchanged (same URLs + names) for zero regression.
| NEW grouped routes added under medical/opd/*, medical/ipd/*, etc.
| Old flat URLs ALSO work — no redirects needed since originals stay.
|
*/

Route::middleware(['auth:institute_user,web', 'tenant', 'medical'])->prefix('medical')->name('medical.')->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return view('medical.dashboard');
    })->name('dashboard');

    // React-powered pages
    Route::get('queue-react', [AppointmentController::class, 'reactIndex'])->name('queue.react');
    Route::get('queue-react/data', [AppointmentController::class, 'reactQueueData'])->name('queue.react.data');
    Route::get('patients-react', [PatientController::class, 'reactIndex'])->name('patients.react');
    Route::get('patients-react/data', [PatientController::class, 'reactData'])->name('patients.react.data');
    Route::get('prescriptions-react', [PrescriptionController::class, 'reactIndex'])->name('prescriptions.react');
    Route::get('prescriptions-react/data', [PrescriptionController::class, 'reactData'])->name('prescriptions.react.data');

    // Patients
    Route::get('patients/lookup', [PatientController::class, 'lookup'])->name('patients.lookup');
    Route::post('patients/quick-store', [PatientController::class, 'quickStore'])->name('patients.quick-store');
    Route::post('patients/{patient}/convert', [PatientController::class, 'convert'])->name('patients.convert');
    Route::resource('patients', PatientController::class);
    Route::get('patients/{patient}/history', [PatientController::class, 'history'])->name('patients.history');
    Route::post('patients/{patient}/problems', [ProblemController::class, 'store'])->name('patients.problems.store');
    Route::patch('problems/{problem}/inactivate', [ProblemController::class, 'inactivate'])->name('problems.inactivate');
    Route::patch('problems/{problem}/reactivate', [ProblemController::class, 'reactivate'])->name('problems.reactivate');
    Route::patch('problems/{problem}/resolve', [ProblemController::class, 'resolve'])->name('problems.resolve');
    Route::patch('problems/{problem}/link', [ProblemController::class, 'link'])->name('problems.link');
    Route::patch('problems/{problem}/unlink', [ProblemController::class, 'unlink'])->name('problems.unlink');
    Route::post('patients/{patient}/followups', [FollowUpController::class, 'store'])->name('patients.followups.store');
    Route::post('followups/{followup}/complete', [FollowUpController::class, 'complete'])->name('followups.complete');
    Route::post('followups/{followup}/cancel', [FollowUpController::class, 'cancel'])->name('followups.cancel');

    // Appointments (OPD)
    Route::get('appointments-react/data', [AppointmentController::class, 'reactAppointmentsData'])->name('appointments.react.data');
    Route::get('appointments/queue/{doctor?}', [AppointmentController::class, 'queue'])->name('appointments.queue');
    Route::get('appointments/{appointment}/token', [AppointmentController::class, 'token'])->name('appointments.token');
    Route::resource('appointments', AppointmentController::class);
    Route::post('appointments/{appointment}/checkin', [AppointmentController::class, 'checkin'])->name('appointments.checkin');
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::post('appointments/{appointment}/transfer', [AppointmentController::class, 'transfer'])->name('appointments.transfer');
    Route::post('appointments/{appointment}/collect-fee', [AppointmentController::class, 'collectFee'])->name('appointments.collect-fee');
    Route::post('appointments/{appointment}/start', [AppointmentController::class, 'start'])->name('appointments.start');
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');

    // Admissions (IPD)
    Route::get('admissions/current', [AdmissionController::class, 'current'])->name('admissions.current');
    Route::resource('admissions', AdmissionController::class);
    Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->name('admissions.discharge');

    // Encounters
    Route::resource('encounters', EncounterController::class)->except(['destroy']);
    Route::post('encounters/{encounter}/start', [EncounterController::class, 'start'])->name('encounters.start');
    Route::post('encounters/{encounter}/complete', [EncounterController::class, 'complete'])->name('encounters.complete');
    Route::post('encounters/{encounter}/cancel', [EncounterController::class, 'cancel'])->name('encounters.cancel');
    Route::post('encounters/{encounter}/amend', [EncounterController::class, 'amend'])->name('encounters.amend');
    Route::get('encounters/{encounter}/diagnoses', [DiagnosisController::class, 'index'])->name('encounters.diagnoses.index');
    Route::post('encounters/{encounter}/diagnoses', [DiagnosisController::class, 'store'])->name('encounters.diagnoses.store');
    Route::patch('diagnoses/{diagnosis}/remove', [DiagnosisController::class, 'remove'])->name('diagnoses.remove');

    // Branches
    Route::resource('branches', BranchController::class)->except(['destroy']);
    Route::post('branches/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])->name('branches.toggle-status');
    Route::post('branches/{branch}/doctors', [BranchController::class, 'assignDoctor'])->name('branches.doctors.assign');
    Route::delete('branches/{branch}/doctors/{doctor}', [BranchController::class, 'removeDoctor'])->name('branches.doctors.remove');

    // Wards & Beds
    Route::get('beds/available', [BedController::class, 'available'])->name('beds.available');
    Route::resource('wards', WardController::class);
    Route::resource('beds', BedController::class);
    Route::post('beds/{bed}/allocate', [BedController::class, 'allocate'])->name('beds.allocate');

    // Prescriptions
    Route::get('prescriptions/patient-info/{patient}', [PrescriptionController::class, 'patientInfo'])->name('prescriptions.patient-info');
    Route::get('prescriptions/patient-options', [PrescriptionController::class, 'patientOptions'])->name('prescriptions.patient-options');
    Route::get('prescriptions/queue-numbers', [PrescriptionController::class, 'queueNumbers'])->name('prescriptions.queue-numbers');
    Route::get('prescriptions/check-existing', [PrescriptionController::class, 'checkExisting'])->name('prescriptions.check-existing');
    Route::post('prescriptions/walk-in', [PrescriptionController::class, 'walkIn'])->name('prescriptions.walk-in');
    Route::resource('prescriptions', PrescriptionController::class);
    Route::post('prescriptions/{prescription}/finalize', [PrescriptionController::class, 'finalize'])->name('prescriptions.finalize');
    Route::get('prescriptions/{prescription}/amend', [PrescriptionController::class, 'amendForm'])->name('prescriptions.amend');
    Route::post('prescriptions/{prescription}/amend', [PrescriptionController::class, 'amend'])->name('prescriptions.amend.store');
    Route::post('prescriptions/{prescription}/findings/{finding}/resolve', [PrescriptionController::class, 'resolveFinding'])->name('prescriptions.findings.resolve');
    Route::get('prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescriptions.print');
    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'downloadPdf'])->name('prescriptions.pdf');
    Route::post('prescriptions/{prescription}/items', [PrescriptionController::class, 'addItem'])->name('prescriptions.items.store');
    Route::delete('prescriptions/{prescription}/items/{item}', [PrescriptionController::class, 'removeItem'])->name('prescriptions.items.destroy');

    // Pharmacy
    Route::get('pharmacy/expiry-alerts', [PharmacyController::class, 'expiryAlerts'])->name('pharmacy.expiry-alerts');
    Route::post('pharmacy/dispense/{prescription_item}', [PharmacyController::class, 'dispense'])->name('pharmacy.dispense');
    Route::post('pharmacy/medicines/quick-store', [MedicineController::class, 'quickStore'])->name('pharmacy.medicines.quick-store');
    Route::post('pharmacy/medicines/{medicine}/restore', [MedicineController::class, 'restore'])->name('pharmacy.medicines.restore');

    // CSV Import (BEFORE resource route to avoid {medicine} capturing 'import')
    Route::get('pharmacy/medicines/import', [MedicineController::class, 'importForm'])->name('pharmacy.medicines.import.form');
    Route::post('pharmacy/medicines/import', [MedicineController::class, 'import'])->name('pharmacy.medicines.import');
    Route::get('pharmacy/medicines/import/template', [MedicineController::class, 'downloadTemplate'])->name('pharmacy.medicines.import.template');

    Route::resource('pharmacy/medicines', MedicineController::class)->names('pharmacy.medicines');
    Route::post('pharmacy/medicines/{medicine}/sync-dgda', [MedicineController::class, 'syncDgda'])->name('pharmacy.medicines.sync-dgda');

    // DGDA Migration
    Route::get('pharmacy/medicines/migrate', [MedicineController::class, 'migrateForm'])->name('pharmacy.medicines.migrate');
    Route::post('pharmacy/medicines/migrate/apply-auto', [MedicineController::class, 'migrateApplyAuto'])->name('pharmacy.medicines.migrate.apply-auto');
    Route::post('pharmacy/medicines/migrate/apply-manual', [MedicineController::class, 'migrateApplyManual'])->name('pharmacy.medicines.migrate.apply-manual');

    // DGDA Search (for hybrid mode)
    Route::get('pharmacy/medicines/dgda-search', [MedicineController::class, 'dgdaSearch'])->name('pharmacy.medicines.dgda-search');

    Route::resource('pharmacy/stock', PharmacyStockController::class)->names('pharmacy.stock');
    Route::get('pharmacy/dispense', [PharmacyController::class, 'dispenseQueue'])->name('pharmacy.dispense.index');
    Route::get('pharmacy/dispense/{prescription_item}', [PharmacyController::class, 'dispenseShow'])->name('pharmacy.dispense.show');
    Route::post('pharmacy/batch-dispense/{prescription}', [PharmacyController::class, 'batchDispense'])->name('pharmacy.dispense.batch');
    Route::post('pharmacy/stock/{stock}/adjust', [PharmacyStockController::class, 'adjust'])->name('pharmacy.stock.adjust');

    // Lab
    Route::resource('lab/tests', LabTestController::class)->names('lab.tests');
    Route::resource('lab/orders', LabOrderController::class)->names('lab.orders');
    Route::post('lab/orders/{order}/collect', [LabOrderController::class, 'collect'])->name('lab.orders.collect');
    Route::post('lab/orders/{order}/cancel', [LabOrderController::class, 'cancel'])->name('lab.orders.cancel');
    Route::post('lab/orders/{order}/result', [LabOrderController::class, 'enterResult'])->name('lab.orders.result');
    Route::get('lab/orders/{order}/report', [LabOrderController::class, 'report'])->name('lab.orders.report');
    Route::get('lab/orders/{order}/result', [LabOrderController::class, 'resultForm'])->name('lab.orders.result.form');

    // Billing
    Route::resource('billing/invoices', InvoiceController::class)->names('billing.invoices');
    Route::post('billing/invoices/{invoice}/payment', [InvoiceController::class, 'payment'])->name('billing.invoices.payment');
    Route::get('billing/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('billing.invoices.print');
    Route::get('billing/payments', [InvoiceController::class, 'payments'])->name('billing.payments.index');

    // TPA
    Route::resource('tpa/claims', TpaClaimController::class)->names('tpa.claims');
    Route::post('tpa/claims/{claim}/approve', [TpaClaimController::class, 'approve'])->name('tpa.claims.approve');
    Route::post('tpa/claims/{claim}/reject', [TpaClaimController::class, 'reject'])->name('tpa.claims.reject');
    Route::post('tpa/claims/{claim}/settle', [TpaClaimController::class, 'settle'])->name('tpa.claims.settle');

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

    // Backwards-compat aliases
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('lab', [LabController::class, 'index'])->name('lab.index');
    Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
    Route::get('tpa', [TpaController::class, 'index'])->name('tpa.index');

    // IPD extras
    Route::post('beds/{bed}/release', [BedController::class, 'release'])->name('beds.release');
    Route::get('admissions/{admission}/discharge', [AdmissionController::class, 'dischargeForm'])->name('admissions.discharge.form');
    Route::get('admissions/{admission}/transfer', [AdmissionController::class, 'transferForm'])->name('admissions.transfer.form');
    Route::post('admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])->name('admissions.transfer');
    Route::get('admissions/{admission}/discharge-summary', [AdmissionController::class, 'dischargeSummary'])->name('admissions.discharge-summary');

    // Vitals
    Route::get('vitals/create', [VitalSignController::class, 'create'])->name('vitals.create');
    Route::post('vitals', [VitalSignController::class, 'store'])->name('vitals.store');
    Route::post('vitals/quick', [VitalSignController::class, 'quickStore'])->name('vitals.quickStore');
    Route::get('vitals', [VitalSignController::class, 'index'])->name('vitals.index');
    Route::get('vitals/opd', [VitalSignController::class, 'opdIndex'])->name('vitals.opd');
    Route::get('vitals/{vital}/edit', [VitalSignController::class, 'edit'])->name('vitals.edit');
    Route::put('vitals/{vital}', [VitalSignController::class, 'update'])->name('vitals.update');
    Route::get('vitals/{vital}', [VitalSignController::class, 'show'])->name('vitals.show');
    Route::delete('vitals/{vital}', [VitalSignController::class, 'destroy'])->name('vitals.destroy');
    Route::post('admissions/{admission}/notes', [VitalSignController::class, 'storeNote'])->name('admissions.notes.store');
    Route::delete('notes/{note}', [VitalSignController::class, 'destroyNote'])->name('notes.destroy');

    // Doctor Management
    Route::get('departments/{department}/specialties', [DepartmentController::class, 'getSpecialties'])->name('departments.specialties');
    Route::post('categories/store', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/departments', [CategoryController::class, 'departments'])->name('categories.departments');
    Route::get('doctors/{doctor}/slots', [DoctorController::class, 'getSlots'])->name('doctors.slots');
    Route::post('doctors/quick-user', [DoctorController::class, 'quickUser'])->name('doctors.quick-user');
    Route::resource('doctors', DoctorController::class);
});

/*
|--------------------------------------------------------------------------
| NEW: Sub-module grouped routes (medical.opd.*, medical.ipd.*, etc.)
|--------------------------------------------------------------------------
| These are the NEW canonical grouped URLs. They coexist with the legacy
| flat URLs above — both work. New code should use these names.
*/
Route::middleware(['auth:institute_user,web', 'tenant', 'medical'])->prefix('medical')->group(function () {

    // OPD
    Route::middleware('medical.module:medical.opd')
        ->prefix('opd')->name('medical.opd.')->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
        Route::get('prescriptions/create', [PrescriptionController::class, 'create'])->name('prescriptions.create');
        Route::post('prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::get('encounters', [EncounterController::class, 'index'])->name('encounters.index');
        Route::get('vitals', [VitalSignController::class, 'index'])->name('vitals.index');
    });

    // IPD
    Route::middleware('medical.module:medical.ipd')
        ->prefix('ipd')->name('medical.ipd.')->group(function () {
        Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
        Route::post('admissions', [AdmissionController::class, 'store'])->name('admissions.store');
        Route::get('wards', [WardController::class, 'index'])->name('wards.index');
        Route::get('beds', [BedController::class, 'index'])->name('beds.index');
    });

    // Pharmacy
    Route::middleware('medical.module:medical.pharmacy')
        ->prefix('pharmacy')->name('medical.pharmacy.')->group(function () {
        Route::get('medicines', [MedicineController::class, 'index'])->name('medicines.index');
        Route::get('medicines/create', [MedicineController::class, 'create'])->name('medicines.create');
        Route::post('medicines', [MedicineController::class, 'store'])->name('medicines.store');
        Route::get('stock', [PharmacyStockController::class, 'index'])->name('stock.index');
        Route::get('dispense', [PharmacyController::class, 'dispenseQueue'])->name('dispense.index');
    });

    // Laboratory
    Route::middleware('medical.module:medical.laboratory')
        ->prefix('laboratory')->name('medical.laboratory.')->group(function () {
        Route::get('orders', [LabOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/create', [LabOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [LabOrderController::class, 'store'])->name('orders.store');
        Route::get('tests', [LabTestController::class, 'index'])->name('tests.index');
    });

    // Billing
    Route::middleware('medical.module:medical.billing')
        ->prefix('billing')->name('medical.billing.')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('tpa-claims', [TpaClaimController::class, 'index'])->name('tpa-claims.index');
    });
});
