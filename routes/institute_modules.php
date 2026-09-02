<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Institute Module Routes
|--------------------------------------------------------------------------
|
| Registers all module routes referenced in views/controllers. Each route
| maps to an existing controller method. Grouped by module prefix.
| Included from routes/web.php inside auth:institute_user,web + tenant.
|
*/

$tenant = ['auth:institute_user,web', 'tenant', 'verified'];

Route::middleware($tenant)->group(function () {

    // ─── HR ────────────────────────────────────────────────────────────────
    $hrEmp = \App\Http\Controllers\Hr\HrEmployeeController::class;
    $hrAtt = \App\Http\Controllers\Hr\HrAttendanceController::class;
    $hrLeave = \App\Http\Controllers\Hr\HrLeaveController::class;
    $hrPay = \App\Http\Controllers\Hr\HrPayrollController::class;
    $hrPerf = \App\Http\Controllers\Hr\HrPerformanceController::class;
    $hrRec = \App\Http\Controllers\Hr\HrRecruitmentController::class;
    $hrTrain = \App\Http\Controllers\Hr\HrTrainingController::class;
    $hrSelf = \App\Http\Controllers\Hr\HrSelfServiceController::class;
    $hrDept = \App\Http\Controllers\Hr\HrDepartmentController::class;
    $hrDesg = \App\Http\Controllers\Hr\HrDesignationController::class;
    $hrDoc = \App\Http\Controllers\Hr\HrDocumentController::class;
    $hrRep = \App\Http\Controllers\Hr\HrReportController::class;
    $hrSal = \App\Http\Controllers\Hr\HrSalaryStructureController::class;
    $hrMgr = \App\Http\Controllers\Hr\HrManagerController::class;

    Route::middleware('module_access:hr')->group(function () use ($hrEmp, $hrAtt, $hrLeave, $hrPay, $hrPerf, $hrRec, $hrTrain, $hrSelf, $hrDept, $hrDesg, $hrDoc, $hrRep, $hrSal, $hrMgr) {

    // HR Employees
    Route::prefix('hr/employees')->name('hr.employees.')->group(function () use ($hrEmp, $hrDoc) {
        Route::get('/', [$hrEmp, 'index'])->name('index');
        Route::get('create', [$hrEmp, 'create'])->name('create');
        Route::post('/', [$hrEmp, 'store'])->name('store');
        Route::get('{employee}', [$hrEmp, 'show'])->name('show');
        Route::get('{employee}/edit', [$hrEmp, 'edit'])->name('edit');
        Route::put('{employee}', [$hrEmp, 'update'])->name('update');
        Route::delete('{employee}', [$hrEmp, 'destroy'])->name('destroy');
        Route::post('{employee}/transfer', [$hrEmp, 'transfer'])->name('transfer');
        Route::post('{employee}/promote', [$hrEmp, 'promote'])->name('promote');
        Route::post('{employee}/resign', [$hrEmp, 'resign'])->name('resign');
        Route::post('{employee}/terminate', [$hrEmp, 'terminate'])->name('terminate');
        Route::post('{employee}/reactivate', [$hrEmp, 'reactivate'])->name('reactivate');
        Route::get('{employee}/documents', [\App\Http\Controllers\Hr\HrDocumentController::class, 'index'])->name('documents.index');
        Route::post('{employee}/documents', [\App\Http\Controllers\Hr\HrDocumentController::class, 'store'])->name('documents.store');
    });

    // HR History
    Route::post('hr/history/resign-decision', [$hrEmp, 'resignDecision'])->name('hr.history.resign-decision');

    // HR Departments
    Route::prefix('hr/departments')->name('hr.departments.')->group(function () use ($hrDept) {
        Route::get('/', [$hrDept, 'index'])->name('index');
        Route::post('/', [$hrDept, 'store'])->name('store');
        Route::put('{department}', [$hrDept, 'update'])->name('update');
        Route::delete('{department}', [$hrDept, 'destroy'])->name('destroy');
        Route::post('{department}/toggle', [$hrDept, 'toggle'])->name('toggle');
    });

    // HR Designations
    Route::prefix('hr/designations')->name('hr.designations.')->group(function () use ($hrDesg) {
        Route::get('/', [$hrDesg, 'index'])->name('index');
        Route::post('/', [$hrDesg, 'store'])->name('store');
        Route::put('{designation}', [$hrDesg, 'update'])->name('update');
        Route::delete('{designation}', [$hrDesg, 'destroy'])->name('destroy');
        Route::post('{designation}/toggle', [$hrDesg, 'toggle'])->name('toggle');
    });

    // HR Attendance
    Route::prefix('hr/attendance')->name('hr.attendance.')->group(function () use ($hrAtt) {
        Route::get('dashboard', [$hrAtt, 'dashboard'])->name('dashboard');
        Route::get('daily', [$hrAtt, 'index'])->name('daily');
        Route::post('daily', [$hrAtt, 'store'])->name('mark');
        Route::get('corrections', [$hrAtt, 'corrections'])->name('corrections');
        Route::post('corrections/request', [$hrAtt, 'requestCorrection'])->name('corrections.request');
        Route::post('corrections/decide', [$hrAtt, 'decideCorrection'])->name('corrections.decide');
        Route::get('shifts', [$hrAtt, 'shifts'])->name('shifts');
        Route::post('shifts', [$hrAtt, 'storeShift'])->name('shifts.store');
        Route::put('shifts/{shift}', [$hrAtt, 'updateShift'])->name('shifts.update');
        Route::delete('shifts/{shift}', [$hrAtt, 'destroyShift'])->name('shifts.destroy');
        Route::get('holidays', [$hrAtt, 'holidays'])->name('holidays');
        Route::post('holidays', [$hrAtt, 'storeHoliday'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [$hrAtt, 'destroyHoliday'])->name('holidays.destroy');
    });

    // HR Leave
    Route::prefix('hr/leave')->name('hr.leave.')->group(function () use ($hrLeave) {
        Route::get('dashboard', [$hrLeave, 'dashboard'])->name('dashboard');
        Route::get('types', [$hrLeave, 'types'])->name('types');
        Route::post('types', [$hrLeave, 'storeType'])->name('types.store');
        Route::put('types/{type}', [$hrLeave, 'updateType'])->name('types.update');
        Route::get('balances', [$hrLeave, 'balances'])->name('balances');
        Route::get('applications', [$hrLeave, 'applications'])->name('applications');
        Route::get('applications/create', [$hrLeave, 'createApplication'])->name('applications.create');
        Route::post('applications', [$hrLeave, 'storeApplication'])->name('applications.store');
        Route::post('applications/decide', [$hrLeave, 'decide'])->name('applications.decide');
    });

    // HR Payroll
    Route::prefix('hr/payroll')->name('hr.payroll.')->group(function () use ($hrPay) {
        Route::post('salary-structures/{employee}/assign', [$hrPay, 'assignSalary'])->name('salary-structures.assign');
        Route::get('periods', [$hrPay, 'periodsIndex'])->name('periods.index');
        Route::post('periods', [$hrPay, 'createPeriod'])->name('periods.store');
        Route::get('periods/{period}', [$hrPay, 'showPeriod'])->name('periods.show');
        Route::post('periods/{period}/generate', [$hrPay, 'generate'])->name('periods.generate');
        Route::post('periods/{period}/preview', [$hrPay, 'preview'])->name('periods.preview');
        Route::post('periods/{period}/approve', [$hrPay, 'approve'])->name('periods.approve');
        Route::post('periods/{period}/pay', [$hrPay, 'pay'])->name('periods.pay');
        Route::post('periods/{period}/cancel', [$hrPay, 'cancel'])->name('periods.cancel');
        Route::get('payslip', [$hrPay, 'payslip'])->name('payslip');
        Route::post('payslip/{payslip}/adjustment', [$hrPay, 'addAdjustment'])->name('payslip.adjustment');
        Route::get('reports', [$hrPay, 'reports'])->name('reports');
        Route::get('register', [$hrPay, 'register'])->name('register');
        Route::get('reconciliation', [$hrPay, 'reconciliation'])->name('reconciliation');
    });

    // HR Performance
    Route::prefix('hr/performance')->name('hr.performance.')->group(function () use ($hrPerf) {
        Route::get('dashboard', [$hrPerf, 'dashboard'])->name('dashboard');
        Route::get('periods', [$hrPerf, 'periods'])->name('periods');
        Route::post('periods', [$hrPerf, 'storePeriod'])->name('periods.store');
        Route::post('periods/{period}/close', [$hrPerf, 'closePeriod'])->name('periods.close');
        Route::get('kpis', [$hrPerf, 'kpis'])->name('kpis');
        Route::post('kpis', [$hrPerf, 'storeKpi'])->name('kpis.store');
        Route::get('reviews', [$hrPerf, 'reviews'])->name('reviews');
        Route::get('reviews/create', [$hrPerf, 'createReview'])->name('reviews.create');
        Route::post('reviews', [$hrPerf, 'storeReview'])->name('reviews.store');
        Route::get('reviews/{review}', [$hrPerf, 'showReview'])->name('reviews.show');
        Route::post('reviews/{review}/evaluate', [$hrPerf, 'evaluate'])->name('reviews.evaluate');
        Route::post('reviews/{review}/approve', [$hrPerf, 'approve'])->name('reviews.approve');
    });

    // HR Recruitment
    Route::prefix('hr/recruitment')->name('hr.recruitment.')->group(function () use ($hrRec) {
        Route::get('dashboard', [$hrRec, 'dashboard'])->name('dashboard');
        Route::get('requisitions', [$hrRec, 'requisitions'])->name('requisitions');
        Route::post('requisitions', [$hrRec, 'storeRequisition'])->name('requisitions.store');
        Route::post('requisitions/submit', [$hrRec, 'submitRequisition'])->name('requisitions.submit');
        Route::post('requisitions/decide', [$hrRec, 'decideRequisition'])->name('requisitions.decide');
        Route::get('vacancies', [$hrRec, 'vacancies'])->name('vacancies');
        Route::post('vacancies', [$hrRec, 'storeVacancy'])->name('vacancies.store');
        Route::post('vacancies/status', [$hrRec, 'updateVacancyStatus'])->name('vacancies.status');
        Route::get('applications', [$hrRec, 'applications'])->name('applications');
        Route::post('applications', [$hrRec, 'storeApplication'])->name('applications.store');
        Route::get('applications/{application}', [$hrRec, 'showApplication'])->name('applications.show');
        Route::post('applications/stage', [$hrRec, 'transitionStage'])->name('applications.stage');
        Route::post('applications/hire', [$hrRec, 'hire'])->name('applications.hire');
        Route::post('interviews', [$hrRec, 'storeInterview'])->name('interviews.store');
        Route::post('offers', [$hrRec, 'storeOffer'])->name('offers.store');
        Route::post('offers/status', [$hrRec, 'updateOfferStatus'])->name('offers.status');
    });

    // HR Training
    Route::prefix('hr/training')->name('hr.training.')->group(function () use ($hrTrain) {
        Route::get('dashboard', [$hrTrain, 'dashboard'])->name('dashboard');
        Route::get('programs', [$hrTrain, 'index'])->name('programs');
        Route::post('programs', [$hrTrain, 'store'])->name('programs.store');
        Route::get('programs/{program}', [$hrTrain, 'show'])->name('programs.show');
        Route::put('programs/{program}', [$hrTrain, 'update'])->name('programs.update');
        Route::post('programs/enroll', [$hrTrain, 'enroll'])->name('programs.enroll');
        Route::post('enrollments/update', [$hrTrain, 'updateEnrollment'])->name('enrollments.update');
        Route::get('skills', [$hrTrain, 'skills'])->name('skills');
        Route::post('skills', [$hrTrain, 'storeSkill'])->name('skills.store');
        Route::post('skills/verify', [$hrTrain, 'verifySkill'])->name('skills.verify');
    });

    // HR Self Service
    Route::prefix('hr/self')->name('hr.self.')->group(function () use ($hrSelf) {
        Route::get('dashboard', [$hrSelf, 'dashboard'])->name('dashboard');
        Route::get('profile', [$hrSelf, 'profile'])->name('profile');
        Route::put('profile', [$hrSelf, 'updateProfile'])->name('profile.update');
        Route::get('leave', [$hrSelf, 'leave'])->name('leave');
        Route::post('leave', [$hrSelf, 'storeLeave'])->name('leave.store');
        Route::post('leave/cancel', [$hrSelf, 'cancelLeave'])->name('leave.cancel');
        Route::get('attendance', [$hrSelf, 'attendance'])->name('attendance');
        Route::post('attendance/correction', [$hrSelf, 'requestCorrection'])->name('attendance.correction');
        Route::get('payslips', [$hrSelf, 'payslips'])->name('payslips');
        Route::get('payslips/{payslip}', [$hrSelf, 'payslipShow'])->name('payslip.show');
        Route::post('documents/upload', [$hrSelf, 'uploadDocument'])->name('documents.upload');
    });

    // HR Documents
    Route::prefix('hr/documents')->name('hr.documents.')->group(function () use ($hrDoc) {
        Route::get('download/{document}', [\App\Http\Controllers\Hr\HrDocumentController::class, 'download'])->name('download');
        Route::get('categories', [\App\Http\Controllers\Hr\HrDocumentController::class, 'categories'])->name('categories');
        Route::put('{document}', [\App\Http\Controllers\Hr\HrDocumentController::class, 'update'])->name('update');
        Route::post('{document}/replace', [\App\Http\Controllers\Hr\HrDocumentController::class, 'replace'])->name('replace');
        Route::post('{document}/archive', [\App\Http\Controllers\Hr\HrDocumentController::class, 'archive'])->name('archive');
        Route::delete('{document}', [\App\Http\Controllers\Hr\HrDocumentController::class, 'destroy'])->name('destroy');
        Route::post('{document}/verify', [\App\Http\Controllers\Hr\HrDocumentController::class, 'verify'])->name('verify');
        Route::post('{document}/reject', [\App\Http\Controllers\Hr\HrDocumentController::class, 'reject'])->name('reject');
        Route::get('{document}/versions', [\App\Http\Controllers\Hr\HrDocumentController::class, 'versions'])->name('versions');
    });

    // HR Reports
    Route::prefix('hr/reports')->name('hr.reports.')->group(function () use ($hrRep) {
        Route::get('/', [$hrRep, 'index'])->name('index');
        Route::get('employee', [$hrRep, 'employee'])->name('employee');
        Route::get('employee/export', [$hrRep, 'employeeExport'])->name('employee.export');
        Route::get('workforce', [$hrRep, 'workforce'])->name('workforce');
        Route::get('workforce/export', [$hrRep, 'workforceExport'])->name('workforce.export');
        Route::get('attendance/export', [$hrRep, 'attendanceExport'])->name('attendance.export');
        Route::get('leave/export', [$hrRep, 'leaveExport'])->name('leave.export');
        Route::get('payroll/export', [$hrRep, 'payrollExport'])->name('payroll.export');
        Route::get('performance/export', [$hrRep, 'performanceExport'])->name('performance.export');
        Route::get('recruitment/export', [$hrRep, 'recruitmentExport'])->name('recruitment.export');
        Route::get('training/export', [$hrRep, 'trainingExport'])->name('training.export');
    });

    // HR Salary Structures
    Route::prefix('hr/salary-structures')->name('hr.salary-structures.')->group(function () use ($hrSal) {
        Route::get('/', [$hrSal, 'index'])->name('index');
        Route::get('create', [$hrSal, 'create'])->name('create');
        Route::post('/', [$hrSal, 'store'])->name('store');
        Route::get('{salaryStructure}/edit', [$hrSal, 'edit'])->name('edit');
        Route::put('{salaryStructure}', [$hrSal, 'update'])->name('update');
        Route::post('{salaryStructure}/toggle', [$hrSal, 'toggle'])->name('toggle');
        Route::delete('{salaryStructure}', [$hrSal, 'destroy'])->name('destroy');
    });

    // HR Manager
    Route::get('hr/manager/dashboard', [$hrMgr, 'dashboard'])->name('hr.manager.dashboard');
    }); // end module_access:hr

    // ─── CRM ───────────────────────────────────────────────────────────────
    $crmContact = \App\Http\Controllers\CrmContactController::class;
    $crmLead = \App\Http\Controllers\CrmLeadController::class;
    $crmOrg = \App\Http\Controllers\CrmOrganizationController::class;
    $crmTask = \App\Http\Controllers\CrmTaskController::class;
    $crmAct = \App\Http\Controllers\CrmActivityController::class;
    $crmNote = \App\Http\Controllers\CrmNoteController::class;

    Route::middleware('module_access:crm')->group(function () use ($crmContact, $crmLead, $crmOrg, $crmTask, $crmAct, $crmNote) {

    // CRM Contacts
    Route::prefix('crm/contacts')->name('crm.contacts.')->group(function () use ($crmContact) {
        Route::get('/', [$crmContact, 'index'])->name('index');
        Route::get('create', [$crmContact, 'create'])->name('create');
        Route::post('/', [$crmContact, 'store'])->name('store');
        Route::get('{contact}', [$crmContact, 'show'])->name('show');
        Route::get('{contact}/edit', [$crmContact, 'edit'])->name('edit');
        Route::put('{contact}', [$crmContact, 'update'])->name('update');
        Route::post('{contact}/assign', [$crmContact, 'assign'])->name('assign');
        Route::delete('{contact}', [$crmContact, 'destroy'])->name('destroy');
    });

    // CRM Leads
    Route::prefix('crm/leads')->name('crm.leads.')->group(function () use ($crmLead) {
        Route::get('/', [$crmLead, 'index'])->name('index');
        Route::get('create', [$crmLead, 'create'])->name('create');
        Route::post('/', [$crmLead, 'store'])->name('store');
        Route::get('{lead}', [$crmLead, 'show'])->name('show');
        Route::get('{lead}/edit', [$crmLead, 'edit'])->name('edit');
        Route::put('{lead}', [$crmLead, 'update'])->name('update');
        Route::post('{lead}/assign', [$crmLead, 'assign'])->name('assign');
        Route::post('{lead}/convert', [$crmLead, 'convert'])->name('convert');
        Route::delete('{lead}', [$crmLead, 'destroy'])->name('destroy');
    });

    // CRM Organizations
    Route::prefix('crm/organizations')->name('crm.organizations.')->group(function () use ($crmOrg) {
        Route::get('/', [$crmOrg, 'index'])->name('index');
        Route::get('create', [$crmOrg, 'create'])->name('create');
        Route::post('/', [$crmOrg, 'store'])->name('store');
        Route::get('{organization}', [$crmOrg, 'show'])->name('show');
        Route::get('{organization}/edit', [$crmOrg, 'edit'])->name('edit');
        Route::put('{organization}', [$crmOrg, 'update'])->name('update');
        Route::post('{organization}/assign', [$crmOrg, 'assign'])->name('assign');
        Route::delete('{organization}', [$crmOrg, 'destroy'])->name('destroy');
    });

    // CRM Tasks
    Route::prefix('crm/tasks')->name('crm.tasks.')->group(function () use ($crmTask) {
        Route::get('/', [$crmTask, 'index'])->name('index');
        Route::post('/', [$crmTask, 'store'])->name('store');
        Route::post('{task}/toggle', [$crmTask, 'toggle'])->name('toggle');
        Route::delete('{task}', [$crmTask, 'destroy'])->name('destroy');
    });

    // CRM Activities
    Route::prefix('crm/activities')->name('crm.activities.')->group(function () use ($crmAct) {
        Route::get('/', [$crmAct, 'index'])->name('index');
        Route::post('/', [$crmAct, 'store'])->name('store');
        Route::delete('{activity}', [$crmAct, 'destroy'])->name('destroy');
    });

    // CRM Notes
    Route::post('crm/notes', [$crmNote, 'store'])->name('crm.notes.store');
    }); // end module_access:crm

    // ─── SALES ─────────────────────────────────────────────────────────────
    $salesOrder = \App\Http\Controllers\Sales\SalesOrderController::class;
    $salesDel = \App\Http\Controllers\Sales\DeliveryController::class;
    $salesQuot = \App\Http\Controllers\Sales\QuotationController::class;
    $salesRet = \App\Http\Controllers\Sales\SalesReturnController::class;
    $salesLead = \App\Http\Controllers\Sales\LeadController::class;
    $salesCust = \App\Http\Controllers\Sales\CustomerController::class;
    $salesInv = \App\Http\Controllers\Sales\SalesInvoiceController::class;
    $salesReport = \App\Http\Controllers\Sales\SalesReportController::class;
    $salesSettings = \App\Http\Controllers\Sales\SalesSettingsController::class;

    Route::middleware('module_access:sales')->group(function () use ($salesOrder, $salesDel, $salesQuot, $salesRet, $salesLead, $salesCust, $salesInv, $salesReport, $salesSettings) {

    // Sales Settings
    Route::post('sales/settings', [$salesSettings, 'update'])->name('sales.settings.update');

    // Sales Reports
    Route::prefix('sales/reports')->name('sales.reports.')->group(function () use ($salesReport) {
        Route::get('/', [$salesReport, 'dashboard'])->name('dashboard');
        Route::get('daily', [$salesReport, 'daily'])->name('daily');
        Route::get('weekly', [$salesReport, 'weekly'])->name('weekly');
        Route::get('monthly', [$salesReport, 'monthly'])->name('monthly');
        Route::get('yearly', [$salesReport, 'yearly'])->name('yearly');
        Route::get('product', [$salesReport, 'product'])->name('product');
        Route::get('category', [$salesReport, 'category'])->name('category');
        Route::get('customer', [$salesReport, 'customer'])->name('customer');
        Route::get('salesperson', [$salesReport, 'salesperson'])->name('salesperson');
        Route::get('branch', [$salesReport, 'branch'])->name('branch');
        Route::get('warehouse', [$salesReport, 'warehouse'])->name('warehouse');
        Route::get('returns', [$salesReport, 'returnsReport'])->name('returns');
        Route::get('statement', [$salesReport, 'statement'])->name('statement');
    });

    // Sales Quotations
    Route::prefix('sales/quotations')->name('sales.quotations.')->group(function () use ($salesQuot) {
        Route::get('/', [$salesQuot, 'index'])->name('index');
        Route::get('create', [$salesQuot, 'create'])->name('create');
        Route::post('/', [$salesQuot, 'store'])->name('store');
        Route::get('{quotation}', [$salesQuot, 'show'])->name('show');
        Route::get('{quotation}/edit', [$salesQuot, 'edit'])->name('edit');
        Route::put('{quotation}', [$salesQuot, 'update'])->name('update');
        Route::post('{quotation}/send', [$salesQuot, 'send'])->name('send');
        Route::post('{quotation}/accept', [$salesQuot, 'accept'])->name('accept');
        Route::post('{quotation}/reject', [$salesQuot, 'reject'])->name('reject');
        Route::post('{quotation}/cancel', [$salesQuot, 'cancel'])->name('cancel');
        Route::post('{quotation}/expire', [$salesQuot, 'expire'])->name('expire');
        Route::get('{quotation}/print', [$salesQuot, 'print'])->name('print');
    });

    // Sales Orders
    Route::prefix('sales/orders')->name('sales.orders.')->group(function () use ($salesOrder) {
        Route::get('/', [$salesOrder, 'index'])->name('index');
        Route::get('create', [$salesOrder, 'create'])->name('create');
        Route::post('/', [$salesOrder, 'store'])->name('store');
        Route::get('{order}', [$salesOrder, 'show'])->name('show');
        Route::get('{order}/edit', [$salesOrder, 'edit'])->name('edit');
        Route::put('{order}', [$salesOrder, 'update'])->name('update');
        Route::post('{order}/submit', [$salesOrder, 'submit'])->name('submit');
        Route::post('{order}/approve', [$salesOrder, 'approve'])->name('approve');
        Route::post('{order}/reject', [$salesOrder, 'reject'])->name('reject');
        Route::post('{order}/cancel', [$salesOrder, 'cancel'])->name('cancel');
        Route::post('{order}/processing', [$salesOrder, 'processing'])->name('processing');
        Route::post('{order}/ready', [$salesOrder, 'readyForDelivery'])->name('ready');
        Route::post('{order}/complete', [$salesOrder, 'complete'])->name('complete');
        Route::get('{order}/print', [$salesOrder, 'print'])->name('print');
    });

    // Sales Deliveries
    Route::prefix('sales/deliveries')->name('sales.deliveries.')->group(function () use ($salesDel, $salesInv) {
        Route::get('/', [$salesDel, 'index'])->name('index');
        Route::get('create', [$salesDel, 'create'])->name('create');
        Route::post('/', [$salesDel, 'store'])->name('store');
        Route::get('{delivery}', [$salesDel, 'show'])->name('show');
        Route::post('{delivery}/confirm', [$salesDel, 'confirm'])->name('confirm');
        Route::post('{delivery}/cancel', [$salesDel, 'cancel'])->name('cancel');
        Route::get('{delivery}/print', [$salesDel, 'print'])->name('print');
        Route::post('{delivery}/invoice', [\App\Http\Controllers\Sales\SalesInvoiceController::class, 'storeForDelivery'])->name('invoice');
    });

    // Sales Invoices
    Route::prefix('sales/invoices')->name('sales.invoices.')->group(function () use ($salesInv) {
        Route::get('create', [\App\Http\Controllers\Sales\SalesInvoiceController::class, 'createForOrder'])->name('create');
        Route::post('/', [\App\Http\Controllers\Sales\SalesInvoiceController::class, 'storeForOrder'])->name('store');
    });

    // Sales Returns
    Route::prefix('sales/returns')->name('sales.returns.')->group(function () use ($salesRet) {
        Route::get('/', [$salesRet, 'index'])->name('index');
        Route::get('create', [$salesRet, 'create'])->name('create');
        Route::post('/', [$salesRet, 'store'])->name('store');
        Route::get('{return}', [$salesRet, 'show'])->name('show');
        Route::post('{return}/credit-note', [$salesRet, 'creditNote'])->name('credit-note');
        Route::post('{return}/approve', [$salesRet, 'approve'])->name('approve');
        Route::post('{return}/post', [$salesRet, 'post'])->name('post');
        Route::post('{return}/cancel', [$salesRet, 'cancel'])->name('cancel');
        Route::post('{return}/reverse', [$salesRet, 'reverse'])->name('reverse');
        Route::post('{return}/refund', [$salesRet, 'refund'])->name('refund');
    });

    // Sales Leads
    Route::prefix('sales/leads')->name('sales.leads.')->group(function () use ($salesLead) {
        Route::get('/', [$salesLead, 'index'])->name('index');
        Route::get('create', [$salesLead, 'create'])->name('create');
        Route::post('/', [$salesLead, 'store'])->name('store');
        Route::get('{lead}', [$salesLead, 'show'])->name('show');
        Route::post('{lead}/convert', [$salesLead, 'convertToQuotation'])->name('convert');
    });

    // Sales Customers (manage)
    Route::prefix('sales/customers/manage')->name('sales.customers.manage.')->group(function () use ($salesCust) {
        Route::get('/', [$salesCust, 'index'])->name('index');
        Route::get('create', [$salesCust, 'create'])->name('create');
        Route::post('/', [$salesCust, 'store'])->name('store');
        Route::get('{customer}', [$salesCust, 'show'])->name('show');
        Route::get('{customer}/edit', [$salesCust, 'edit'])->name('edit');
        Route::put('{customer}', [$salesCust, 'update'])->name('update');
    });
    }); // end module_access:sales

    // ─── PURCHASE ──────────────────────────────────────────────────────────
    $poCtrl = \App\Http\Controllers\PurchaseOrderController::class;
    $pInv = \App\Http\Controllers\Purchase\PurchaseInvoiceController::class;
    $pQuot = \App\Http\Controllers\Purchase\PurchaseQuotationController::class;
    $pReq = \App\Http\Controllers\Purchase\PurchaseRequestController::class;
    $pRet = \App\Http\Controllers\Purchase\PurchaseReturnController::class;
    $pRcpt = \App\Http\Controllers\Purchase\GoodsReceiptWebController::class;
    $pRep = \App\Http\Controllers\Purchase\PurchaseReportController::class;
    $pSup = \App\Http\Controllers\Purchase\SuppliersController::class;

    Route::middleware('module_access:purchase')->group(function () use ($poCtrl, $pInv, $pQuot, $pReq, $pRet, $pRcpt, $pRep, $pSup) {

    // Purchase Orders
    Route::prefix('purchase/orders')->name('purchase.orders.')->group(function () use ($poCtrl) {
        Route::get('/', [$poCtrl, 'index'])->name('index');
        Route::get('create', [$poCtrl, 'create'])->name('create');
        Route::post('/', [$poCtrl, 'store'])->name('store');
        Route::get('{order}', [$poCtrl, 'show'])->name('show');
        Route::get('{order}/edit', [$poCtrl, 'edit'])->name('edit');
        Route::put('{order}', [$poCtrl, 'update'])->name('update');
        Route::post('{order}/submit', [$poCtrl, 'submit'])->name('submit');
        Route::post('{order}/approve', [$poCtrl, 'approve'])->name('approve');
        Route::post('{order}/reject', [$poCtrl, 'reject'])->name('reject');
        Route::post('{order}/cancel', [$poCtrl, 'cancel'])->name('cancel');
        Route::post('{order}/close', [$poCtrl, 'close'])->name('close');
        Route::get('{order}/print', [$poCtrl, 'print'])->name('print');
    });

    // Purchase Invoices
    Route::prefix('purchase/invoices')->name('purchase.invoices.')->group(function () use ($pInv) {
        Route::get('/', [$pInv, 'index'])->name('index');
        Route::get('create', [$pInv, 'create'])->name('create');
        Route::post('/', [$pInv, 'store'])->name('store');
        Route::get('{invoice}', [$pInv, 'show'])->name('show');
        Route::post('{invoice}/post', [$pInv, 'post'])->name('post');
        Route::post('{invoice}/cancel', [$pInv, 'cancel'])->name('cancel');
        Route::get('{invoice}/print', [$pInv, 'print'])->name('print');
        Route::post('{invoice}/reverse', [$pInv, 'reverse'])->name('reverse');
        Route::post('{invoice}/pay', [$pInv, 'pay'])->name('pay');
        Route::post('{invoice}/reverse-payment', [$pInv, 'reversePayment'])->name('reverse-payment');
    });

    // Purchase Quotations
    Route::prefix('purchase/quotations')->name('purchase.quotations.')->group(function () use ($pQuot) {
        Route::get('/', [$pQuot, 'index'])->name('index');
        Route::get('create', [$pQuot, 'create'])->name('create');
        Route::post('/', [$pQuot, 'store'])->name('store');
        Route::get('{quotation}', [$pQuot, 'show'])->name('show');
        Route::get('{quotation}/edit', [$pQuot, 'edit'])->name('edit');
        Route::put('{quotation}', [$pQuot, 'update'])->name('update');
        Route::post('{quotation}/send', [$pQuot, 'send'])->name('send');
        Route::post('{quotation}/accept', [$pQuot, 'accept'])->name('accept');
        Route::post('{quotation}/reject', [$pQuot, 'reject'])->name('reject');
        Route::post('{quotation}/cancel', [$pQuot, 'cancel'])->name('cancel');
        Route::post('{quotation}/expire', [$pQuot, 'expire'])->name('expire');
        Route::post('{quotation}/convert', [$pQuot, 'convert'])->name('convert');
    });

    // Purchase Requests
    Route::prefix('purchase/requests')->name('purchase.requests.')->group(function () use ($pReq) {
        Route::get('/', [$pReq, 'index'])->name('index');
        Route::get('create', [$pReq, 'create'])->name('create');
        Route::post('/', [$pReq, 'store'])->name('store');
        Route::get('{request}', [$pReq, 'show'])->name('show');
        Route::post('{request}/approve', [$pReq, 'approve'])->name('approve');
        Route::post('{request}/convert', [$pReq, 'convertToOrder'])->name('convert');
    });

    // Purchase Returns
    Route::prefix('purchase/returns')->name('purchase.returns.')->group(function () use ($pRet) {
        Route::get('/', [$pRet, 'index'])->name('index');
        Route::get('create', [$pRet, 'create'])->name('create');
        Route::post('/', [$pRet, 'store'])->name('store');
        Route::get('{return}', [$pRet, 'show'])->name('show');
        Route::post('{return}/submit', [$pRet, 'submit'])->name('submit');
        Route::post('{return}/approve', [$pRet, 'approve'])->name('approve');
        Route::post('{return}/post', [$pRet, 'post'])->name('post');
        Route::post('{return}/cancel', [$pRet, 'cancel'])->name('cancel');
        Route::post('{return}/reverse', [$pRet, 'reverse'])->name('reverse');
        Route::post('{return}/credit-note', [$pRet, 'creditNote'])->name('credit-note');
        Route::get('{return}/print', [$pRet, 'print'])->name('print');
        Route::post('{return}/refund', [$pRet, 'refund'])->name('refund');
        Route::post('{return}/adjust', [$pRet, 'adjust'])->name('adjust');
    });

    // Purchase Goods Receipts
    Route::prefix('purchase/receipts')->name('purchase.receipts.')->group(function () use ($pRcpt) {
        Route::get('/', [$pRcpt, 'index'])->name('index');
        Route::get('create', [$pRcpt, 'create'])->name('create');
        Route::post('/', [$pRcpt, 'store'])->name('store');
        Route::get('{receipt}', [$pRcpt, 'show'])->name('show');
        Route::post('{receipt}/confirm', [$pRcpt, 'confirm'])->name('confirm');
        Route::post('{receipt}/cancel', [$pRcpt, 'cancel'])->name('cancel');
        Route::post('{receipt}/reverse', [$pRcpt, 'reverse'])->name('reverse');
        Route::get('{receipt}/print', [$pRcpt, 'print'])->name('print');
    });

    // Purchase Reports
    Route::prefix('purchase/reports')->name('purchase.reports.')->group(function () use ($pRep) {
        Route::get('/', [$pRep, 'dashboard'])->name('dashboard');
        Route::get('daily', [$pRep, 'daily'])->name('daily');
        Route::get('export', [$pRep, 'export'])->name('export');
        Route::get('inventory', [$pRep, 'inventoryReconciliation'])->name('inventory');
        Route::get('payable', [$pRep, 'payableReport'])->name('payable');
        Route::get('print', [$pRep, 'print'])->name('print');
        Route::get('product', [$pRep, 'productWise'])->name('product');
        Route::get('supplier', [$pRep, 'supplierWise'])->name('supplier');
        Route::get('supplierStatement', [$pRep, 'supplierStatement'])->name('supplierStatement');
    });

    // Purchase Payments
    Route::post('purchase/payments/reverse', [$pRet, 'reverse'])->middleware('module_access:purchase')->name('purchase.payments.reverse');

    // Purchase Credit
    Route::post('purchase/credit/adjust', [$pRet, 'adjust'])->middleware('module_access:purchase')->name('purchase.credit.adjust');
    }); // end module_access:purchase

    // ─── FINANCE ───────────────────────────────────────────────────────────
    $finBudget = \App\Http\Controllers\FinanceBudgetController::class;
    $finCoA = \App\Http\Controllers\FinanceChartOfAccountController::class;
    $finJour = \App\Http\Controllers\FinanceJournalController::class;
    $finInv = \App\Http\Controllers\FinanceInvoiceController::class;
    $finPay = \App\Http\Controllers\FinancePaymentController::class;
    $finPart = \App\Http\Controllers\FinancePartyController::class;
    $finPm = \App\Http\Controllers\FinancePaymentMethodController::class;
    $finPer = \App\Http\Controllers\FinancePeriodController::class;
    $finOB = \App\Http\Controllers\FinanceOpeningBalanceController::class;
    $finFx = \App\Http\Controllers\FinanceExchangeRateController::class;
    $finReval = \App\Http\Controllers\FinanceFxRevaluationController::class;
    $finOpay = \App\Http\Controllers\FinanceOnlinePaymentController::class;
    $feeStruct = \App\Http\Controllers\FeeStructureController::class;
    $eduFin = \App\Http\Controllers\EducationFinanceController::class;

    // Finance Budgets
    Route::prefix('finance/budgets')->name('finance.budgets.')->group(function () use ($finBudget) {
        Route::get('/', [$finBudget, 'index'])->name('index');
        Route::get('create', [$finBudget, 'create'])->name('create');
        Route::post('/', [$finBudget, 'store'])->name('store');
        Route::get('{budget}', [$finBudget, 'show'])->name('show');
        Route::get('{budget}/edit', [$finBudget, 'edit'])->name('edit');
        Route::put('{budget}', [$finBudget, 'update'])->name('update');
        Route::post('{budget}/submit', [$finBudget, 'submit'])->name('submit');
        Route::post('{budget}/approve', [$finBudget, 'approve'])->name('approve');
        Route::post('{budget}/reject', [$finBudget, 'reject'])->name('reject');
        Route::post('{budget}/lock', [$finBudget, 'lock'])->name('lock');
        Route::post('{budget}/revise', [$finBudget, 'revise'])->name('revise');
        Route::get('{budget}/reports', [$finBudget, 'reports'])->name('reports');
        Route::get('{budget}/forecast', [$finBudget, 'forecast'])->name('forecast');
        Route::get('export/comparison', [$finBudget, 'exportComparison'])->name('export.comparison');
    });

    // Finance Chart of Accounts
    Route::prefix('finance/chart-of-accounts')->name('finance.chart-of-accounts.')->group(function () use ($finCoA) {
        Route::get('create', [$finCoA, 'create'])->name('create');
        Route::post('/', [$finCoA, 'store'])->name('store');
        Route::get('{account}/edit', [$finCoA, 'edit'])->name('edit');
        Route::put('{account}', [$finCoA, 'update'])->name('update');
        Route::post('{account}/toggle', [$finCoA, 'toggle'])->name('toggle');
        Route::delete('{account}', [$finCoA, 'destroy'])->name('destroy');
    });

    // Finance Journals
    Route::prefix('finance/journals')->name('finance.journals.')->group(function () use ($finJour) {
        Route::get('create', [$finJour, 'create'])->name('create');
        Route::post('/', [$finJour, 'store'])->name('store');
        Route::get('{journal}', [$finJour, 'show'])->name('show');
        Route::post('{journal}/post', [$finJour, 'post'])->name('post');
        Route::post('{journal}/reverse', [$finJour, 'reverse'])->name('reverse');
        Route::post('{journal}/void', [$finJour, 'void'])->name('void');
    });

    // Finance Invoices
    Route::prefix('finance/invoices')->name('finance.invoices.')->group(function () use ($finInv) {
        Route::get('create', [$finInv, 'create'])->name('create');
        Route::post('/', [$finInv, 'store'])->name('store');
        Route::get('{invoice}', [$finInv, 'show'])->name('show');
        Route::post('{invoice}/cancel', [$finInv, 'cancel'])->name('cancel');
    });

    // Finance Payments
    Route::prefix('finance/payments')->name('finance.payments.')->group(function () use ($finPay) {
        Route::post('/', [$finPay, 'store'])->name('store');
        Route::post('{payment}/reverse', [$finPay, 'reverse'])->name('reverse');
    });

    // Finance Parties
    Route::prefix('finance/parties')->name('finance.parties.')->group(function () use ($finPart) {
        Route::get('create', [$finPart, 'create'])->name('create');
        Route::post('/', [$finPart, 'store'])->name('store');
        Route::get('{party}/edit', [$finPart, 'edit'])->name('edit');
        Route::put('{party}', [$finPart, 'update'])->name('update');
        Route::delete('{party}', [$finPart, 'destroy'])->name('destroy');
    });

    // Finance Payment Methods
    Route::prefix('finance/payment-methods')->name('finance.payment-methods.')->group(function () use ($finPm) {
        Route::get('create', [$finPm, 'create'])->name('create');
        Route::post('/', [$finPm, 'store'])->name('store');
        Route::get('{method}/edit', [$finPm, 'edit'])->name('edit');
        Route::put('{method}', [$finPm, 'update'])->name('update');
    });

    // Finance Periods
    Route::prefix('finance/periods')->name('finance.periods.')->group(function () use ($finPer) {
        Route::post('/', [$finPer, 'store'])->name('store');
        Route::post('{period}/close', [$finPer, 'closePeriod'])->name('close');
        Route::post('{period}/reopen', [$finPer, 'reopenPeriod'])->name('reopen');
    });

    // Finance Opening Balances
    Route::prefix('finance/opening-balances')->name('finance.opening-balances.')->group(function () use ($finOB) {
        Route::post('/', [$finOB, 'store'])->name('store');
    });

    // Finance Exchange Rates
    Route::prefix('finance/exchange-rates')->name('finance.exchange-rates.')->group(function () use ($finFx) {
        Route::post('/', [$finFx, 'store'])->name('store');
        Route::delete('{rate}', [$finFx, 'destroy'])->name('destroy');
    });

    // Finance FX Revaluations
    Route::prefix('finance/fx-revaluations')->name('finance.fx-revaluations.')->group(function () use ($finReval) {
        Route::post('/', [$finReval, 'store'])->name('store');
        Route::post('{revaluation}/reverse', [$finReval, 'reverse'])->name('reverse');
    });

    // Finance Online Payments
    Route::prefix('finance/online-payments')->name('finance.online-payments.')->group(function () use ($finOpay) {
        Route::post('{gateway}/enable', [$finOpay, 'enableGateway'])->name('enable');
        Route::post('{gateway}/disable', [$finOpay, 'disableGateway'])->name('disable');
        Route::get('attempts', [$finOpay, 'attempts'])->name('attempts');
    });

    // Finance Education
    Route::prefix('finance/education')->name('finance.education.')->group(function () use ($feeStruct, $eduFin) {
        // Fee Heads — read
        Route::get('fee-heads', [$feeStruct, 'feeHeadsIndex'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('fee-heads.index');
        // Fee Heads — mutations
        Route::post('fee-heads', [$feeStruct, 'feeHeadsStore'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-heads.store');
        Route::put('fee-heads/{feeHead}', [$feeStruct, 'feeHeadsUpdate'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-heads.update');
        Route::post('fee-heads/{feeHead}/toggle', [$feeStruct, 'feeHeadsToggle'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-heads.toggle');
        Route::delete('fee-heads/{feeHead}', [$feeStruct, 'feeHeadsDestroy'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-heads.destroy');

        // Fee Structures — read
        Route::get('fee-structures/create', [$feeStruct, 'create'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-structures.create');
        // Fee Structures — mutations
        Route::post('fee-structures', [$feeStruct, 'store'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-structures.store');
        Route::get('fee-structures/{feeStructure}/edit', [$feeStruct, 'edit'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-structures.edit');
        Route::put('fee-structures/{feeStructure}', [$feeStruct, 'update'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-structures.update');
        Route::delete('fee-structures/{feeStructure}', [$feeStruct, 'destroy'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-structures.destroy');

        // Students Finance — read
        Route::get('students', [$eduFin, 'students'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('students.index');
        Route::get('students/{student}', [$eduFin, 'studentShow'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('students.show');
        // Students Finance — mutations
        Route::post('students/{student}/invoice', [$eduFin, 'generateInvoice'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('students.invoice');
        Route::post('students/{student}/payments', [$eduFin, 'recordPayment'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('students.payments');

        // Invoices — mutations
        Route::post('invoices/{invoice}/waive', [$eduFin, 'applyWaiver'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('invoices.waive');

        // Payments — mutations
        Route::post('payments/{payment}/reverse', [$eduFin, 'reversePayment'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('payments.reverse');

        // Receipt — read
        Route::get('payments/{payment}/receipt', [$eduFin, 'printReceipt'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('receipt');

        // Fee Collection — read
        Route::get('fee-collection', [$eduFin, 'feeCollection'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('fee-collection');
        Route::get('fee-collection/student/{student}', [$eduFin, 'feeCollectionStudent'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('fee-collection.student');
        // Fee Collection — mutations
        Route::post('fee-collection/pay', [$eduFin, 'collectFee'])
            ->middleware('permission:finance.manage', 'module_access:finance')
            ->name('fee-collection.pay');

        // Reports — read
        Route::get('reports/batches', [$eduFin, 'batches'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('reports.batches');
        Route::get('reports/courses', [$eduFin, 'courses'])
            ->middleware('permission:finance.view', 'module_access:finance')
            ->name('reports.courses');
    });

    // ─── ACCOUNTING ────────────────────────────────────────────────────────
    $acctReport = \App\Http\Controllers\Accounting\AccountingReportController::class;
    $acctPeriod = \App\Http\Controllers\Accounting\AccountingPeriodController::class;
    $acctFiscal = \App\Http\Controllers\Accounting\FiscalYearController::class;
    $acctApproval = \App\Http\Controllers\Accounting\ApprovalWorkflowController::class;
    $acctBank = \App\Http\Controllers\Accounting\BankReconciliationController::class;
    $acctExec = \App\Http\Controllers\Accounting\ExecutiveDashboardController::class;
    $acctPayable = \App\Http\Controllers\Accounting\PayableController::class;
    $acctReceivable = \App\Http\Controllers\Accounting\ReceivableController::class;
    $acctSecurity = \App\Http\Controllers\Accounting\SecurityAuditController::class;

    // Accounting Approvals
    Route::prefix('accounting/approvals')->name('accounting.approvals.')->group(function () use ($acctApproval) {
        Route::get('/', [$acctApproval, 'index'])->name('index');
        Route::get('create', [$acctApproval, 'create'])->name('create');
        Route::post('/', [$acctApproval, 'store'])->name('store');
        Route::get('{approval}', [$acctApproval, 'show'])->name('show');
        Route::post('{approval}/approve', [$acctApproval, 'approve'])->name('approve');
        Route::post('{approval}/reject', [$acctApproval, 'reject'])->name('reject');
    });

    // Accounting Bank Reconciliation
    Route::prefix('accounting/bank-reconciliation')->name('accounting.bank-reconciliation.')->group(function () use ($acctBank) {
        Route::get('statements', [$acctBank, 'statements'])->name('statements');
        Route::post('statements', [$acctBank, 'storeStatement'])->name('statements.store');
        Route::get('{reconciliation}', [$acctBank, 'show'])->name('show');
        Route::post('{reconciliation}/auto-match', [$acctBank, 'autoMatch'])->name('auto-match');
        Route::post('{reconciliation}/lines', [$acctBank, 'storeLine'])->name('lines.store');
        Route::delete('{reconciliation}/lines/{line}', [$acctBank, 'destroyLine'])->name('lines.destroy');
    });

    // Accounting Executive Dashboard
    Route::prefix('accounting/executive')->name('accounting.executive.')->group(function () use ($acctExec) {
        Route::get('/', [$acctExec, 'index'])->name('index');
        Route::get('revenue', [$acctExec, 'revenue'])->name('revenue');
        Route::get('profit', [$acctExec, 'profit'])->name('profit');
        Route::get('cash', [$acctExec, 'cash'])->name('cash');
        Route::get('insights', [$acctExec, 'insights'])->name('insights');
        Route::get('hr', [$acctExec, 'hr'])->name('hr');
        Route::get('sales-funnel', [$acctExec, 'salesFunnel'])->name('sales-funnel');
        Route::get('departments', [$acctExec, 'departments'])->name('departments');
    });

    // Accounting Fiscal Years
    Route::prefix('accounting/fiscal-years')->name('accounting.fiscal-years.')->group(function () use ($acctFiscal) {
        Route::post('{fiscalYear}/close', [$acctFiscal, 'close'])->name('close');
        Route::post('{fiscalYear}/reopen', [$acctFiscal, 'reopen'])->name('reopen');
    });

    // Accounting Payables
    Route::prefix('accounting/payables')->name('accounting.payables.')->group(function () use ($acctPayable) {
        Route::get('/', [$acctPayable, 'index'])->name('index');
        Route::get('statement', [$acctPayable, 'statement'])->name('statement');
    });

    // Accounting Receivables
    Route::prefix('accounting/receivables')->name('accounting.receivables.')->group(function () use ($acctReceivable) {
        Route::get('/', [$acctReceivable, 'index'])->name('index');
        Route::get('statement', [$acctReceivable, 'statement'])->name('statement');
    });

    // Accounting Reports
    Route::prefix('accounting/reports')->name('accounting.reports.')->group(function () use ($acctReport) {
        Route::get('cash-bank', [$acctReport, 'cashBank'])->name('cash-bank');
        Route::get('payables', [$acctReport, 'payables'])->name('payables');
        Route::get('receivables', [$acctReport, 'receivables'])->name('receivables');
    });

    // Accounting Periods
    Route::prefix('accounting/periods')->name('accounting.periods.')->group(function () use ($acctPeriod) {
        Route::post('{period}/close', [$acctPeriod, 'close'])->name('close');
        Route::post('{period}/reopen', [$acctPeriod, 'reopen'])->name('reopen');
    });

    // Accounting Security
    Route::prefix('accounting/security')->name('accounting.security.')->group(function () use ($acctSecurity) {
        Route::get('/', [$acctSecurity, 'index'])->name('index');
        Route::get('audit-logs', [$acctSecurity, 'auditLogs'])->name('audit-logs');
    });

    // ─── INVENTORY ─────────────────────────────────────────────────────────
    $invItem = \App\Http\Controllers\Inventory\InventoryItemController::class;
    $invWh = \App\Http\Controllers\Inventory\InventoryWarehouseController::class;
    $invAdj = \App\Http\Controllers\Inventory\InventoryAdjustmentController::class;
    $invTrf = \App\Http\Controllers\Inventory\InventoryTransferController::class;

    // Inventory Items
    Route::prefix('inventory/items')->name('inventory.items.')->group(function () use ($invItem) {
        Route::get('/', [$invItem, 'index'])->name('index');
        Route::get('create', [$invItem, 'create'])->name('create');
        Route::post('/', [$invItem, 'store'])->name('store');
        Route::get('{item}', [$invItem, 'show'])->name('show');
        Route::get('{item}/edit', [$invItem, 'edit'])->name('edit');
        Route::put('{item}', [$invItem, 'update'])->name('update');
        Route::delete('{item}', [$invItem, 'destroy'])->name('destroy');
    });

    // Inventory Stock Ledger
    Route::get('inventory/stock-ledger', [$invItem, 'stockLedger'])->name('inventory.stock-ledger');

    // Inventory Batches
    Route::get('inventory/batches', [$invItem, 'batchTracker'])->name('inventory.batches');

    // Inventory Barcode Search
    Route::get('inventory/barcode-search', [$invItem, 'barcodeSearch'])->name('inventory.barcode-search');

    // Inventory Warehouses
    Route::prefix('inventory/warehouses')->name('inventory.warehouses.')->group(function () use ($invWh) {
        Route::get('/', [$invWh, 'index'])->name('index');
        Route::get('create', [$invWh, 'create'])->name('create');
        Route::post('/', [$invWh, 'store'])->name('store');
        Route::get('{warehouse}', [$invWh, 'show'])->name('show');
        Route::get('{warehouse}/edit', [$invWh, 'edit'])->name('edit');
        Route::put('{warehouse}', [$invWh, 'update'])->name('update');
        Route::delete('{warehouse}', [$invWh, 'destroy'])->name('destroy');
    });

    // Inventory Adjustments
    Route::prefix('inventory/adjustments')->name('inventory.adjustments.')->group(function () use ($invAdj) {
        Route::get('/', [$invAdj, 'index'])->name('index');
        Route::get('create', [$invAdj, 'create'])->name('create');
        Route::post('/', [$invAdj, 'store'])->name('store');
        Route::get('{adjustment}', [$invAdj, 'show'])->name('show');
    });

    // Inventory Transfers
    Route::prefix('inventory/transfers')->name('inventory.transfers.')->group(function () use ($invTrf) {
        Route::get('/', [$invTrf, 'index'])->name('index');
        Route::get('create', [$invTrf, 'create'])->name('create');
        Route::post('/', [$invTrf, 'store'])->name('store');
        Route::get('{transfer}', [$invTrf, 'show'])->name('show');
    });

    // ─── FIXED ASSETS ──────────────────────────────────────────────────────
    $faAsset = \App\Http\Controllers\FixedAsset\FixedAssetController::class;
    $faCat = \App\Http\Controllers\FixedAsset\AssetCategoryController::class;
    $faDepr = \App\Http\Controllers\FixedAsset\AssetDepreciationController::class;
    $faReport = \App\Http\Controllers\FixedAsset\AssetReportController::class;

    // Fixed Assets
    Route::prefix('fixed-assets/assets')->name('fixed_assets.assets.')->group(function () use ($faAsset) {
        Route::get('/', [$faAsset, 'index'])->name('index');
        Route::get('create', [$faAsset, 'create'])->name('create');
        Route::post('/', [$faAsset, 'store'])->name('store');
        Route::get('{asset}', [$faAsset, 'show'])->name('show');
        Route::get('{asset}/edit', [$faAsset, 'edit'])->name('edit');
        Route::put('{asset}', [$faAsset, 'update'])->name('update');
        Route::post('{asset}/capitalize', [$faAsset, 'capitalize'])->name('capitalize');
    });

    // Fixed Asset Categories
    Route::prefix('fixed-assets/categories')->name('fixed_assets.categories.')->group(function () use ($faCat) {
        Route::get('/', [$faCat, 'index'])->name('index');
        Route::get('create', [$faCat, 'create'])->name('create');
        Route::post('/', [$faCat, 'store'])->name('store');
        Route::get('{category}/edit', [$faCat, 'edit'])->name('edit');
        Route::put('{category}', [$faCat, 'update'])->name('update');
    });

    // Fixed Asset Depreciation
    Route::prefix('fixed-assets/depreciation')->name('fixed_assets.depreciation.')->group(function () use ($faDepr) {
        Route::get('/', [$faDepr, 'index'])->name('index');
        Route::post('run', [$faDepr, 'run'])->name('run');
        Route::get('{depreciation}', [$faDepr, 'show'])->name('show');
    });

    // Fixed Asset Reports
    Route::get('fixed-assets/reports/register', [$faReport, 'register'])->name('fixed_assets.reports.register');

    // ─── CURRICULA ─────────────────────────────────────────────────────────
    $curric = \App\Http\Controllers\CurriculumController::class;

    Route::prefix('curricula')->name('curricula.')->middleware('domain:professional')->group(function () use ($curric) {
        Route::get('/', [$curric, 'index'])->middleware('permission:curriculum.view')->name('index');
        Route::get('create', [$curric, 'create'])->middleware('permission:curriculum.manage')->name('create');
        Route::post('/', [$curric, 'store'])->middleware('permission:curriculum.manage')->name('store');
        Route::get('{curriculum}', [$curric, 'show'])->middleware('permission:curriculum.view')->name('show');
        Route::get('{curriculum}/edit', [$curric, 'edit'])->middleware('permission:curriculum.manage')->name('edit');
        Route::put('{curriculum}', [$curric, 'update'])->middleware('permission:curriculum.manage')->name('update');
        Route::post('{curriculum}/activate', [$curric, 'activate'])->middleware('permission:curriculum.manage')->name('activate');
        Route::delete('{curriculum}', [$curric, 'destroy'])->middleware('permission:curriculum.manage')->name('destroy');
        // Modules
        Route::post('{curriculum}/modules', [$curric, 'storeModule'])->middleware('permission:curriculum.manage')->name('modules.store');
        Route::put('{curriculum}/modules/{module}', [$curric, 'updateModule'])->middleware('permission:curriculum.manage')->name('modules.update');
        Route::delete('{curriculum}/modules/{module}', [$curric, 'destroyModule'])->middleware('permission:curriculum.manage')->name('modules.destroy');
        // Lessons
        Route::post('{curriculum}/lessons', [$curric, 'storeLesson'])->middleware('permission:curriculum.manage')->name('lessons.store');
        Route::put('{curriculum}/lessons/{lesson}', [$curric, 'updateLesson'])->middleware('permission:curriculum.manage')->name('lessons.update');
        Route::delete('{curriculum}/lessons/{lesson}', [$curric, 'destroyLesson'])->middleware('permission:curriculum.manage')->name('lessons.destroy');
    });

    // ─── COURSES (institute) ───────────────────────────────────────────────
    $course = \App\Http\Controllers\CourseController::class;
    $courseMaster = \App\Http\Controllers\CourseMasterController::class;
    $courseMat = \App\Http\Controllers\CourseMaterialController::class;

    // Course Management
    $courseCatMgr = \App\Http\Controllers\CourseCategoryManageController::class;
    $courseSubCatMgr = \App\Http\Controllers\CourseSubCategoryManageController::class;
    $subjectMgmt = \App\Http\Controllers\SubjectManagementController::class;
    Route::prefix('courses/manage')->name('courses.manage.')->middleware('domain:professional')->group(function () use ($courseMaster, $courseMat, $courseCatMgr, $courseSubCatMgr, $subjectMgmt) {
        Route::get('/', [$courseMaster, 'index'])->middleware('permission:courses.view')->name('index');
        Route::get('create', [$courseMaster, 'create'])->middleware('permission:courses.view')->name('create');
        Route::post('/', [$courseMaster, 'store'])->middleware('permission:courses.manage')->name('store');
        Route::get('{course}/edit', [$courseMaster, 'edit'])->middleware('permission:courses.view')->name('edit');
        Route::put('{course}', [$courseMaster, 'update'])->middleware('permission:courses.manage')->name('update');
        Route::delete('{course}', [$courseMaster, 'destroy'])->middleware('permission:courses.manage')->name('destroy');
        Route::post('{course}/materials', [$courseMat, 'store'])->middleware('permission:courses.manage')->name('materials.store');
        Route::delete('{course}/materials/{material}', [$courseMat, 'destroy'])->middleware('permission:courses.manage')->name('materials.destroy');
        // Category management (institute-scoped, tenant) — RBAC protected
        Route::prefix('categories')->name('categories.')->group(function () use ($courseCatMgr) {
            Route::get('/', [$courseCatMgr, 'index'])->middleware('permission:courses.view')->name('index');
            Route::post('/', [$courseCatMgr, 'store'])->middleware('permission:courses.manage')->name('store');
            Route::put('/{category}', [$courseCatMgr, 'update'])->middleware('permission:courses.manage')->name('update');
            Route::delete('/{category}', [$courseCatMgr, 'destroy'])->middleware('permission:courses.manage')->name('destroy');
        });
        // Sub Category management (institute-scoped, tenant) — RBAC protected
        Route::prefix('sub-categories')->name('sub-categories.')->group(function () use ($courseSubCatMgr) {
            Route::get('/', [$courseSubCatMgr, 'index'])->middleware('permission:courses.view')->name('index');
            Route::post('/', [$courseSubCatMgr, 'store'])->middleware('permission:courses.manage')->name('store');
            Route::put('/{subCategory}', [$courseSubCatMgr, 'update'])->middleware('permission:courses.manage')->name('update');
            Route::delete('/{subCategory}', [$courseSubCatMgr, 'destroy'])->middleware('permission:courses.manage')->name('destroy');
        });
        // Subject Management (canonical)
        Route::prefix('subjects')->name('subjects.')->group(function () use ($subjectMgmt) {
            Route::get('/', [$subjectMgmt, 'index'])->middleware('permission:courses.view')->name('index');
            Route::get('create', [$subjectMgmt, 'create'])->middleware('permission:courses.manage')->name('create');
            Route::post('/', [$subjectMgmt, 'store'])->middleware('permission:courses.manage')->name('store');
            Route::get('{subject}/edit', [$subjectMgmt, 'edit'])->middleware('permission:courses.manage')->name('edit');
            Route::put('{subject}', [$subjectMgmt, 'update'])->middleware('permission:courses.manage')->name('update');
            Route::delete('{subject}', [$subjectMgmt, 'destroy'])->middleware('permission:courses.manage')->name('destroy');
            Route::post('{subject}/restore', [$subjectMgmt, 'restore'])->middleware('permission:courses.manage')->name('restore');
            Route::get('{subject}/dependencies', [$subjectMgmt, 'dependencies'])->middleware('permission:courses.view')->name('dependencies');
        });
    });

    // ─── TRAINING ENROLLMENTS & SETTINGS (domain:professional) ───────────────
    Route::prefix('training')->name('training.')->middleware('domain:professional')->group(function () {
        Route::resource('enrollments', \App\Http\Controllers\Training\EnrollmentController::class)->only(['index','create','store','update','destroy'])->names('enrollments');
        Route::get('settings', [\App\Http\Controllers\Training\SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\Training\SettingController::class, 'update'])->name('settings.update');
        Route::get('certificates', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'index'])->name('certificates.index');
        Route::post('certificates/generate', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'generate'])->name('certificates.generate');
        Route::get('exams', [\App\Http\Controllers\Training\TrainingExamController::class, 'index'])->name('exams.index');
        Route::get('attendance', [\App\Http\Controllers\Training\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance', [\App\Http\Controllers\Training\AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('attendance/bulk', [\App\Http\Controllers\Training\AttendanceController::class, 'bulkStore'])->name('attendance.bulk.store');
        Route::get('marks', [\App\Http\Controllers\Training\MarksController::class, 'index'])->name('marks.index');
        Route::post('marks', [\App\Http\Controllers\Training\MarksController::class, 'store'])->name('marks.store');
        Route::get('results', [\App\Http\Controllers\Training\ResultsController::class, 'index'])->name('results.index');
        Route::post('results/publish/{batch}', [\App\Http\Controllers\Training\TrainingResultController::class, 'publish'])->name('results.publish');
        Route::post('results/re-evaluate/{batch}', [\App\Http\Controllers\Training\TrainingResultController::class, 'reEvaluate'])->name('results.re-evaluate');
        Route::get('results/marksheet/{batch}/{trainee}', [\App\Http\Controllers\Training\TrainingResultController::class, 'downloadMarksheet'])->name('results.marksheet');
        Route::get('certificates/{certificate}', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'show'])->name('certificates.show');
        Route::get('certificates/{certificate}/download', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'download'])->name('certificates.download');
        Route::get('certificates/{certificate}/qr', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'downloadQr'])->name('certificates.qr');
        Route::put('certificates/{certificate}', [\App\Http\Controllers\Training\TrainingCertificateController::class, 'update'])->name('certificates.update');
        Route::get('fees', [\App\Http\Controllers\Training\FeesController::class, 'index'])->name('fees.index');
        Route::get('reports', [\App\Http\Controllers\Training\ReportsController::class, 'index'])->name('reports.index');
    });

    // Course Subjects (institute)
    Route::prefix('courses/{course}/subjects')->name('courses.subjects.')->group(function () use ($course) {
        Route::get('/', [$course, 'subjects'])->name('index');
        Route::post('/request', [$course, 'requestSubject'])->name('request');
        Route::post('/sync', [$course, 'syncSubjects'])->name('sync');
        Route::put('/{subject}', [$course, 'updateSubject'])->name('update.nested');
    });
    // Global subject request (used by classes/subjects & courses/subjects listing pages where no {course} context exists)
    Route::post('courses/subjects/request', [$course, 'requestSubject'])->name('courses.subjects.request.global');

    Route::get('courses/{course}', [$course, 'show'])->name('courses.show');

    // ─── CLASSES (institute) — academic domain only (domain:academic)
    $cls = \App\Http\Controllers\ClassController::class;

    Route::prefix('classes')->name('classes.')->middleware('domain:academic')->group(function () use ($cls) {
        Route::get('/', [$cls, 'index'])->middleware('permission:courses.view')->name('index');
        Route::get('subjects', [$cls, 'subjects'])->middleware('permission:courses.view')->name('subjects');
        Route::get('batches', [$cls, 'batches'])->middleware('permission:batches.view')->name('batches');
        Route::get('archive', [$cls, 'archive'])->middleware('permission:batches.view')->name('archive');
    });

    // ─── BATCHES (extra routes) ────────────────────────────────────────────
    $batch = \App\Http\Controllers\BatchController::class;

    Route::prefix('batches')->name('batches.')->middleware('domain:professional')->group(function () use ($batch) {
        Route::post('{batch}/status', [$batch, 'changeStatus'])->name('status');
        Route::post('{batch}/transfer', [$batch, 'transferStudent'])->name('transfer');
        Route::post('{batch}/remove-student', [$batch, 'removeStudent'])->name('remove-student');
    });

    // ─── ADMISSIONS ────────────────────────────────────────────────────────
    $adm = \App\Http\Controllers\AdmissionController::class;
    $admPipe = \App\Http\Controllers\AdmissionPipelineController::class;

    Route::prefix('admissions')->name('admissions.')->group(function () use ($adm, $admPipe) {
        Route::get('/', [$adm, 'index'])->name('index');
        Route::get('create', [$adm, 'create'])->name('create');
        Route::post('/', [$adm, 'store'])->name('store');
        // Pipeline (must come BEFORE {admission} wildcard)
        Route::get('pipeline', [$admPipe, 'index'])->name('pipeline');
        Route::get('pipeline/report', [$admPipe, 'report'])->name('pipeline.report');
        Route::post('pipeline', [$admPipe, 'store'])->name('pipeline.store');
        Route::post('pipeline/convert', [$admPipe, 'convert'])->name('pipeline.convert');
        Route::get('pipeline/students', [$admPipe, 'searchStudents'])->name('pipeline.students');
        Route::post('pipeline/link', [$admPipe, 'link'])->name('pipeline.link');
        // Approval workflow (must come BEFORE {admission} wildcard)
        Route::get('pending', [$adm, 'pending'])->middleware('permission:admission.approve')->name('pending');
        Route::get('{student}/review', [$adm, 'review'])->middleware('permission:admission.approve')->name('review');
        Route::post('{student}/approve', [$adm, 'approve'])->middleware('permission:admission.approve')->name('approve');
        Route::post('{student}/reject', [$adm, 'reject'])->middleware('permission:admission.approve')->name('reject');
        // Wildcard routes (must come AFTER specific routes)
        Route::get('{student}', [$adm, 'show'])->name('show');
        Route::get('{student}/edit', [$adm, 'edit'])->name('edit');
        Route::put('{student}', [$adm, 'update'])->name('update');
        Route::post('{student}/transition', [$adm, 'transition'])->name('transition');
    });

    // ─── ALUMNI ────────────────────────────────────────────────────────────
    $alumni = \App\Http\Controllers\Alumni\AlumniController::class;

    Route::prefix('alumni')->name('alumni.')->group(function () use ($alumni) {
        Route::get('/', [$alumni, 'index'])->name('index');
        Route::get('directory', [$alumni, 'directory'])->name('directory');
        Route::get('create', [$alumni, 'create'])->name('create');
        Route::get('students/search', [$alumni, 'searchStudents'])->name('students.search');
        Route::post('/', [$alumni, 'store'])->name('store');
        Route::get('{alumnus}', [$alumni, 'show'])->name('show');
        Route::get('{alumnus}/edit', [$alumni, 'edit'])->name('edit');
        Route::put('{alumnus}', [$alumni, 'update'])->name('update');
        Route::delete('{alumnus}', [$alumni, 'destroy'])->name('destroy');
        Route::get('directory/export', [$alumni, 'export'])->name('directory.export');
        Route::get('reports', [$alumni, 'reports'])->name('reports');
    });

    // ─── CALENDAR ──────────────────────────────────────────────────────────
    $cal = \App\Http\Controllers\CalendarController::class;

    Route::prefix('calendar')->name('calendar.')->group(function () use ($cal) {
        Route::get('/', [$cal, 'index'])->name('index');
        Route::get('timetable', [$cal, 'timetable'])->name('timetable');
        Route::post('events', [$cal, 'store'])->name('events.store');
        Route::get('events/{event}', [$cal, 'show'])->name('events.show');
        Route::put('events/{event}', [$cal, 'update'])->name('events.update');
        Route::delete('events/{event}', [$cal, 'destroy'])->name('events.destroy');
    });

    // ─── DOCUMENTS ─────────────────────────────────────────────────────────
    $doc = \App\Http\Controllers\DocumentController::class;

    Route::prefix('documents')->name('documents.')->group(function () use ($doc) {
        Route::get('/', [$doc, 'index'])->name('index');
        Route::post('/', [$doc, 'store'])->name('store');
        Route::get('categories', [$doc, 'categories'])->name('categories');
        Route::get('{document}/download', [$doc, 'download'])->name('download');
        Route::put('{document}', [$doc, 'update'])->name('update');
        Route::post('{document}/replace', [$doc, 'replace'])->name('replace');
        Route::post('{document}/archive', [$doc, 'archive'])->name('archive');
        Route::post('{document}/restore', [$doc, 'restore'])->name('restore');
        Route::delete('{document}', [$doc, 'destroy'])->name('destroy');
        Route::post('{document}/force-delete', [$doc, 'forceDestroy'])->name('force-delete');
        Route::post('{document}/verify', [$doc, 'verify'])->name('verify');
        Route::post('{document}/reject', [$doc, 'reject'])->name('reject');
        Route::get('{document}/versions', [$doc, 'versions'])->name('versions');
    });

    // ─── EXAMS (extra routes) ──────────────────────────────────────────────
    Route::post('exams/{exam}/marks', [\App\Http\Controllers\ExamController::class, 'saveMarks'])->name('exams.marks');

    // ─── TEACHERS ──────────────────────────────────────────────────────────
    $teacher = \App\Http\Controllers\TeacherController::class;

    Route::prefix('teachers')->name('teachers.')->group(function () use ($teacher) {
        Route::get('create', [$teacher, 'create'])->name('create');
        Route::post('/', [$teacher, 'store'])->name('store');
        Route::get('{teacher}', [$teacher, 'show'])->name('show');
        Route::get('{teacher}/edit', [$teacher, 'edit'])->name('edit');
        Route::put('{teacher}', [$teacher, 'update'])->name('update');
        Route::post('{teacher}/status', [$teacher, 'status'])->name('status');
        Route::post('{teacher}/assign', [$teacher, 'assign'])->name('assign');
        Route::post('{teacher}/complete', [$teacher, 'complete'])->name('complete');
        Route::post('{teacher}/remove', [$teacher, 'remove'])->name('remove');
    });

    // ─── STUDENTS (extra routes) — academic-only transcripts/history (domain:academic)
    Route::get('students/{student}/academic-history', [\App\Http\Controllers\StudentController::class, 'academicHistory'])->middleware('domain:academic')->name('students.academic-history');
    Route::get('students/{student}/academic-attendance', [\App\Http\Controllers\StudentController::class, 'academicAttendance'])->middleware('domain:academic')->name('students.academic-attendance');
    Route::get('students/{student}/academic-transcript', [\App\Http\Controllers\StudentController::class, 'academicTranscript'])->middleware('domain:academic')->name('students.academic-transcript');
    Route::post('students/{student}/academic-transfer', [\App\Http\Controllers\StudentController::class, 'transfer'])->middleware('domain:academic')->name('students.academic-transfer');
    Route::post('students/{student}/academic-withdraw', [\App\Http\Controllers\StudentController::class, 'withdraw'])->middleware('domain:academic')->name('students.academic-withdraw');
    Route::post('students/{student}/certificate-request', [\App\Http\Controllers\CertificateController::class, 'request'])->middleware('domain:academic')->name('students.certificate-request');
    Route::post('certificates/{certificate}/action', [\App\Http\Controllers\CertificateController::class, 'action'])->middleware('domain:academic')->whereNumber('certificate')->name('certificates.action');

    // ─── ACADEMIC ATTENDANCE ───────────────────────────────────────────────
    $acadAtt = \App\Http\Controllers\AcademicAttendanceController::class;
    $acadAttRep = \App\Http\Controllers\AcademicAttendanceReportController::class;

    Route::prefix('academic-attendance')->name('academic-attendance.')->middleware('domain:academic')->group(function () use ($acadAtt, $acadAttRep) {
        Route::post('mark', [$acadAtt, 'store'])->name('mark.store');
        Route::get('reports/class', [$acadAttRep, 'classReport'])->name('reports.class');
        Route::get('reports/daily', [$acadAttRep, 'daily'])->name('reports.daily');
        Route::get('reports/student', [$acadAttRep, 'student'])->name('reports.student');
        Route::get('reports/export/class', [$acadAttRep, 'exportClass'])->name('reports.export.class');
        Route::get('reports/export/daily', [$acadAttRep, 'exportDaily'])->name('reports.export.daily');
        Route::get('reports/export/student', [$acadAttRep, 'exportStudent'])->name('reports.export.student');
    });

    // ─── ACADEMIC ANALYTICS ────────────────────────────────────────────────
    $acadAn = \App\Http\Controllers\AcademicAnalyticsController::class;

    Route::prefix('academic/analytics')->name('academic.analytics.')->middleware('domain:academic')->group(function () use ($acadAn) {
        Route::get('students', [$acadAn, 'students'])->name('students');
        Route::get('students/export', [$acadAn, 'studentsExport'])->name('students.export');
        Route::get('courses', [$acadAn, 'courses'])->name('courses');
        Route::get('courses/export', [$acadAn, 'coursesExport'])->name('courses.export');
        Route::get('batches/export', [$acadAn, 'batchesExport'])->name('batches.export');
        Route::get('attendance', [$acadAn, 'attendance'])->name('attendance');
        Route::get('attendance/export', [$acadAn, 'attendanceExport'])->name('attendance.export');
        Route::get('results/export', [$acadAn, 'resultsExport'])->name('results.export');
        Route::get('promotions', [$acadAn, 'promotions'])->name('promotions');
        Route::get('promotions/export', [$acadAn, 'promotionsExport'])->name('promotions.export');
        Route::get('completion/export', [$acadAn, 'completionExport'])->name('completion.export');
        Route::get('certificates/export', [$acadAn, 'certificatesExport'])->name('certificates.export');
        Route::get('finance', [$acadAn, 'finance'])->name('finance');
        Route::get('finance/export', [$acadAn, 'financeExport'])->name('finance.export');
        Route::get('crm', [$acadAn, 'crm'])->name('crm');
        Route::get('crm/export', [$acadAn, 'crmExport'])->name('crm.export');
    });

    // ─── SETTINGS (academic) ───────────────────────────────────────────────
    $setAcadStruct = \App\Http\Controllers\AcademicStructureController::class;
    $setAcadGrading = \App\Http\Controllers\AcademicGradingController::class;
    $setAcadAgg = \App\Http\Controllers\AcademicAggregationController::class;
    $setAcadAssess = \App\Http\Controllers\AcademicAssessmentController::class;
    $setAcadFinal = \App\Http\Controllers\AcademicFinalResultController::class;
    $setAcadPromo = \App\Http\Controllers\AcademicPromotionController::class;
    $setAcadPlc = \App\Http\Controllers\StudentAcademicPlacementController::class;
    $setAcadMarks = \App\Http\Controllers\AcademicMarksController::class;

    // Settings - Academic Structure (education.manage required; promotions require promotion.manage) — academic domain enforced
    Route::prefix('settings/academic')->name('settings.academic.')->middleware(['permission:education.manage', 'domain:academic'])->group(function () use ($setAcadStruct, $setAcadGrading, $setAcadAgg, $setAcadAssess, $setAcadFinal, $setAcadPromo, $setAcadPlc, $setAcadMarks) {
        Route::get('/', [$setAcadStruct, 'index'])->name('index');
        Route::match(['put', 'post'], 'label', [$setAcadStruct, 'updateLabel'])->name('label');

        // Levels
        Route::post('levels', [$setAcadStruct, 'storeLevel'])->name('levels.store');
        Route::put('levels/{level}', [$setAcadStruct, 'updateLevel'])->name('levels.update');
        Route::delete('levels/{level}', [$setAcadStruct, 'destroyLevel'])->name('levels.destroy');

        // Classes
        Route::post('classes', [$setAcadStruct, 'storeClass'])->name('classes.store');
        Route::put('classes/{class}', [$setAcadStruct, 'updateClass'])->name('classes.update');
        Route::delete('classes/{class}', [$setAcadStruct, 'destroyClass'])->name('classes.destroy');

        // Groups
        Route::post('groups', [$setAcadStruct, 'storeGroup'])->name('groups.store');
        Route::put('groups/{group}', [$setAcadStruct, 'updateGroup'])->name('groups.update');
        Route::delete('groups/{group}', [$setAcadStruct, 'destroyGroup'])->name('groups.destroy');

        // Grading
        Route::get('grading', [$setAcadGrading, 'index'])->name('grading.index');
        Route::get('grading/create', [$setAcadGrading, 'create'])->name('grading.create');
        Route::post('grading', [$setAcadGrading, 'store'])->name('grading.store');
        Route::get('grading/{grading}/edit', [$setAcadGrading, 'edit'])->name('grading.edit');
        Route::put('grading/{grading}', [$setAcadGrading, 'update'])->name('grading.update');
        Route::delete('grading/{grading}', [$setAcadGrading, 'destroy'])->name('grading.destroy');
        Route::get('grading/preview', [$setAcadGrading, 'preview'])->name('grading.preview');

        // Aggregations
        Route::get('aggregations', [$setAcadAgg, 'index'])->name('aggregations.index');
        Route::get('aggregations/create', [$setAcadAgg, 'create'])->name('aggregations.create');
        Route::post('aggregations', [$setAcadAgg, 'store'])->name('aggregations.store');
        Route::get('aggregations/{aggregation}', [$setAcadAgg, 'show'])->name('aggregations.show');
        Route::get('aggregations/{aggregation}/edit', [$setAcadAgg, 'edit'])->name('aggregations.edit');
        Route::put('aggregations/{aggregation}', [$setAcadAgg, 'update'])->name('aggregations.update');
        Route::delete('aggregations/{aggregation}', [$setAcadAgg, 'destroy'])->name('aggregations.destroy');
        Route::get('aggregations/{aggregation}/assessments', [$setAcadAgg, 'assessments'])->name('aggregations.assessments');

        // Assessments
        Route::get('assessments', [$setAcadAssess, 'index'])->name('assessments.index');
        Route::get('assessments/create', [$setAcadAssess, 'create'])->name('assessments.create');
        Route::post('assessments', [$setAcadAssess, 'store'])->name('assessments.store');
        Route::get('assessments/{assessment}', [$setAcadAssess, 'show'])->name('assessments.show');
        Route::get('assessments/{assessment}/edit', [$setAcadAssess, 'edit'])->name('assessments.edit');
        Route::put('assessments/{assessment}', [$setAcadAssess, 'update'])->name('assessments.update');
        Route::delete('assessments/{assessment}', [$setAcadAssess, 'destroy'])->name('assessments.destroy');
        Route::post('assessments/{assessment}/lock', [$setAcadAssess, 'lock'])->name('assessments.lock');
        Route::post('assessments/{assessment}/unlock', [$setAcadAssess, 'unlock'])->name('assessments.unlock');
        Route::get('assessments/{assessment}/subjects', [$setAcadAssess, 'subjects'])->name('assessments.subjects');
        Route::get('assessments/{assessment}/readiness', [$setAcadFinal, 'readiness'])->name('assessments.readiness');
        Route::get('assessments/{assessment}/readiness/export', [$setAcadFinal, 'readinessExport'])->name('assessments.readiness.export');
        Route::post('assessments/{assessment}/marks', [$setAcadMarks, 'store'])->name('assessments.marks.store');
        Route::get('assessments/{assessment}/marks-sheet', [$setAcadMarks, 'sheet'])->name('assessments.marks-sheet');
        Route::get('assessments/{assessment}/marks-sheet/export', [$setAcadMarks, 'export'])->name('assessments.marks-sheet.export');

        // Final Results
        Route::get('final-results', [$setAcadFinal, 'index'])->name('final-results.index');
        Route::post('final-results', [$setAcadFinal, 'storeResult'])->name('final-results.store');
        Route::get('final-results/{result}', [$setAcadFinal, 'show'])->name('final-results.show');
        Route::post('final-results/{result}/approve', [$setAcadFinal, 'approve'])->name('final-results.approve');
        Route::get('final-results/{result}/report', [$setAcadFinal, 'report'])->name('final-results.report');
        Route::get('final-results/{result}/result-sheet', [$setAcadFinal, 'resultSheet'])->name('final-results.result-sheet');
        Route::post('final-results/{result}/send-to-review', [$setAcadFinal, 'sendToReview'])->name('final-results.send-to-review');
        Route::post('final-results/{result}/lock', [$setAcadFinal, 'lock'])->name('final-results.lock');
        Route::post('final-results/{result}/publish', [$setAcadFinal, 'publish'])->name('final-results.publish');
        Route::get('final-results/{result}/export', [$setAcadFinal, 'export'])->name('final-results.export');
        Route::get('final-results/{result}/readiness', [$setAcadFinal, 'readiness'])->name('final-results.readiness');
        Route::get('final-results/{result}/readiness/export', [$setAcadFinal, 'readinessExport'])->name('final-results.readiness.export');
        Route::get('final-results/{result}/preflight', [$setAcadFinal, 'preflight'])->name('final-results.preflight');
        Route::get('final-results/policy', [$setAcadFinal, 'policy'])->name('final-results.policy');
        Route::put('final-results/policy', [$setAcadFinal, 'updatePolicy'])->name('final-results.policy.update');

        // Promotions — requires promotion.manage in addition to education.manage
        Route::middleware('permission:promotion.manage')->group(function () use ($setAcadPromo) {
            Route::get('promotions', [$setAcadPromo, 'index'])->name('promotions.index');
            Route::get('promotions/policies/{policy}', [$setAcadPromo, 'showPolicy'])->name('promotions.policies.show');
            Route::get('promotions/policies/{policy}/edit', [$setAcadPromo, 'editPolicy'])->name('promotions.policies.edit');
            Route::post('promotions/policies', [$setAcadPromo, 'storePolicy'])->name('promotions.policies.store');
            Route::put('promotions/policies/{policy}', [$setAcadPromo, 'updatePolicy'])->name('promotions.policies.update');
            Route::post('promotions/policies/{policy}/status', [$setAcadPromo, 'setPolicyStatus'])->name('promotions.policies.status');
            Route::post('promotions/rules', [$setAcadPromo, 'storeRule'])->name('promotions.rules.store');
            Route::put('promotions/rules/{rule}', [$setAcadPromo, 'updateRule'])->name('promotions.rules.update');
            Route::delete('promotions/rules/{rule}', [$setAcadPromo, 'destroyRule'])->name('promotions.rules.destroy');
            Route::get('promotions/decisions/{decision}', [$setAcadPromo, 'showDecision'])->name('promotions.decisions.show');
            Route::post('promotions/decisions', [$setAcadPromo, 'storeDecision'])->name('promotions.decisions.store');
            Route::get('promotions/decisions/{decision}/review', [$setAcadPromo, 'reviewDecision'])->name('promotions.decisions.review');
            Route::post('promotions/decisions/{decision}/send-to-review', [$setAcadPromo, 'sendBackToReview'])->name('promotions.decisions.send-to-review');
            Route::post('promotions/decisions/{decision}/approve', [$setAcadPromo, 'approveDecision'])->name('promotions.decisions.approve');
            Route::post('promotions/decisions/{decision}/cancel', [$setAcadPromo, 'cancelDecision'])->name('promotions.decisions.cancel');
            Route::get('promotions/decisions/{decision}/export', [$setAcadPromo, 'export'])->name('promotions.decisions.export');
            Route::get('promotions/decisions/{decision}/sheet', [$setAcadPromo, 'sheet'])->name('promotions.decisions.sheet');
        });

        // Placements
        Route::get('placements', [$setAcadPlc, 'index'])->name('placements.index');
        Route::get('placements/create', [$setAcadPlc, 'create'])->name('placements.create');
        Route::post('placements', [$setAcadPlc, 'store'])->name('placements.store');
        Route::get('placements/{placement}', [$setAcadPlc, 'show'])->name('placements.show');
        Route::get('placements/{placement}/edit', [$setAcadPlc, 'edit'])->name('placements.edit');
        Route::put('placements/{placement}', [$setAcadPlc, 'update'])->name('placements.update');
        Route::delete('placements/{placement}', [$setAcadPlc, 'destroy'])->name('placements.destroy');
        Route::post('placements/{placement}/archive', [$setAcadPlc, 'archive'])->name('placements.archive');
        Route::get('placements/{placement}/subjects', [$setAcadPlc, 'subjects'])->name('placements.subjects');

        // Academic Years — dedicated index extracted from placements anchor (Step 2), backward compatible with old anchor
        Route::get('academic-years', [$setAcadStruct, 'academicYearsIndex'])->name('academic-years.index');
        Route::post('academic-years', [$setAcadPlc, 'storeAcademicYear'])->name('academic-years.store');
        Route::put('academic-years/{academicYear}', [$setAcadPlc, 'updateAcademicYear'])->name('academic-years.update');
        Route::delete('academic-years/{academicYear}', [$setAcadPlc, 'destroyAcademicYear'])->name('academic-years.destroy');

    });

    // ─── SETTINGS (notifications) ──────────────────────────────────────────
    $setNotif = \App\Http\Controllers\NotificationController::class;
    $setNotifTpl = \App\Http\Controllers\NotificationTemplateController::class;
    $setNotifLog = \App\Http\Controllers\NotificationLogController::class;

    Route::prefix('settings/notifications')->name('settings.notifications.')->group(function () use ($setNotif, $setNotifTpl, $setNotifLog) {
        Route::get('/', [$setNotif, 'index'])->name('index');
        Route::put('channels', [$setNotif, 'updateChannels'])->name('channels');
        // Non-destructive alias: form does POST without @method('PUT'), keep PUT canonical and add POST alias
        Route::post('channels', [$setNotif, 'updateChannels'])->name('channels.post');
        // Non-destructive fix: GET must NOT mutate (old route called update on GET which would wipe prefs). Keep GET as redirect, handle mutations via POST/PUT.
        Route::get('preferences', function () { return redirect()->route('settings.notifications.index'); })->name('preferences');
        Route::post('preferences', [\App\Http\Controllers\NotificationPreferenceController::class, 'update'])->name('preferences.post');
        Route::put('preferences', [\App\Http\Controllers\NotificationPreferenceController::class, 'update'])->name('preferences.put');
        Route::patch('preferences', [\App\Http\Controllers\NotificationPreferenceController::class, 'update'])->name('preferences.patch');

        // Templates
        Route::get('templates', [$setNotifTpl, 'index'])->name('templates.index');
        Route::get('templates/create', [$setNotifTpl, 'create'])->name('templates.create');
        Route::post('templates', [$setNotifTpl, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [$setNotifTpl, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [$setNotifTpl, 'update'])->name('templates.update');
        Route::post('templates/{template}/toggle', [$setNotifTpl, 'toggle'])->name('templates.toggle');
        Route::delete('templates/{template}', [$setNotifTpl, 'destroy'])->name('templates.destroy');

        // Logs
        Route::get('logs', [$setNotifLog, 'index'])->name('logs.index');
        Route::get('logs/{log}', [$setNotifLog, 'show'])->name('logs.show');
        Route::post('logs/{log}/retry', [$setNotifLog, 'retry'])->name('logs.retry');
    });

    // ─── SETTINGS (general) ────────────────────────────────────────────────
    Route::put('settings/appearance', [\App\Http\Controllers\InstituteSettingController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::put('settings/general', [\App\Http\Controllers\InstituteSettingController::class, 'updateGeneral'])->name('settings.general.update');
    Route::put('settings/password', [\App\Http\Controllers\InstituteSettingController::class, 'updatePassword'])->name('settings.password');

    // ─── WORKFLOWS ─────────────────────────────────────────────────────────
    $wf = \App\Http\Controllers\WorkflowController::class;

    Route::prefix('workflows')->name('workflows.')->group(function () use ($wf) {
        Route::get('create', [$wf, 'create'])->name('create');
        Route::post('/', [$wf, 'store'])->name('store');
        Route::get('{workflow}', [$wf, 'show'])->name('show');
        Route::post('{workflow}/transition', [$wf, 'transition'])->name('transition');
        Route::post('{workflow}/approve-step', [$wf, 'approveStep'])->name('approve-step');
        Route::post('{workflow}/reject-step', [$wf, 'rejectStep'])->name('reject-step');
    });

    // ─── RECYCLE BIN ───────────────────────────────────────────────────────
    $recycle = \App\Http\Controllers\RecycleBinController::class;

    Route::prefix('recycle')->name('recycle.')->group(function () use ($recycle) {
        Route::post('students/{student}/restore', [$recycle, 'restore'])->withTrashed()->name('students.restore');
        Route::post('students/{student}/force-delete', [$recycle, 'forceDelete'])->withTrashed()->name('students.force-delete');
        Route::post('batches/{batch}/restore', [$recycle, 'restoreBatch'])->withTrashed()->name('batches.restore');
        Route::post('batches/{batch}/force-delete', [$recycle, 'forceDeleteBatch'])->withTrashed()->name('batches.force-delete');
    });

    // ─── CERTIFICATE TYPES ─────────────────────────────────────────────────
    $certType = \App\Http\Controllers\CertificateTypeController::class;

    Route::prefix('certificate-types')->name('certificate-types.')->group(function () use ($certType) {
        Route::get('/', [$certType, 'index'])->name('index');
        Route::get('create', [$certType, 'create'])->name('create');
        Route::post('/', [$certType, 'store'])->name('store');
        Route::get('{certificateType}/edit', [$certType, 'edit'])->name('edit');
        Route::put('{certificateType}', [$certType, 'update'])->name('update');
        Route::delete('{certificateType}', [$certType, 'destroy'])->name('destroy');
    });

    // ─── ADMIN CERTIFICATES (extra) ────────────────────────────────────────
    $adminCert = \App\Http\Controllers\Admin\CertificateAdminController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminCert) {
        Route::get('certificates/requests', [$adminCert, 'requests'])->name('certificates.requests');
        Route::get('certificates/requests-columns', [$adminCert, 'saveRequestsColumns'])->name('certificates.requests-columns');
        Route::get('certificates/columns', [$adminCert, 'saveColumns'])->name('certificates.columns');
        Route::get('certificates/{certificate}', [$adminCert, 'show'])->name('certificates.show');
        Route::get('certificates/{certificate}/qr', [$adminCert, 'downloadQr'])->name('certificates.qr');
        Route::delete('certificates/{certificate}', [$adminCert, 'destroy'])->name('certificates.destroy');
        Route::post('certificates/{certificate}/restore', [$adminCert, 'restore'])->name('certificates.restore')->withTrashed();
        Route::delete('certificates/{certificate}/force-delete', [$adminCert, 'forceDelete'])->name('certificates.force-delete')->withTrashed();
    });

    // ─── ADMIN CLASSES (extra) ─────────────────────────────────────────────
    $adminClass = \App\Http\Controllers\Admin\ClassAdminController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminClass) {
        Route::get('classes/columns', [$adminClass, 'saveIndexColumns'])->name('classes.index-columns');
        Route::get('classes/subjects', [$adminClass, 'subjects'])->name('classes.subjects');
        Route::get('classes/subjects-columns', [$adminClass, 'saveSubjectsColumns'])->name('classes.subjects-columns');
        Route::get('classes/batches', [$adminClass, 'batches'])->name('classes.batches');
        Route::get('classes/batches-columns', [$adminClass, 'saveBatchesColumns'])->name('classes.batches-columns');
        Route::get('classes/archive', [$adminClass, 'archive'])->name('classes.archive');
    });

    // ─── ADMIN COURSES (extra) ─────────────────────────────────────────────
    $adminCourse = \App\Http\Controllers\Admin\CourseAdminController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminCourse) {
        Route::get('courses/columns', [$adminCourse, 'saveIndexColumns'])->name('courses.index-columns');
        Route::get('courses/{course}/batches', [$adminCourse, 'batches'])->name('courses.batches');
        Route::get('courses/{course}/batches-columns', [$adminCourse, 'saveBatchesColumns'])->name('courses.batches-columns');
        Route::get('courses/archive', [$adminCourse, 'archive'])->name('courses.archive');
    });

    // ─── ADMIN STUDENTS ────────────────────────────────────────────────────
    $adminStud = \App\Http\Controllers\Admin\StudentAdminController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminStud) {
        Route::get('students/columns', [$adminStud, 'saveColumns'])->name('students.columns');
    });

}); // end $tenant — super-admin routes must NOT require institute context

    // ─── ADMIN ACADEMIC (extra) ────────────────────────────────────────────
    $adminAcadStruct = \App\Http\Controllers\Admin\AcademicStructureAdminController::class;
    $adminAcadSubj = \App\Http\Controllers\Admin\AcademicSubjectAdminController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminAcadStruct, $adminAcadSubj) {
        Route::get('academic/country/{country}', [$adminAcadStruct, 'country'])->name('academic.country');
        Route::put('academic/country/{country}', [$adminAcadStruct, 'updateCountry'])->name('academic.country.update');
        Route::get('academic/system/{system}', [$adminAcadStruct, 'system'])->name('academic.system');
        Route::post('academic/country/{country}/systems', [$adminAcadStruct, 'storeSystem'])->name('academic.systems.store');
        Route::put('academic/systems/{system}', [$adminAcadStruct, 'updateSystem'])->name('academic.systems.update');
        Route::post('academic/systems/{system}/toggle', [$adminAcadStruct, 'toggleSystem'])->name('academic.systems.toggle');
        Route::get('academic/level/{level}', [$adminAcadStruct, 'level'])->name('academic.level');
        Route::post('academic/system/{system}/levels', [$adminAcadStruct, 'storeLevel'])->name('academic.levels.store');
        Route::put('academic/levels/{level}', [$adminAcadStruct, 'updateLevel'])->name('academic.levels.update');
        Route::post('academic/levels/{level}/toggle', [$adminAcadStruct, 'toggleLevel'])->name('academic.levels.toggle');
        Route::get('academic/classGrade/{classGrade}', [$adminAcadStruct, 'classGrade'])->name('academic.classGrade');
        Route::post('academic/level/{level}/classes', [$adminAcadStruct, 'storeClass'])->name('academic.classes.store');
        Route::put('academic/classes/{classGrade}', [$adminAcadStruct, 'updateClass'])->name('academic.classes.update');
        Route::post('academic/classes/{classGrade}/toggle', [$adminAcadStruct, 'toggleClass'])->name('academic.classes.toggle');
        Route::post('academic/classGrade/{classGrade}/groups', [$adminAcadStruct, 'storeGroup'])->name('academic.groups.store');
        Route::post('academic/groups/{group}/toggle', [$adminAcadStruct, 'toggleGroup'])->name('academic.groups.toggle');

        // Subjects
        Route::post('academic/subjects', [$adminAcadSubj, 'store'])->name('academic.subjects.store');
        Route::put('academic/subjects/{subject}', [$adminAcadSubj, 'update'])->name('academic.subjects.update');
        Route::post('academic/subjects/{subject}/toggle', [$adminAcadSubj, 'toggle'])->name('academic.subjects.toggle');
        Route::get('academic/subjects/columns', [$adminAcadSubj, 'saveColumns'])->name('academic.subjects.save-columns');
        Route::get('academic/subjects/assign', [$adminAcadSubj, 'assign'])->name('academic.subjects.assign');
        Route::post('academic/subjects/assignments', [$adminAcadSubj, 'storeAssignment'])->name('academic.subjects.assignments.store');
        Route::put('academic/subjects/assignments/{assignment}', [$adminAcadSubj, 'updateAssignment'])->name('academic.subjects.assignments.update');
        Route::delete('academic/subjects/assignments/{assignment}', [$adminAcadSubj, 'destroyAssignment'])->name('academic.subjects.assignments.destroy');
        Route::post('academic/subjects/selection-groups', [$adminAcadSubj, 'storeSelectionGroup'])->name('academic.subjects.selection-groups.store');
        Route::put('academic/subjects/selection-groups/{group}', [$adminAcadSubj, 'updateSelectionGroup'])->name('academic.subjects.selection-groups.update');
        Route::post('academic/subjects/selection-groups/{group}/toggle', [$adminAcadSubj, 'toggleSelectionGroup'])->name('academic.subjects.selection-groups.toggle');
        Route::delete('academic/subjects/selection-groups/{group}', [$adminAcadSubj, 'destroySelectionGroup'])->name('academic.subjects.selection-groups.destroy');
    });

    // ─── ADMIN INSTITUTES (extra) ──────────────────────────────────────────
    $adminInst = \App\Http\Controllers\Admin\InstituteAdminController::class;
    $adminEntitle = \App\Http\Controllers\Admin\InstituteModuleEntitlementController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminInst, $adminEntitle) {
        Route::post('institutes/columns', [$adminInst, 'saveColumns'])->name('institutes.columns');

        // Entitlements
        Route::get('institutes/{institute}/entitlements', [$adminEntitle, 'index'])->name('institutes.entitlements.index');
        Route::get('institutes/{institute}/entitlements/create', [$adminEntitle, 'create'])->name('institutes.entitlements.create');
        Route::post('institutes/{institute}/entitlements', [$adminEntitle, 'store'])->name('institutes.entitlements.store');
        Route::delete('institutes/{institute}/entitlements/{entitlement}', [$adminEntitle, 'destroy'])->name('institutes.entitlements.destroy');
        Route::post('institutes/{institute}/entitlements/{entitlement}/extend', [$adminEntitle, 'extend'])->name('institutes.entitlements.extend');
    });

    // ─── ADMIN GEO ─────────────────────────────────────────────────────────
    $adminGeo = \App\Http\Controllers\Admin\GeoAdminController::class;
    $adminGeoImp = \App\Http\Controllers\Admin\GeoImportController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminGeo, $adminGeoImp) {
        Route::get('geo', [$adminGeo, 'index'])->name('geo.index');
        Route::get('geo/create', [$adminGeo, 'createCountry'])->name('geo.countries.create');
        Route::post('geo/countries', [$adminGeo, 'storeCountry'])->name('geo.countries.store');
        Route::get('geo/{country}/edit', [$adminGeo, 'edit'])->name('geo.edit');
        Route::put('geo/{country}', [$adminGeo, 'update'])->name('geo.update');
        Route::post('geo/{country}/toggle', [$adminGeo, 'toggleStatus'])->name('geo.toggle');
        Route::get('geo/imports', [$adminGeoImp, 'index'])->name('geo.imports');
        Route::post('geo/imports', [$adminGeoImp, 'store'])->name('geo.imports.store');
        Route::post('geo/imports/validate', [$adminGeoImp, 'validatePackage'])->name('geo.imports.validate');
        Route::post('geo/imports/run', [$adminGeoImp, 'run'])->name('geo.imports.run');
        Route::get('geo/imports/status', [$adminGeoImp, 'status'])->name('geo.imports.status');
    });

    // NOTE: admin/industry-settings and admin/themes are defined in routes/web.php
    // outside the tenant scope — do NOT re-define them here.

    // ─── ADMIN USER MODULE ACCESS (per-institute users) ────────────────────
    $adminUserMod = \App\Http\Controllers\Admin\UserModuleAccessController::class;

    Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () use ($adminUserMod) {
        Route::get('institutes/{institute}/users/modules', [$adminUserMod, 'index'])->name('institutes.users.modules.index');
        Route::put('institutes/{institute}/users/modules', [$adminUserMod, 'update'])->name('institutes.users.modules.update');
    });

Route::middleware($tenant)->group(function () {

    // ─── INSTITUTE USER MODULE ACCESS ──────────────────────────────────────
    $instUserMod = \App\Http\Controllers\InstituteUserModuleAccessController::class;

    Route::prefix('institute/users/modules')->name('institute.users.modules.')->group(function () use ($instUserMod) {
        Route::get('/', [$instUserMod, 'index'])->name('index');
        Route::put('/', [$instUserMod, 'update'])->name('update');
    });

    // ─── GEO (public) moved to bottom outside tenant group — see end of file

    // ─── AI ────────────────────────────────────────────────────────────────
    Route::post('ai/assistant/send', [\App\Http\Controllers\Ai\AiAssistantController::class, 'send'])->name('ai.assistant.send');

    // ─── OWNER PROFILE ─────────────────────────────────────────────────────
    $ownerProf = \App\Http\Controllers\OwnerProfileController::class;

    Route::prefix('owner/profile')->name('owner.profile.')->group(function () use ($ownerProf) {
        Route::put('/', [$ownerProf, 'update'])->name('update');
        Route::put('password', [$ownerProf, 'updatePassword'])->name('password');
    });

    // ─── ONLINE PAYMENTS ───────────────────────────────────────────────────
    $opay = \App\Http\Controllers\OnlinePaymentController::class;

    Route::prefix('online-payments')->name('online-payments.')->group(function () use ($opay) {
        Route::get('history', [$opay, 'history'])->name('history');
        Route::post('process', [$opay, 'process'])->name('process');
        Route::get('status/{payment}', [$opay, 'status'])->name('status');
    });

    // ─── REPORTS HUB ───────────────────────────────────────────────────────
    $reportsHub = \App\Http\Controllers\ReportsHubController::class;

    Route::prefix('reports')->name('reports.')->group(function () use ($reportsHub) {
        Route::get('hub', [$reportsHub, 'index'])->name('hub');
        Route::get('hub/{report}', [$reportsHub, 'show'])->name('hub.show');
    });

    // ─── SaaS ──────────────────────────────────────────────────────────────
    $saas = \App\Http\Controllers\SaasCheckoutController::class;

    Route::prefix('saas')->name('saas.')->group(function () use ($saas) {
        Route::get('packages', [$saas, 'packages'])->name('packages');
        Route::get('checkout/form', [$saas, 'checkoutForm'])->name('checkout.form');
        Route::post('checkout', [$saas, 'checkout'])->name('checkout');
        Route::get('checkout/attempt/{attempt}', [$saas, 'attemptShow'])->name('attempt.show');
        Route::get('checkout/callback', [$saas, 'callback'])->name('callback');
    });

    // ─── UI COLUMNS ────────────────────────────────────────────────────────
    Route::post('ui/columns/save', [\App\Http\Controllers\UiPreferenceController::class, 'save'])->name('ui.columns.save');

    // ─── SUPER-ADMIN DATABASE CERTIFICATION ────────────────────────────────
    $dbOps = \App\Http\Controllers\SuperAdmin\DatabaseOperationsController::class;

    Route::get('super-admin/database/certification', [$dbOps, 'certification'])->name('super-admin.database.certification');

    // ─── SALES LEADS EXTRA ────────────────────────────────────────────────
    Route::post('sales/leads/{lead}/convert', [\App\Http\Controllers\Sales\LeadController::class, 'convertToQuotation'])->middleware('module_access:sales')->name('sales.leads.convert');

    // ─── SALES INVOICES EXTRA ──────────────────────────────────────────────
    Route::post('sales/invoices/create', [\App\Http\Controllers\Sales\SalesInvoiceController::class, 'createForOrder'])->middleware('module_access:sales')->name('sales.invoices.create');

    // ─── SALES QUOTATIONS EXTRA ────────────────────────────────────────────
    Route::get('sales/quotations/{quotation}/print', [\App\Http\Controllers\Sales\QuotationController::class, 'print'])->middleware('module_access:sales')->name('sales.quotations.print');

    // ─── SALES DELIVERIES EXTRA ────────────────────────────────────────────
    Route::get('sales/deliveries/{delivery}/invoice', [\App\Http\Controllers\Sales\SalesInvoiceController::class, 'storeForDelivery'])->middleware('module_access:sales')->name('sales.deliveries.invoice');

    // ─── SALES RETURNS EXTRA ───────────────────────────────────────────────
    Route::post('sales/returns/{return}/invoice', [\App\Http\Controllers\Sales\SalesReturnController::class, 'invoiceLines'])->middleware('module_access:sales')->name('sales.returns.invoice');

    // ─── PURCHASE QUOTATIONS EXTRA ─────────────────────────────────────────
    Route::get('purchase/quotations/{quotation}/print', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'print'])->middleware('module_access:purchase')->name('purchase.quotations.print');

    // ─── PURCHASE RECEIPTS EXTRA ───────────────────────────────────────────
    Route::get('purchase/receipts/{receipt}/print', [\App\Http\Controllers\Purchase\GoodsReceiptWebController::class, 'print'])->middleware('module_access:purchase')->name('purchase.receipts.print');

    // ─── PURCHASE INVOICES EXTRA ───────────────────────────────────────────
    Route::get('purchase/invoices/{invoice}/print', [\App\Http\Controllers\Purchase\PurchaseInvoiceController::class, 'print'])->middleware('module_access:purchase')->name('purchase.invoices.print');

    // ─── PURCHASE REPORTS EXTRA ────────────────────────────────────────────
    Route::get('purchase/reports/daily', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'daily'])->middleware('module_access:purchase')->name('purchase.reports.daily');
    Route::get('purchase/reports/export', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'export'])->middleware('module_access:purchase')->name('purchase.reports.export');
    Route::get('purchase/reports/inventory', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'inventoryReconciliation'])->middleware('module_access:purchase')->name('purchase.reports.inventory');
    Route::get('purchase/reports/payable', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'payableReport'])->middleware('module_access:purchase')->name('purchase.reports.payable');
    Route::get('purchase/reports/print', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'print'])->middleware('module_access:purchase')->name('purchase.reports.print');
    Route::get('purchase/reports/product', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'productWise'])->middleware('module_access:purchase')->name('purchase.reports.product');
    Route::get('purchase/reports/supplier', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'supplierWise'])->middleware('module_access:purchase')->name('purchase.reports.supplier');
    Route::get('purchase/reports/supplierStatement', [\App\Http\Controllers\Purchase\PurchaseReportController::class, 'supplierStatement'])->middleware('module_access:purchase')->name('purchase.reports.supplierStatement');

    // ─── PURCHASE CREDIT ───────────────────────────────────────────────────
    Route::post('purchase/credit/adjust', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'adjust'])->middleware('module_access:purchase')->name('purchase.credit.adjust');

    // ─── PURCHASE PAYMENTS EXTRA ───────────────────────────────────────────
    Route::post('purchase/payments/reverse', [\App\Http\Controllers\Purchase\PurchaseInvoiceController::class, 'reversePayment'])->middleware('module_access:purchase')->name('purchase.payments.reverse');

    // ─── SALES LEADS (duplicate fix) ───────────────────────────────────────
    Route::get('sales/leads/{lead}', [\App\Http\Controllers\Sales\LeadController::class, 'show'])->middleware('module_access:sales')->name('sales.leads.show');
    Route::put('sales/leads/{lead}', [\App\Http\Controllers\Sales\LeadController::class, 'update'])->middleware('module_access:sales')->name('sales.leads.update');

    // ─── SALES RETURNS EXTRA ───────────────────────────────────────────────
    Route::get('sales/returns/create', [\App\Http\Controllers\Sales\SalesReturnController::class, 'create'])->middleware('module_access:sales')->name('sales.returns.create');

    // ─── PURCHASE QUOTATIONS EXTRA (duplicate fix) ─────────────────────────
    Route::get('purchase/quotations/{quotation}', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'show'])->middleware('module_access:purchase')->name('purchase.quotations.show');

    // ─── PURCHASE ORDERS EXTRA ─────────────────────────────────────────────
    Route::get('purchase/orders/{order}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])->middleware('module_access:purchase')->name('purchase.orders.show');
    Route::post('purchase/orders/{order}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->middleware('module_access:purchase')->name('purchase.orders.cancel');
    Route::post('purchase/orders/{order}/close', [\App\Http\Controllers\PurchaseOrderController::class, 'close'])->middleware('module_access:purchase')->name('purchase.orders.close');

    // ─── ACADEMIC ATTENDANCE MARK ──────────────────────────────────────────
    Route::post('academic-attendance/mark', [\App\Http\Controllers\AcademicAttendanceController::class, 'store'])->name('academic-attendance.mark.store');

    // ─── WORKFLOW TRANSITION ───────────────────────────────────────────────
    Route::post('workflows/{workflow}/transition', [\App\Http\Controllers\WorkflowController::class, 'transition'])->name('workflows.transition');

    // ─── SETTINGS NOTIFICATIONS EXTRA ──────────────────────────────────────
    Route::get('settings/notifications/templates', [\App\Http\Controllers\NotificationTemplateController::class, 'index'])->name('settings.notifications.templates.index');
    Route::get('settings/notifications/templates/create', [\App\Http\Controllers\NotificationTemplateController::class, 'create'])->name('settings.notifications.templates.create');
    Route::post('settings/notifications/templates', [\App\Http\Controllers\NotificationTemplateController::class, 'store'])->name('settings.notifications.templates.store');
    Route::get('settings/notifications/templates/{template}/edit', [\App\Http\Controllers\NotificationTemplateController::class, 'edit'])->name('settings.notifications.templates.edit');
    Route::put('settings/notifications/templates/{template}', [\App\Http\Controllers\NotificationTemplateController::class, 'update'])->name('settings.notifications.templates.update');
    Route::post('settings/notifications/templates/{template}/toggle', [\App\Http\Controllers\NotificationTemplateController::class, 'toggle'])->name('settings.notifications.templates.toggle');
    Route::delete('settings/notifications/templates/{template}', [\App\Http\Controllers\NotificationTemplateController::class, 'destroy'])->name('settings.notifications.templates.destroy');
    Route::get('settings/notifications/logs', [\App\Http\Controllers\NotificationLogController::class, 'index'])->name('settings.notifications.logs.index');
    Route::get('settings/notifications/logs/{log}', [\App\Http\Controllers\NotificationLogController::class, 'show'])->name('settings.notifications.logs.show');
    Route::post('settings/notifications/logs/{log}/retry', [\App\Http\Controllers\NotificationLogController::class, 'retry'])->name('settings.notifications.logs.retry');

    // ─── HR REPORTS (empty route) ──────────────────────────────────────────
    Route::get('hr/reports', [\App\Http\Controllers\Hr\HrReportController::class, 'index'])->middleware('module_access:hr')->name('hr.reports.');

    // ─── SUPER-ADMIN DATABASE CERTIFICATION ────────────────────────────────
    Route::get('super-admin/database/certification', [\App\Http\Controllers\SuperAdmin\DatabaseOperationsController::class, 'certification'])->name('super-admin.database.certification');

    // ─── REGISTER ──────────────────────────────────────────────────────────
    // NOTE: public Owner registration (GET /register) is defined in routes/web.php as owner.register
    // Do NOT re-define here inside tenant middleware — guest must access it without auth.

    // ─── ROUTE NAME ALIASES (views use different names than controllers) ──
    // Admin Grading aliases (views reference admin.academic.grading.* not settings.academic.grading.*)
    $adminGrading = \App\Http\Controllers\Admin\AcademicGradingAdminController::class;
    Route::get('admin/academic/grading/create', [$adminGrading, 'create'])->name('admin.academic.grading.create');
    Route::post('admin/academic/grading', [$adminGrading, 'store'])->name('admin.academic.grading.store');
    Route::get('admin/academic/grading/{grading}/edit', [$adminGrading, 'edit'])->name('admin.academic.grading.edit');
    Route::put('admin/academic/grading/{grading}', [$adminGrading, 'update'])->name('admin.academic.grading.update');
    Route::delete('admin/academic/grading/{grading}', [$adminGrading, 'destroy'])->name('admin.academic.grading.destroy');

    // Admin Courses Assignment aliases (views use dash not dot)
    $adminCourse = \App\Http\Controllers\Admin\CourseAdminController::class;
    Route::post('admin/courses/assignment/assign', [$adminCourse, 'assignmentAssign'])->name('admin.courses.assignment-assign');
    Route::get('admin/courses/assignment-columns', [$adminCourse, 'saveAssignmentColumns'])->name('admin.courses.assignment-columns');
    Route::post('admin/courses/assignment/remove', [$adminCourse, 'assignmentRemove'])->name('admin.courses.assignment-remove');

    // Exams alias (views use send-to-exam not sendToExam)
    Route::post('exams/send-to-exam/{batch}', [\App\Http\Controllers\ExamController::class, 'sendToExam'])->name('exams.send-to-exam');

    // HR Reports alias (views use hr.reports.index not hr.reports.)
    Route::get('hr/reports', [\App\Http\Controllers\Hr\HrReportController::class, 'index'])->middleware('module_access:hr')->name('hr.reports.index');

    // Purchase Returns alias (views use creditNote not credit-note)
    Route::post('purchase/returns/{return}/credit-note', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'creditNote'])->middleware('module_access:purchase')->name('purchase.returns.creditNote');

    // Learning Structure Engine — generic N-level endpoints (Phase 3)
    $learnCtrl = \App\Http\Controllers\LearningStructureController::class;
    Route::prefix('academic/structure')->name('academic.structure.')->group(function () use ($learnCtrl) {
        Route::get('options', [$learnCtrl, 'options'])->name('options');
        Route::get('nodes', [$learnCtrl, 'nodes'])->name('nodes');
        Route::post('nodes', [$learnCtrl, 'store'])->middleware('permission:education.manage')->name('nodes.store');
        Route::put('nodes/{node}', [$learnCtrl, 'update'])->middleware('permission:education.manage')->name('nodes.update');
        Route::delete('nodes/{node}', [$learnCtrl, 'destroy'])->middleware('permission:education.manage')->name('nodes.destroy');
        Route::post('nodes/{node}/move', [$learnCtrl, 'move'])->middleware('permission:education.manage')->name('nodes.move');
        Route::post('nodes/reorder', [$learnCtrl, 'reorder'])->middleware('permission:education.manage')->name('nodes.reorder');
    });
    // Learning Structure Settings UI (Phase 4)
    $learnSettings = \App\Http\Controllers\LearningStructureSettingsController::class;
    Route::prefix('academic/structure')->name('academic.structure.')->group(function () use ($learnSettings) {
        Route::get('settings', [$learnSettings, 'index'])->name('settings');
        Route::post('settings/assign', [$learnSettings, 'assignTemplate'])->middleware('permission:education.manage')->name('settings.assign');
        Route::post('settings/nodes', [$learnSettings, 'storeNode'])->middleware('permission:education.manage')->name('settings.nodes.store');
        Route::put('settings/nodes/{node}', [$learnSettings, 'updateNode'])->middleware('permission:education.manage')->name('settings.nodes.update');
        Route::delete('settings/nodes/{node}', [$learnSettings, 'destroyNode'])->middleware('permission:education.manage')->name('settings.nodes.destroy');
        Route::post('settings/nodes/reorder', [$learnSettings, 'reorder'])->middleware('permission:education.manage')->name('settings.nodes.reorder');
    });

    // Settings Academic aliases for specific sub-routes
    Route::get('settings/academic/assessments/{assessment}/marks-sheet/export', [\App\Http\Controllers\AcademicMarksController::class, 'export'])->name('settings.academic.assessments.marks-sheet.export');
    Route::get('settings/academic/assessments/{assessment}/readiness/export', [\App\Http\Controllers\AcademicFinalResultController::class, 'readinessExport'])->name('settings.academic.assessments.readiness.export');
    Route::get('settings/academic/final-results/{result}/readiness/export', [\App\Http\Controllers\AcademicFinalResultController::class, 'readinessExport'])->name('settings.academic.final-results.readiness.export');
    Route::post('settings/academic/promotions/decisions/{decision}/send-to-review', [\App\Http\Controllers\AcademicPromotionController::class, 'sendBackToReview'])->name('settings.academic.promotions.decisions.send-to-review');

});

// ─── GEO (public) — must be outside tenant/auth so register/address works as guest ──
// Supports both guest registration flow and authenticated app.
$geo = \App\Http\Controllers\GeoController::class;
Route::get('geo/levels/{country}', [$geo, 'levels'])->name('geo.levels');
Route::get('geo/units', [$geo, 'units'])->name('geo.units');

// Admin Grading aliases — platform_admin outside tenant (overrides tenant aliases for correct guard)
Route::middleware(['auth:platform_admin', 'verified'])->group(function () {
    $adminGrading = \App\Http\Controllers\Admin\AcademicGradingAdminController::class;
    Route::get('admin/academic/grading/create', [$adminGrading, 'create'])->name('admin.academic.grading.create');
    Route::post('admin/academic/grading', [$adminGrading, 'store'])->name('admin.academic.grading.store');
    Route::get('admin/academic/grading/{grading}/edit', [$adminGrading, 'edit'])->name('admin.academic.grading.edit');
    Route::put('admin/academic/grading/{grading}', [$adminGrading, 'update'])->name('admin.academic.grading.update');
    Route::delete('admin/academic/grading/{grading}', [$adminGrading, 'destroy'])->name('admin.academic.grading.destroy');
});

