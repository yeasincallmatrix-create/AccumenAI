<?php

use App\Http\Controllers\Admin\CertificateAdminController;
use App\Http\Controllers\Admin\CourseAdminController;
use App\Http\Controllers\Admin\InstituteAdminController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentAdminController;
use App\Http\Controllers\Auth\InstituteUserLoginController;
use App\Http\Controllers\Auth\InstituteUserRegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OwnerRegisterController;
use App\Http\Controllers\Auth\PlatformAdminLoginController;
use App\Http\Controllers\Auth\UserLoginController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\InstituteNotificationController;
use App\Http\Controllers\InstituteSettingController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\StaffInvitationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SuperAdmin\DatabaseControlCenterController;
use App\Http\Controllers\SuperAdmin\DatabaseMonitoringController;
use App\Http\Controllers\SuperAdmin\DatabaseOperationsController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin — Database Control Center & Operations Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:platform_admin', 'verified'])->prefix('super-admin')->name('super-admin.')->group(function () {
    $ccc = DatabaseControlCenterController::class;
    Route::get('database/control-center', [$ccc, 'index'])->name('database.control-center');
    Route::get('database/control-center/json', [$ccc, 'json'])->name('database.control-center.json');

    Route::get('database/monitoring', [DatabaseMonitoringController::class, 'index'])->name('database.monitoring');
    Route::post('database/monitoring/refresh', [DatabaseMonitoringController::class, 'refresh'])->name('database.monitoring.refresh');

    $dbCtrl = DatabaseOperationsController::class;
    Route::get('database', [$dbCtrl, 'dashboard'])->name('database.dashboard');
    Route::post('database/refresh', [$dbCtrl, 'refresh'])->name('database.refresh');
    Route::get('database/backups', [$dbCtrl, 'backups'])->name('database.backups');
    Route::post('database/backups/create', [$dbCtrl, 'createBackup'])->name('database.backups.create');
    Route::post('database/backups/{backup}/verify', [$dbCtrl, 'verifyBackup'])->name('database.backups.verify');
    Route::post('database/backups/retention/execute', [$dbCtrl, 'executeRetention'])->name('database.retention.execute');
    Route::get('database/recovery', [$dbCtrl, 'recovery'])->name('database.recovery');
    Route::post('database/recovery/drill', [$dbCtrl, 'runDrill'])->name('database.recovery.drill');
    Route::get('database/health', [$dbCtrl, 'health'])->name('database.health');
    Route::get('database/performance', [$dbCtrl, 'performance'])->name('database.performance');
    Route::get('database/integrity', [$dbCtrl, 'integrity'])->name('database.integrity');
    Route::get('database/audit', [$dbCtrl, 'audit'])->name('database.audit');
    Route::get('database/status', [$dbCtrl, 'status'])->name('database.status');
});

// Public — portal auth (guest, throttled)
Route::get('login', [UserLoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [UserLoginController::class, 'login'])->name('login.submit')
    ->middleware('throttle:10,15');

Route::get('admin/login', [PlatformAdminLoginController::class, 'showLoginForm'])->name('admin.login');
Route::post('admin/login', [PlatformAdminLoginController::class, 'login'])->name('admin.login.submit')
    ->middleware('throttle:10,15');

// institute/login permanently removed — redirect to original unified login (web guard)
// Old bookmarks / cached forms hitting /institute/login will 301 to /login
Route::get('institute/login', function () { return redirect()->route('login', [], 301); })->name('institute.login');
Route::post('institute/login', function (\Illuminate\Http\Request $r) { return redirect()->route('login', [], 301); })->name('institute.login.submit');

Route::get('institute/register', [InstituteUserRegisterController::class, 'showRegisterForm'])->name('institute.register');
Route::post('institute/register', [InstituteUserRegisterController::class, 'register'])->name('institute.register.submit')
    ->middleware('throttle:10,15');

// OTP-First 5-step onboarding (new flow)
Route::get('register', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'showAccount'])->name('owner.register')->middleware('throttle:10,15');
Route::get('register/account', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'showAccount'])->name('register.account')->middleware('throttle:10,15');
Route::post('register/account', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'storeAccount'])->name('register.account.submit')->middleware('throttle:10,15');
Route::get('register/verify-otp', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'showOtp'])->name('register.otp.form')->middleware('throttle:10,15');
Route::post('register/verify-otp', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'verifyOtp'])->name('register.otp.verify')->middleware('throttle:10,15');
Route::post('register/resend-otp', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'resendOtp'])->name('register.otp.resend')->middleware('throttle:10,10');
Route::get('register/organization', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'showOrganization'])->name('register.organization')->middleware('throttle:10,15');
Route::post('register/organization', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'storeOrganization'])->name('register.organization.submit')->middleware('throttle:10,15');
Route::get('register/address', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'showAddress'])->name('register.address')->middleware('throttle:10,15');
Route::post('register/address', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'storeAddress'])->name('register.address.submit')->middleware('throttle:10,15');
Route::get('register/education', [\App\Http\Controllers\Auth\RegistrationFlowController::class, 'educationPlaceholder'])->name('register.education.placeholder')->middleware(['auth:web']);

// Legacy aliases kept for backwards compat (redirect into new flow)
Route::post('register/selection', [OwnerRegisterController::class, 'select'])->name('owner.register.selection')->middleware('throttle:10,15');
Route::get('register/form', function(){ return redirect()->route('register.account'); })->name('owner.register.form');
Route::post('register', function(\Illuminate\Http\Request $r){ return app(\App\Http\Controllers\Auth\RegistrationFlowController::class)->storeAccount($r); })->name('owner.register.submit')->middleware('throttle:10,15');

Route::post('logout', LogoutController::class)->name('logout');
// Non-destructive GET fallback: if CSRF/session expired, POST is blocked with 419
// before reaching the controller. This GET allows the user to still log out
// cleanly (clears tenant/workspace + redirects to portal login). Kept separate
// from POST to preserve strict CSRF on the primary path.
Route::get('logout', LogoutController::class)->name('logout.get');

Route::get('verify/certificate/{certificate_number}', [\App\Http\Controllers\VerifyCertificateController::class, 'show'])->name('verify.certificate');
Route::get('verify/certificate', [\App\Http\Controllers\VerifyCertificateController::class, 'index'])->name('verify.certificate.index');
Route::post('verify/certificate', [\App\Http\Controllers\VerifyCertificateController::class, 'check'])->name('verify.certificate.check');

Route::middleware(['auth:platform_admin,institute_user,web', 'verified'])->group(function () {
    Route::get('account/preferences', [UserPreferenceController::class, 'edit'])->name('account.preferences');
    Route::put('account/preferences', [UserPreferenceController::class, 'update'])->name('account.preferences.update');
    Route::post('account/preferences/theme', [UserPreferenceController::class, 'updateTheme'])->name('account.preferences.theme');
    Route::post('ui/columns', [\App\Http\Controllers\UiPreferenceController::class, 'save'])->name('ui.columns');
});

// Public Home — AccumenAI landing (no auth, Tailwind + Bootstrap Icons) — PHASE: IMPLEMENT_ACCUMENAI_HOME_PAGE
Route::get('/', function (\Illuminate\Http\Request $request) {
    // Authenticated users see their dashboard at root for backward compat
    if (\Illuminate\Support\Facades\Auth::guard('platform_admin')->check()
        || \Illuminate\Support\Facades\Auth::guard('institute_user')->check()
        || \Illuminate\Support\Facades\Auth::guard('web')->check()) {
        return app(DashboardController::class)();
    }
    return view('home');
})->middleware(['web', 'tenant'])->name('home');

Route::middleware(['auth:platform_admin,institute_user,web', 'tenant', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // Backward compat: / still resolves to dashboard for authenticated via home above, but keep alias
    Route::get('academic-dashboard', [DashboardController::class, '__invoke'])->name('academic-dashboard');
});

// Workspace — picker/switch are protected (verified), create/store are onboarding (accessible while unverified)
Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('workspace', [WorkspaceController::class, 'picker'])->name('workspace.picker');
    Route::post('workspace/switch/{institutionId}', [WorkspaceController::class, 'switch'])->name('workspace.switch');
});
Route::middleware(['auth:web'])->group(function () {
    Route::get('workspace/create', [WorkspaceController::class, 'create'])->name('workspace.create');
    Route::post('workspace/create', [WorkspaceController::class, 'store'])->name('workspace.store');
});

// Workspace onboarding — owner-only pick of country/industry/sub_industry (no tenant, auth:web only)
// Blade posts to workspace.onboarding.post (POST /workspace/onboarding); POST /workspace/onboarding/choose
// is kept as backwards-compat alias for cached forms that still POST to /choose (prevents 405).
Route::middleware(['auth:web'])->group(function () {
    Route::get('workspace/onboarding', [\App\Http\Controllers\InstituteOnboardingController::class, 'step1'])->name('workspace.onboarding');
    Route::post('workspace/onboarding', [\App\Http\Controllers\InstituteOnboardingController::class, 'choose'])->name('workspace.onboarding.post');
    Route::match(['get', 'post'], 'workspace/onboarding/choose', [\App\Http\Controllers\InstituteOnboardingController::class, 'choose'])->name('workspace.onboarding.choose');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->prefix('students')->name('students.')->group(function () {
    Route::get('/', [StudentController::class, 'index'])->middleware('permission:students.view')->name('index');
    Route::get('create', [StudentController::class, 'create'])->middleware('permission:students.manage')->name('create');
    Route::post('/', [StudentController::class, 'store'])->middleware('permission:students.manage')->name('store');
    Route::get('{student}', [StudentController::class, 'show'])->middleware('permission:students.view')->name('show');
    Route::post('{student}/enroll', [StudentController::class, 'enroll'])->middleware('permission:students.manage')->name('enroll');
    Route::get('{student}/edit', [StudentController::class, 'edit'])->middleware('permission:students.manage')->name('edit');
    Route::put('{student}', [StudentController::class, 'update'])->middleware('permission:students.manage')->name('update');
    Route::post('{student}/photo', [StudentController::class, 'uploadPhoto'])->middleware('permission:students.manage')->name('photo');
    Route::delete('{student}', [StudentController::class, 'destroy'])->middleware('permission:students.manage')->name('destroy');
});

// OCR Document Scan for Student form — auto-fill via Tesseract/cloud fallback
Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->group(function () {
    Route::post('document/scan', [\App\Http\Controllers\DocumentScanController::class, 'scan'])
        ->middleware(['permission:students.manage', 'throttle:10,30'])
        ->name('document.scan');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->prefix('sync')->name('sync.')->group(function () {
    Route::get('/', [OfflineSyncController::class, 'index'])->middleware('permission:finance.view')->name('index');
    Route::post('upload', [OfflineSyncController::class, 'store'])->name('upload');
    Route::post('{queue}/approve', [OfflineSyncController::class, 'approve'])->middleware('permission:finance.manage')->name('approve');
    Route::post('{queue}/reject', [OfflineSyncController::class, 'reject'])->middleware('permission:finance.manage')->name('reject');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified', 'domain:academic'])->group(function () {
    Route::get('academic/dashboard', [\App\Http\Controllers\AcademicDashboardController::class, '__invoke'])->name('academic.dashboard');
    Route::get('academic/analytics', [\App\Http\Controllers\AcademicAnalyticsController::class, 'index'])->name('academic.analytics.index');
    Route::get('academic-attendance/mark', [\App\Http\Controllers\AcademicAttendanceController::class, 'index'])->name('academic-attendance.mark.index');
    Route::get('academic-attendance/reports', [\App\Http\Controllers\AcademicAttendanceReportController::class, 'index'])->name('academic-attendance.reports.index');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified', 'domain:professional'])->prefix('batches')->name('batches.')->group(function () {
    Route::get('/', [BatchController::class, 'index'])->middleware('permission:batches.view')->name('index');
    Route::get('{batch}', [BatchController::class, 'show'])->middleware('permission:batches.view')->name('show');
    Route::post('/', [BatchController::class, 'store'])->middleware('permission:batches.manage')->name('store');
    Route::put('{batch}', [BatchController::class, 'update'])->middleware('permission:batches.manage')->name('update');
    Route::delete('{batch}', [BatchController::class, 'destroy'])->middleware('permission:batches.manage')->name('destroy');
    Route::post('{batch}/archive', [BatchController::class, 'archive'])->middleware('permission:batches.manage')->name('archive');
    Route::post('{batch}/unarchive', [BatchController::class, 'unarchive'])->middleware('permission:batches.manage')->name('unarchive');
});

// Exams — fixed: uses App\Http\Controllers\ExamController (not Institute\ namespace)
Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->prefix('exams')->name('exams.')->group(function () {
    Route::get('/', [ExamController::class, 'index'])->middleware('permission:exams.view')->name('index');
    Route::post('send-to-exam/{batch}', [ExamController::class, 'sendToExam'])->middleware('permission:exams.manage')->name('sendToExam');
    Route::get('{exam}', [ExamController::class, 'show'])->middleware('permission:exams.view')->name('show');
    Route::put('{exam}', [ExamController::class, 'update'])->middleware('permission:exams.manage')->name('update');
    Route::post('{exam}/marks', [ExamController::class, 'saveMarks'])->middleware('permission:exams.manage')->name('saveMarks');
    Route::delete('{exam}', [ExamController::class, 'destroy'])->middleware('permission:exams.manage')->name('destroy');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->group(function () {
    // Courses — archive/subjects must be before {course} wildcard (defined in institute_modules.php)
    // Legacy GET /courses (courses.index) retired — canonical is /courses/manage (courses.manage.index)
    Route::get('courses/archive', [CourseController::class, 'archive'])->middleware('permission:courses.view')->name('courses.archive');
    Route::get('courses/subjects', [CourseController::class, 'subjects'])->middleware('permission:courses.view')->name('courses.subjects');
    Route::get('certificates', [CertificateController::class, 'index'])->middleware(['permission:certificates.view','domain:professional'])->name('certificates.index');
    Route::get('verify', [InstituteSettingController::class, 'verify'])->name('verify');
    Route::get('settings', [InstituteSettingController::class, 'index'])->middleware('permission:settings.manage')->name('settings.edit');
    Route::put('settings', [InstituteSettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
    Route::get('settings/account', [InstituteSettingController::class, 'account'])->name('settings.account');
    Route::get('settings/appearance', [InstituteSettingController::class, 'appearance'])->name('settings.appearance');
    // Tenant logo — spec ADD_TENANT_LOGO_GLOBAL (global display via $institute->logo_url)
    Route::post('settings/logo', [InstituteSettingController::class, 'uploadLogo'])->middleware('permission:settings.manage')->name('settings.logo.upload');
    Route::delete('settings/logo', [InstituteSettingController::class, 'removeLogo'])->middleware('permission:settings.manage')->name('settings.logo.remove');
    // Legacy institute-controlled toggle — now superseded by Super Admin panel (admin.institutes.certificate-approval-mode.update)
    // Kept for backwards compatibility (tests / cached forms); UI removed from settings.
    Route::put('settings/certificate-approval-mode', [InstituteSettingController::class, 'updateCertificateApprovalMode'])->middleware('permission:settings.manage')->name('settings.certificate-approval-mode.update');
});

Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('invite', [StaffInvitationController::class, 'create'])->middleware('permission:staff.manage')->name('invite');
    Route::post('invite', [StaffInvitationController::class, 'store'])->middleware('permission:staff.manage')->name('invite.store');
});

Route::middleware(['auth:platform_admin', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'index'])->name('users.index');
    Route::get('users/bin', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'bin'])->name('users.bin');
    Route::get('users/{user}', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'show'])->name('users.show')->whereNumber('user');
    Route::post('users/{user}/suspend', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'suspend'])->name('users.suspend')->whereNumber('user');
    Route::post('users/{user}/reactivate', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'reactivate'])->name('users.reactivate')->whereNumber('user');
    Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'destroy'])->name('users.destroy')->whereNumber('user');
    Route::post('users/{user}/restore', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'restore'])->name('users.restore')->withTrashed()->whereNumber('user');
    Route::delete('users/{user}/force-delete', [\App\Http\Controllers\Admin\UserAccountAdminController::class, 'forceDelete'])->name('users.force-delete')->withTrashed()->whereNumber('user');

    Route::get('tenants', [\App\Http\Controllers\Admin\TenantsAdminController::class, 'index'])->name('tenants.index');
    Route::get('institutes', [InstituteAdminController::class, 'index'])->name('institutes.index');
    Route::get('institutes/bin', [InstituteAdminController::class, 'bin'])->name('institutes.bin');
    Route::post('institutes/bin/batch-action', [InstituteAdminController::class, 'batchBinAction'])->name('institutes.bin.batch-action');
    Route::post('institutes/bin/columns', [InstituteAdminController::class, 'saveBinColumns'])->name('institutes.bin.columns');
    Route::post('institutes/batch-action', [InstituteAdminController::class, 'batchAction'])->name('institutes.batch-action');
    Route::get('institutes/{institute}', [InstituteAdminController::class, 'show'])->name('institutes.show')->whereNumber('institute');
    Route::get('institutes/{institute}/edit', [InstituteAdminController::class, 'edit'])->name('institutes.edit')->whereNumber('institute');
    Route::put('institutes/{institute}', [InstituteAdminController::class, 'update'])->name('institutes.update')->whereNumber('institute');
    Route::put('institutes/{institute}/certificate-approval-mode', [InstituteAdminController::class, 'updateCertificateApprovalMode'])->name('institutes.certificate-approval-mode.update')->whereNumber('institute');
    Route::post('institutes/{institute}/action', [InstituteAdminController::class, 'action'])->name('institutes.action')->whereNumber('institute');
    Route::post('institutes/{institute}/restore', [InstituteAdminController::class, 'restore'])->name('institutes.restore')->withTrashed()->whereNumber('institute');
    Route::delete('institutes/{institute}/force-delete', [InstituteAdminController::class, 'forceDelete'])->name('institutes.force-delete')->withTrashed()->whereNumber('institute');
    Route::delete('institutes/{institute}/staff/{kind}/{id}', [InstituteAdminController::class, 'destroyStaff'])->name('institutes.staff.destroy')->whereNumber('institute')->whereNumber('id');
    Route::get('courses', [CourseAdminController::class, 'index'])->name('courses.index');
    Route::get('courses/assignment', [CourseAdminController::class, 'assignment'])->name('courses.assignment');
    Route::post('courses/assignment/assign', [CourseAdminController::class, 'assignmentAssign'])->name('courses.assignment.assign');
    Route::post('courses/assignment/remove', [CourseAdminController::class, 'assignmentRemove'])->name('courses.assignment.remove');
    Route::get('courses/subjects', [CourseAdminController::class, 'subjects'])->name('courses.subjects');
    Route::get('courses/subjects-columns', [CourseAdminController::class, 'saveSubjectsColumns'])->name('courses.subjects-columns');
    Route::get('courses/subject-requests', [CourseAdminController::class, 'subjectRequests'])->name('courses.subjects-requests');
    Route::post('courses/subject-requests/{subjectRequest}/action', [CourseAdminController::class, 'subjectRequestsAction'])->name('courses.subjects-requests.action');
    Route::get('courses/subject-requests-columns', [CourseAdminController::class, 'saveSubjectRequestsColumns'])->name('courses.subjects-requests-columns');
    Route::get('courses/requests', [CourseAdminController::class, 'requests'])->name('courses.requests');
    Route::get('courses/requests-columns', [CourseAdminController::class, 'saveRequestsColumns'])->name('courses.requests-columns');
    Route::post('courses/requests/{courseRequest}/action', [CourseAdminController::class, 'requestAction'])->name('courses.requests.action');
    Route::get('courses/batches', [CourseAdminController::class, 'batches'])->name('courses.batches');
    Route::get('courses/archive', [CourseAdminController::class, 'archive'])->name('courses.archive');
    Route::get('courses/{course}', [CourseAdminController::class, 'show'])->name('courses.show')->whereNumber('course');
    Route::get('students', [StudentAdminController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentAdminController::class, 'show'])->name('students.show')->whereNumber('student');
    Route::get('certificates', [CertificateAdminController::class, 'index'])->name('certificates.index');
    Route::get('certificates/{certificate}', [CertificateAdminController::class, 'show'])->name('certificates.show')->whereNumber('certificate');
    Route::get('certificates/{certificate}/qr', [CertificateAdminController::class, 'downloadQr'])->name('certificates.qr')->whereNumber('certificate');
    Route::post('certificates/{certificate}/update-template', [CertificateAdminController::class, 'updateTemplate'])->name('certificates.update-template')->whereNumber('certificate');
    Route::post('certificates/{certificate}/action', [CertificateAdminController::class, 'action'])->name('certificates.action')->whereNumber('certificate');
    Route::post('certificates/columns', [CertificateAdminController::class, 'saveColumns'])->name('certificates.columns');
    Route::post('certificates/requests/columns', [CertificateAdminController::class, 'saveRequestsColumns'])->name('certificates.requests-columns');
    Route::delete('certificates/{certificate}', [CertificateAdminController::class, 'destroy'])->name('certificates.destroy')->whereNumber('certificate');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('settings/staff', [SettingController::class, 'staff'])->name('settings.staff');
    Route::post('settings/password', [SettingController::class, 'updatePassword'])->name('settings.password');
    Route::post('settings/language', [SettingController::class, 'updateLanguage'])->name('settings.language');
    Route::post('settings/appearance', [SettingController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::post('settings/mail-payment', [SettingController::class, 'updateMailPayment'])->name('settings.mail-payment.update');
    Route::post('settings/mail-payment/test', [SettingController::class, 'testMail'])->name('settings.mail-payment.test');
    Route::get('settings/ai', [\App\Http\Controllers\Admin\AiSettingController::class, 'index'])->name('settings.ai');
    Route::post('settings/ai', [\App\Http\Controllers\Admin\AiSettingController::class, 'update'])->name('settings.ai.update');
    Route::post('settings/ai/test', [\App\Http\Controllers\Admin\AiSettingController::class, 'test'])->name('settings.ai.test');
    Route::post('settings/staff/{instituteUser}/action', [SettingController::class, 'staffAction'])->name('settings.staff-action');

    // API Key management (multiple per provider)
    Route::get('ai-api-keys', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'index'])->name('ai-api-keys.index');
    Route::post('ai-api-keys', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'store'])->name('ai-api-keys.store');
    Route::put('ai-api-keys/{key}', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'update'])->name('ai-api-keys.update');
    Route::delete('ai-api-keys/{key}', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'destroy'])->name('ai-api-keys.destroy');
    Route::post('ai-api-keys/{key}/toggle', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'toggleActive'])->name('ai-api-keys.toggle');
    Route::post('ai-api-keys/{key}/configure', [\App\Http\Controllers\Admin\AiApiKeyController::class, 'configure'])->name('ai-api-keys.configure');

    // Platform Configuration Center (E19)
    Route::get('platform-settings', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'index'])->name('platform-settings.index');
    Route::post('platform-settings/general', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateGeneral'])->name('platform-settings.general');
    Route::post('platform-settings/email', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateEmail'])->name('platform-settings.email');
    Route::post('platform-settings/email/test', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'testEmail'])->name('platform-settings.email.test');
    Route::post('platform-settings/sms', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateSms'])->name('platform-settings.sms');
    Route::post('platform-settings/sms/test-connection', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'testSmsConnection'])->name('platform-settings.sms.test-connection')->middleware('throttle:10,15');
    Route::post('platform-settings/sms/test', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'testSms'])->name('platform-settings.sms.test')->middleware('throttle:3,10');
    Route::post('platform-settings/otp', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateOtp'])->name('platform-settings.otp');
    Route::post('platform-settings/twofactor', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateTwoFactor'])->name('platform-settings.twofactor');
    Route::post('platform-settings/login-security', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateLoginSecurity'])->name('platform-settings.login-security');
    Route::post('platform-settings/queue/health', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'queueHealth'])->name('platform-settings.queue.health');
    Route::post('platform-settings/payment', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updatePayment'])->name('platform-settings.payment');
    Route::post('platform-settings/storage', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateStorage'])->name('platform-settings.storage');
    Route::post('platform-settings/maps', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateMaps'])->name('platform-settings.maps');
    Route::post('platform-settings/notifications', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateNotifications'])->name('platform-settings.notifications');
    Route::post('platform-settings/ai', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateAi'])->name('platform-settings.ai');
    Route::post('platform-settings/api', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateApi'])->name('platform-settings.api');
    Route::post('platform-settings/branding', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateBranding'])->name('platform-settings.branding');
    Route::post('platform-settings/maintenance', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'updateMaintenance'])->name('platform-settings.maintenance');
    Route::get('platform-audit', [\App\Http\Controllers\Admin\PlatformAuditController::class, 'index'])->name('platform-audit.index');

    // Platform Staff Management (delegated, least-privilege - NOT super admin)
    Route::get('platform-staff', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'index'])->name('platform-staff.index');
    Route::get('platform-staff/create', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'create'])->name('platform-staff.create');
    Route::post('platform-staff', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'store'])->name('platform-staff.store');
    Route::get('platform-staff/{platformStaff}/edit', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'edit'])->name('platform-staff.edit');
    Route::put('platform-staff/{platformStaff}', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'update'])->name('platform-staff.update');
    Route::delete('platform-staff/{platformStaff}', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'destroy'])->name('platform-staff.destroy');
    Route::post('platform-staff/{platformStaff}/toggle', [\App\Http\Controllers\Admin\PlatformStaffController::class, 'toggleStatus'])->name('platform-staff.toggle');

    // Safe Artisan Command Runner — platform_admin only, whitelist, rate-limited, audited
    Route::get('artisan-commands', [\App\Http\Controllers\Admin\ArtisanCommandController::class, 'index'])->name('artisan-commands.index');
    Route::post('artisan-commands/execute', [\App\Http\Controllers\Admin\ArtisanCommandController::class, 'execute'])->name('artisan-commands.execute')->middleware('throttle:10,60');

    // Dual Deployment System — Git + ZIP (admin.deploy, audited, throttled, backup retention 5)
    Route::get('deploy', [\App\Http\Controllers\Admin\DeployController::class, 'index'])->name('deploy.index')->middleware('permission:admin.deploy');
    Route::post('deploy/git', [\App\Http\Controllers\Admin\DeployController::class, 'gitDeploy'])->name('deploy.git')->middleware(['permission:admin.deploy', 'throttle:5,60']);
    Route::post('deploy/zip', [\App\Http\Controllers\Admin\DeployController::class, 'zipDeploy'])->name('deploy.zip')->middleware(['permission:admin.deploy', 'throttle:5,60']);
    Route::post('deploy/rollback/{logId}', [\App\Http\Controllers\Admin\DeployController::class, 'rollback'])->name('deploy.rollback')->whereNumber('logId')->middleware(['permission:admin.deploy', 'throttle:5,60']);
});

// DEV — Page Marker (temporary tool, gated by PageMarker::enabled())
Route::get('dev/page-marker', function () {
    if (!\App\Support\PageMarker::enabled()) abort(404);
    return response()->json(['page' => \App\Support\PageMarker::page()]);
})->name('dev.page-marker');
Route::post('dev/page-marker/toggle', function () {
    \App\Support\PageMarker::toggle();
    return back();
})->name('dev.page-marker.toggle')->middleware('auth:platform_admin');
Route::post('admin/dev/page-marker/toggle', function () {
    \App\Support\PageMarker::toggle();
    return back();
})->name('admin.dev.page-marker.toggle')->middleware('auth:platform_admin');

// Notifications bell — protected, non-destructive alias keeps old URL working
Route::middleware(['auth:platform_admin,institute_user,web', 'verified'])->group(function () {
    Route::get('notifications', [InstituteNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [InstituteNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [InstituteNotificationController::class, 'markRead'])->name('notifications.read');
});

// ── Admin: Industry Settings (per-industry theme defaults, NOT global settings) ──
Route::get('admin/industry-settings', [\App\Http\Controllers\Admin\IndustrySettingController::class, 'index'])->name('admin.industry-settings')->middleware(['auth:platform_admin', 'verified']);
Route::post('admin/industry-settings/theme', [\App\Http\Controllers\Admin\IndustrySettingController::class, 'updateTheme'])->name('admin.industry-settings.theme')->middleware(['auth:platform_admin', 'verified']);
Route::put('admin/themes/{theme}', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.themes.update')->middleware(['auth:platform_admin', 'verified'])->whereNumber('theme');
Route::get('admin/modules', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'index'])->name('admin.modules.index')->middleware(['auth:platform_admin', 'verified']);
Route::put('admin/modules/{module}', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'update'])->name('admin.modules.update')->middleware(['auth:platform_admin', 'verified'])->whereNumber('module');
Route::get('admin/modules/access-logs', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'accessLogs'])->name('admin.modules.access-logs')->middleware(['auth:platform_admin', 'verified']);
Route::get('admin/packages/{package}/modules', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'packageModules'])->name('admin.packages.modules')->middleware(['auth:platform_admin', 'verified'])->whereNumber('package');
Route::put('admin/packages/{package}/modules', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'updatePackageModules'])->name('admin.packages.modules.update')->middleware(['auth:platform_admin', 'verified'])->whereNumber('package');
Route::get('admin/institutes/{institute}/modules', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'instituteModules'])->name('admin.institutes.modules')->middleware(['auth:platform_admin', 'verified'])->whereNumber('institute');
Route::put('admin/institutes/{institute}/modules', [\App\Http\Controllers\Admin\ModuleAdminController::class, 'updateInstituteModules'])->name('admin.institutes.modules.update')->middleware(['auth:platform_admin', 'verified'])->whereNumber('institute');
Route::get('admin/academic', [\App\Http\Controllers\Admin\AcademicStructureAdminController::class, 'index'])->name('admin.academic.index')->middleware(['auth:platform_admin', 'verified']);
Route::get('admin/academic/subjects', [\App\Http\Controllers\Admin\AcademicSubjectAdminController::class, 'index'])->name('admin.academic.subjects.index')->middleware(['auth:platform_admin', 'verified']);
Route::get('admin/academic/grading', [\App\Http\Controllers\Admin\AcademicGradingAdminController::class, 'index'])->name('admin.academic.grading.index')->middleware(['auth:platform_admin', 'verified']);
Route::get('admin/classes', [\App\Http\Controllers\Admin\ClassAdminController::class, 'index'])->name('admin.classes.index')->middleware(['auth:platform_admin', 'verified']);
Route::get('admin/security', [\App\Http\Controllers\Auth\SecurityController::class, '__invoke'])->name('admin.security')->middleware(['auth:platform_admin', 'verified']);
Route::get('account/security', [\App\Http\Controllers\Auth\SecurityController::class, '__invoke'])->name('account.security')->middleware(['auth:platform_admin,institute_user,web', 'verified']);

// ── Admin: Country Batch Actions ──
Route::get('admin/countries', function (\Illuminate\Http\Request $request) {
    $query = \App\Models\Country::query()->withCount('educationSystems');
    if ($request->query('q') !== null && trim((string) $request->query('q')) !== '') {
        $q = trim((string) $request->query('q'));
        $query->where(function ($qq) use ($q) {
            $qq->where('name', 'like', "%{$q}%")
               ->orWhere('iso2', 'like', "%{$q}%");
        });
    }
    return view('admin.countries.index', [
        'countries' => $query->orderBy('name')->get(),
        'q' => $request->query('q'),
    ]);
})->name('admin.countries.index')->middleware(['auth:platform_admin', 'verified']);
Route::post('admin/countries/batch', [\App\Http\Controllers\Admin\CountryBatchController::class, '__invoke'])
    ->name('admin.countries.batch')
    ->middleware(['auth:platform_admin', 'permission:countries.manage']);

// Identity — Email/Phone verification, change, removal (E4–E5)
Route::middleware(['auth:web', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::post('phone/verify-send', [\App\Http\Controllers\Auth\IdentityController::class, 'sendPhoneVerification'])->name('phone.verify-send')->middleware('throttle:10,15');
    Route::post('phone/verify', [\App\Http\Controllers\Auth\IdentityController::class, 'verifyPhone'])->name('phone.verify')->middleware('throttle:10,15');
    Route::post('email/change-request', [\App\Http\Controllers\Auth\IdentityController::class, 'requestEmailChange'])->name('email.change-request')->middleware('throttle:10,15');
    Route::post('email/verify-change', [\App\Http\Controllers\Auth\IdentityController::class, 'verifyEmailChange'])->name('email.verify-change')->middleware('throttle:10,15');
    Route::get('email/verify', [\App\Http\Controllers\Auth\IdentityController::class, 'verifyEmailChangeLink'])->name('email.verify');
    Route::post('phone/change-request', [\App\Http\Controllers\Auth\IdentityController::class, 'requestPhoneChange'])->name('phone.change-request')->middleware('throttle:10,15');
    Route::post('phone/verify-change', [\App\Http\Controllers\Auth\IdentityController::class, 'verifyPhoneChange'])->name('phone.verify-change')->middleware('throttle:10,15');
    Route::post('email/remove', [\App\Http\Controllers\Auth\IdentityController::class, 'removeEmail'])->name('email.remove')->middleware('throttle:10,15');
    Route::post('phone/remove', [\App\Http\Controllers\Auth\IdentityController::class, 'removePhone'])->name('phone.remove')->middleware('throttle:10,15');
});

// Business Profile — dedicated authenticated workspace profile (Phase B6)
Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->group(function () {
    Route::get('business/profile', [\App\Http\Controllers\BusinessProfileController::class, 'show'])->name('business.profile');
    Route::post('institutes/logo', [\App\Http\Controllers\InstituteLogoController::class, 'upload'])->name('institute.logo.upload');
    Route::delete('institutes/logo', [\App\Http\Controllers\InstituteLogoController::class, 'remove'])->name('institute.logo.remove');
});

// institute/finance/accounting stubs
Route::middleware(['auth:institute_user,web','tenant', 'verified'])->group(function () {
    Route::get('business/{institute}', function ($institute) { return redirect()->route('dashboard'); })->name('business.show');
    Route::get('teachers', [\App\Http\Controllers\TeacherController::class, 'index'])->name('teachers.index');
    Route::get('alumni', [\App\Http\Controllers\Alumni\AlumniController::class, 'index'])->name('alumni.index');
    Route::get('workflows', [\App\Http\Controllers\WorkflowController::class, 'index'])->name('workflows.index');
    Route::get('crm', [\App\Http\Controllers\CrmDashboardController::class, 'index'])->middleware('module_access:crm')->name('crm.dashboard');
    Route::get('hr', [\App\Http\Controllers\Hr\HrDashboardController::class, 'index'])->middleware('module_access:hr')->name('hr.dashboard');
    Route::get('hr/payroll/periods', function () { return redirect()->route('hr.dashboard'); })->middleware('module_access:hr')->name('hr.payroll.periods.index');
    Route::get('sales/settings', [\App\Http\Controllers\Sales\SalesSettingsController::class, 'index'])->middleware('module_access:sales')->name('sales.settings.index');
    Route::get('purchase/orders', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->middleware('module_access:purchase')->name('purchase.orders.index');
    Route::get('finance', [\App\Http\Controllers\FinanceDashboardController::class, 'index'])->name('finance.dashboard');
    Route::get('finance/budgets/dashboard', [\App\Http\Controllers\FinanceBudgetController::class, 'index'])->name('finance.budgets.dashboard');
    Route::get('finance/chart-of-accounts', [\App\Http\Controllers\FinanceChartOfAccountController::class, 'index'])->name('finance.chart-of-accounts.index');
    Route::get('finance/journals', [\App\Http\Controllers\FinanceJournalController::class, 'index'])->name('finance.journals.index');
    Route::get('finance/invoices', [\App\Http\Controllers\FinanceInvoiceController::class, 'index'])->name('finance.invoices.index');
    Route::get('finance/payments', [\App\Http\Controllers\FinancePaymentController::class, 'index'])->name('finance.payments.index');
    Route::get('finance/parties', [\App\Http\Controllers\FinancePartyController::class, 'index'])->name('finance.parties.index');
    Route::get('finance/payment-methods', [\App\Http\Controllers\FinancePaymentMethodController::class, 'index'])->name('finance.payment-methods.index');
    Route::get('finance/periods', [\App\Http\Controllers\FinancePeriodController::class, 'index'])->name('finance.periods.index');
    Route::get('finance/opening-balances/create', [\App\Http\Controllers\FinanceOpeningBalanceController::class, 'create'])->name('finance.opening-balances.create');
    Route::get('finance/exchange-rates', [\App\Http\Controllers\FinanceExchangeRateController::class, 'index'])->name('finance.exchange-rates.index');
    Route::get('finance/fx-revaluations', [\App\Http\Controllers\FinanceFxRevaluationController::class, 'index'])->name('finance.fx-revaluations.index');
    Route::get('finance/audit', [\App\Http\Controllers\FinanceAuditController::class, 'index'])->name('finance.audit.index');
    Route::get('finance/education/dashboard', function () { return redirect()->route('finance.dashboard'); })->name('finance.education.dashboard');
    Route::get('finance/education/fee-structures', [\App\Http\Controllers\FeeStructureController::class, 'index'])
        ->middleware(['permission:finance.view', 'module_access:finance'])
        ->name('finance.education.fee-structures.index');
    Route::get('finance/reports/trial-balance', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'trialBalance'])->name('finance.reports.trial-balance');
    Route::get('accounting', [\App\Http\Controllers\Accounting\AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    Route::get('accounting/reports/trial-balance', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'trialBalance'])->name('accounting.reports.trial-balance');
    Route::get('accounting/reports/profit-loss', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'profitAndLoss'])->name('accounting.reports.profit-loss');
    Route::get('accounting/reports/balance-sheet', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'balanceSheet'])->name('accounting.reports.balance-sheet');
    Route::get('accounting/reports/cash-flow', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'cashFlow'])->name('accounting.reports.cash-flow');
    Route::get('accounting/reports/general-ledger', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'generalLedger'])->name('accounting.reports.general-ledger');
    Route::get('accounting/reports/account-ledger', [\App\Http\Controllers\Accounting\AccountingReportController::class, 'accountLedger'])->name('accounting.reports.account-ledger');
    Route::get('recycle', [\App\Http\Controllers\RecycleBinController::class, 'index'])->name('recycle.index');
    Route::get('settings', [\App\Http\Controllers\InstituteSettingController::class, 'index'])->name('settings.index');
    Route::get('owner/profile', function () { return redirect()->route('settings.index'); })->name('owner.profile');
    Route::get('ai/assistant', [\App\Http\Controllers\Ai\AiAssistantController::class, 'index'])->name('ai.assistant');
    Route::get('finance/online-payments/gateways', function () { return redirect()->route('finance.dashboard'); })->name('finance.online-payments.gateways');
});



// -- Institute Module Routes (778 routes mapped to controllers)
require __DIR__.'/institute_modules.php';

// Shallow subject update must be defined AFTER institute_modules so its
// name `courses.subjects.update` overwrites the nested `courses/{course}/subjects/{subject}`
// for view generation (subjects list uses single {subject} param). The nested URI
// remains registered for direct matching but route() will generate the shallow URI.
Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->group(function () {
    Route::put('courses/subjects/{subject}', [\App\Http\Controllers\CourseController::class, 'updateSubject'])
        ->middleware('permission:courses.manage')
        ->name('courses.subjects.update');
});
