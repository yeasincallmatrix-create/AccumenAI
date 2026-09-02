# Monetix � Routing Document

Generated: 2026-08-23 21:25 | Laravel 12.66.0 | Total lines: 3392 | Real: 276 | Stubs: 3116

## Files

| File | Purpose |
|------|---|
| `routes/web.php` | Portals, dashboard, modules |
| `routes/auth.php` | Fortify |
| `routes/guardian.php` | Guardian |
| `routes/api.php` | API |

## Real routes (276 without __stub)

```
  GET|HEAD  / ........................................................................ dashboard ΓÇ║ DashboardController
  GET|HEAD  academic-attendance/mark ............. academic-attendance.mark.index ΓÇ║ AcademicAttendanceController@index
  GET|HEAD  academic-attendance/reports . academic-attendance.reports.index ΓÇ║ AcademicAttendanceReportController@index
  GET|HEAD  academic-dashboard ..................................... academic-dashboard ΓÇ║ DashboardController@__invoke
  GET|HEAD  academic/analytics .......................... academic.analytics.index ΓÇ║ AcademicAnalyticsController@index
  GET|HEAD  academic/dashboard ............................. academic.dashboard ΓÇ║ AcademicDashboardController@__invoke
  GET|HEAD  account/preferences .................................. account.preferences ΓÇ║ UserPreferenceController@edit
  PUT       account/preferences ......................... account.preferences.update ΓÇ║ UserPreferenceController@update
  POST      account/preferences/theme ............... account.preferences.theme ΓÇ║ UserPreferenceController@updateTheme
  GET|HEAD  account/security .............................................. account.security ΓÇ║ Auth\SecurityController
  POST      account/security/email/resend account.verification.send ΓÇ║ Auth\EmailVerificationNotificationController@stΓÇª
  POST      account/security/sessions/revoke ........ account.sessions.revoke ΓÇ║ Auth\SecurityController@revokeSessions
  POST      account/security/two-factor/confirm ......... account.two-factor.confirm ΓÇ║ Auth\SecurityController@confirm
  POST      account/security/two-factor/disable ......... account.two-factor.disable ΓÇ║ Auth\SecurityController@disable
  POST      account/security/two-factor/enable ............ account.two-factor.enable ΓÇ║ Auth\SecurityController@enable
  GET|HEAD  account/security/two-factor/qr-code .......... account.two-factor.qr-code ΓÇ║ Auth\SecurityController@qrCode
  GET|HEAD  account/security/two-factor/recovery-codes account.two-factor.recovery-codes ΓÇ║ Auth\SecurityController@reΓÇª
  POST      account/security/two-factor/recovery-codes account.two-factor.regenerate-recovery-codes ΓÇ║ Auth\SecurityCoΓÇª
  GET|HEAD  accounting ......................... accounting.dashboard ΓÇ║ Accounting\AccountingDashboardController@index
  GET|HEAD  accounting/reports/account-ledger accounting.reports.account-ledger ΓÇ║ Accounting\AccountingReportControllΓÇª
  GET|HEAD  accounting/reports/balance-sheet accounting.reports.balance-sheet ΓÇ║ Accounting\AccountingReportControllerΓÇª
  GET|HEAD  accounting/reports/cash-flow accounting.reports.cash-flow ΓÇ║ Accounting\AccountingReportController@cashFlow
  GET|HEAD  accounting/reports/general-ledger accounting.reports.general-ledger ΓÇ║ Accounting\AccountingReportControllΓÇª
  GET|HEAD  accounting/reports/profit-loss accounting.reports.profit-loss ΓÇ║ Accounting\AccountingReportController@proΓÇª
  GET|HEAD  accounting/reports/trial-balance accounting.reports.trial-balance ΓÇ║ Accounting\AccountingReportControllerΓÇª
  GET|HEAD  admin/academic ....................... admin.academic.index ΓÇ║ Admin\AcademicStructureAdminController@index
  GET|HEAD  admin/academic/grading ......... admin.academic.grading.index ΓÇ║ Admin\AcademicGradingAdminController@index
  GET|HEAD  admin/academic/subjects ....... admin.academic.subjects.index ΓÇ║ Admin\AcademicSubjectAdminController@index
  GET|HEAD  admin/certificates ..................... admin.certificates.index ΓÇ║ Admin\CertificateAdminController@index
  POST      admin/certificates/{certificate}/action admin.certificates.action ΓÇ║ Admin\CertificateAdminController@actiΓÇª
  GET|HEAD  admin/classes ..................................... admin.classes.index ΓÇ║ Admin\ClassAdminController@index
  GET|HEAD  admin/courses .................................... admin.courses.index ΓÇ║ Admin\CourseAdminController@index
  GET|HEAD  admin/courses/assignment ............... admin.courses.assignment ΓÇ║ Admin\CourseAdminController@assignment
  GET|HEAD  admin/courses/requests ..................... admin.courses.requests ΓÇ║ Admin\CourseAdminController@requests
  POST      admin/courses/requests/{courseRequest}/action admin.courses.requests.action ΓÇ║ Admin\CourseAdminControllerΓÇª
  POST      admin/dev/page-marker/toggle ........................... admin.dev.page-marker.toggle ΓÇ║ routes/web.php:213
  GET|HEAD  admin/industry-settings ..................................... admin.industry-settings ΓÇ║ routes/web.php:223
  GET|HEAD  admin/institutes ........................... admin.institutes.index ΓÇ║ Admin\InstituteAdminController@index
  GET|HEAD  admin/institutes/bin ........................... admin.institutes.bin ΓÇ║ Admin\InstituteAdminController@bin
  GET|HEAD  admin/institutes/{institute} ................. admin.institutes.show ΓÇ║ Admin\InstituteAdminController@show
  PUT       admin/institutes/{institute} ............. admin.institutes.update ΓÇ║ Admin\InstituteAdminController@update
  POST      admin/institutes/{institute}/action ...... admin.institutes.action ΓÇ║ Admin\InstituteAdminController@action
  GET|HEAD  admin/institutes/{institute}/edit ............ admin.institutes.edit ΓÇ║ Admin\InstituteAdminController@edit
  DELETE    admin/institutes/{institute}/force-delete admin.institutes.force-delete ΓÇ║ Admin\InstituteAdminController@ΓÇª
  GET|HEAD  admin/institutes/{institute}/modules admin.institutes.modules ΓÇ║ Admin\ModuleAdminController@instituteModuΓÇª
  PUT       admin/institutes/{institute}/modules admin.institutes.modules.update ΓÇ║ Admin\ModuleAdminController@updateΓÇª
  POST      admin/institutes/{institute}/restore ... admin.institutes.restore ΓÇ║ Admin\InstituteAdminController@restore
  GET|HEAD  admin/login ................................ admin.login ΓÇ║ Auth\PlatformAdminLoginController@showLoginForm
  POST      admin/login ................................. admin.login.submit ΓÇ║ Auth\PlatformAdminLoginController@login
  GET|HEAD  admin/modules .................................... admin.modules.index ΓÇ║ Admin\ModuleAdminController@index
  GET|HEAD  admin/modules/access-logs ............. admin.modules.access-logs ΓÇ║ Admin\ModuleAdminController@accessLogs
  PUT       admin/modules/{module} ......................... admin.modules.update ΓÇ║ Admin\ModuleAdminController@update
  GET|HEAD  admin/notifications ....................... admin.notifications.index ΓÇ║ Admin\NotificationController@index
  POST      admin/notifications/read-all ..... admin.notifications.read-all ΓÇ║ Admin\NotificationController@markAllRead
  POST      admin/notifications/{notification}/read . admin.notifications.read ΓÇ║ Admin\NotificationController@markRead
  GET|HEAD  admin/packages/{package}/modules ..... admin.packages.modules ΓÇ║ Admin\ModuleAdminController@packageModules
  PUT       admin/packages/{package}/modules admin.packages.modules.update ΓÇ║ Admin\ModuleAdminController@updatePackagΓÇª
  GET|HEAD  admin/security .................................................. admin.security ΓÇ║ Auth\SecurityController
  POST      admin/security/email/resend . admin.verification.send ΓÇ║ Auth\EmailVerificationNotificationController@store
  POST      admin/security/sessions/flush-all .... admin.sessions.flush-all ΓÇ║ Auth\SecurityController@flushAllSessions
  POST      admin/security/sessions/revoke ............ admin.sessions.revoke ΓÇ║ Auth\SecurityController@revokeSessions
  POST      admin/security/two-factor/confirm ............. admin.two-factor.confirm ΓÇ║ Auth\SecurityController@confirm
  POST      admin/security/two-factor/disable ............. admin.two-factor.disable ΓÇ║ Auth\SecurityController@disable
  POST      admin/security/two-factor/enable ................ admin.two-factor.enable ΓÇ║ Auth\SecurityController@enable
  GET|HEAD  admin/security/two-factor/qr-code .............. admin.two-factor.qr-code ΓÇ║ Auth\SecurityController@qrCode
  GET|HEAD  admin/security/two-factor/recovery-codes admin.two-factor.recovery-codes ΓÇ║ Auth\SecurityController@recoveΓÇª
  POST      admin/security/two-factor/recovery-codes admin.two-factor.regenerate-recovery-codes ΓÇ║ Auth\SecurityControΓÇª
  GET|HEAD  admin/settings ...................................... admin.settings.index ΓÇ║ Admin\SettingController@index
  GET|HEAD  admin/settings/ai .................................... admin.settings.ai ΓÇ║ Admin\AiSettingController@index
  POST      admin/settings/ai ............................ admin.settings.ai.update ΓÇ║ Admin\AiSettingController@update
  POST      admin/settings/ai/test ........................... admin.settings.ai.test ΓÇ║ Admin\AiSettingController@test
  POST      admin/settings/appearance .... admin.settings.appearance.update ΓÇ║ Admin\SettingController@updateAppearance
  POST      admin/settings/language ................. admin.settings.language ΓÇ║ Admin\SettingController@updateLanguage
  POST      admin/settings/mail-payment admin.settings.mail-payment.update ΓÇ║ Admin\SettingController@updateMailPayment
  POST      admin/settings/mail-payment/test ..... admin.settings.mail-payment.test ΓÇ║ Admin\SettingController@testMail
  POST      admin/settings/password ................. admin.settings.password ΓÇ║ Admin\SettingController@updatePassword
  GET|HEAD  admin/settings/staff ................................ admin.settings.staff ΓÇ║ Admin\SettingController@staff
  POST      admin/settings/staff/{instituteUser}/action admin.settings.staff-action ΓÇ║ Admin\SettingController@staffAcΓÇª
  GET|HEAD  admin/students ................................. admin.students.index ΓÇ║ Admin\StudentAdminController@index
  GET|HEAD  admin/students/{student} ......................... admin.students.show ΓÇ║ Admin\StudentAdminController@show
  GET|HEAD  ai/assistant ............................................... ai.assistant ΓÇ║ Ai\AiAssistantController@index
  GET|HEAD  alumni ...................................................... alumni.index ΓÇ║ Alumni\AlumniController@index
  GET|HEAD  api/assessments ........................................................... Api\AssessmentController@index
  GET|HEAD  api/assessments/{id}/results ............................................ Api\AssessmentController@results
  GET|HEAD  api/attendance ............................................................ Api\AttendanceController@index
  POST      api/attendance ............................................................ Api\AttendanceController@store
  GET|HEAD  api/batches .................................................................... Api\BatchController@index
  GET|HEAD  api/batches/{id} ................................................................ Api\BatchController@show
  GET|HEAD  api/branches ................................................................. Api\AuthController@branches
  GET|HEAD  api/certificates ......................................................... Api\CertificateController@index
  GET|HEAD  api/courses ................................................................... Api\CourseController@index
  GET|HEAD  api/courses/{id} ............................................................... Api\CourseController@show
  GET|HEAD  api/crm/contacts .......................................................... Api\CrmContactController@index
  GET|HEAD  api/crm/contacts/{id} ...................................................... Api\CrmContactController@show
  GET|HEAD  api/crm/leads ................................................................ Api\CrmLeadController@index
  GET|HEAD  api/crm/leads/{id} ............................................................ Api\CrmLeadController@show
  GET|HEAD  api/enrollments ........................................................... Api\EnrollmentController@index
  GET|HEAD  api/goods-receipts ...................................................... Api\GoodsReceiptController@index
  POST      api/goods-receipts ...................................................... Api\GoodsReceiptController@store
  GET|HEAD  api/goods-receipts/{id} .................................................. Api\GoodsReceiptController@show
  POST      api/goods-receipts/{id}/cancel ......................................... Api\GoodsReceiptController@cancel
  POST      api/goods-receipts/{id}/confirm ....................................... Api\GoodsReceiptController@confirm
  POST      api/goods-receipts/{id}/reverse ....................................... Api\GoodsReceiptController@reverse
  GET|HEAD  api/hr/attendance ......................................................... Api\HrApiController@attendance
  GET|HEAD  api/hr/employees ........................................................... Api\HrApiController@employees
  GET|HEAD  api/hr/payroll ............................................................... Api\HrApiController@payroll
  GET|HEAD  api/institute ............................................................... Api\AuthController@institute
  GET|HEAD  api/inventory/items ..................................................... Api\InventoryApiController@items
  GET|HEAD  api/inventory/movements ............................................. Api\InventoryApiController@movements
  GET|HEAD  api/inventory/stock ..................................................... Api\InventoryApiController@stock
  GET|HEAD  api/invoices ................................................................. Api\InvoiceController@index
  GET|HEAD  api/invoices/{id} ............................................................. Api\InvoiceController@show
  POST      api/login ....................................................................... Api\AuthController@login
  POST      api/logout ..................................................................... Api\AuthController@logout
  GET|HEAD  api/notifications ....................................................... Api\NotificationController@index
  POST      api/notifications/{id}/read .......................................... Api\NotificationController@markRead
  GET|HEAD  api/payments ................................................................. Api\PaymentController@index
  GET|HEAD  api/profile ................................................................... Api\AuthController@profile
  GET|HEAD  api/purchase-orders .................................................... Api\PurchaseOrderController@index
  POST      api/purchase-orders .................................................... Api\PurchaseOrderController@store
  GET|HEAD  api/purchase-orders/{id} ................................................ Api\PurchaseOrderController@show
  POST      api/purchase-orders/{id}/approve ..................................... Api\PurchaseOrderController@approve
  POST      api/purchase-orders/{id}/cancel ....................................... Api\PurchaseOrderController@cancel
  POST      api/purchase-orders/{id}/submit ....................................... Api\PurchaseOrderController@submit
  GET|HEAD  api/sales/deliveries ................................................... Api\SalesApiController@deliveries
  GET|HEAD  api/sales/invoices ....................................................... Api\SalesApiController@invoices
  GET|HEAD  api/sales/orders ........................................................... Api\SalesApiController@orders
  GET|HEAD  api/sales/quotations ................................................... Api\SalesApiController@quotations
  GET|HEAD  api/students ................................................................. Api\StudentController@index
  GET|HEAD  api/students/{id} ............................................................. Api\StudentController@show
  GET|HEAD  api/verify/certificate/{number} ................ api.certificate.verify ΓÇ║ Api\CertificateController@verify
  GET|HEAD  batches ............................................................ batches.index ΓÇ║ BatchController@index
  POST      batches ............................................................ batches.store ΓÇ║ BatchController@store
  GET|HEAD  batches/{batch} ...................................................... batches.show ΓÇ║ BatchController@show
  PUT       batches/{batch} .................................................. batches.update ΓÇ║ BatchController@update
  DELETE    batches/{batch} ................................................ batches.destroy ΓÇ║ BatchController@destroy
  POST      batches/{batch}/archive ........................................ batches.archive ΓÇ║ BatchController@archive
  POST      batches/{batch}/unarchive .................................. batches.unarchive ΓÇ║ BatchController@unarchive
  GET|HEAD  business/{institute} .................................................. business.show ΓÇ║ routes/web.php:240
  GET|HEAD  certificates ............................................ certificates.index ΓÇ║ CertificateController@index
  GET|HEAD  courses ........................................................... courses.index ΓÇ║ CourseController@index
  GET|HEAD  crm ......................................................... crm.dashboard ΓÇ║ CrmDashboardController@index
  GET|HEAD  dev/page-marker ..................................................... dev.page-marker ΓÇ║ routes/web.php:205
  POST      dev/page-marker/toggle ....................................... dev.page-marker.toggle ΓÇ║ routes/web.php:209
  POST      email/verification-notification ... verification.send ΓÇ║ Auth\EmailVerificationNotificationController@store
  GET|HEAD  email/verify ..................................... verification.notice ΓÇ║ Auth\VerificationPromptController
  GET|HEAD  email/verify/{id}/{hash} ................................ verification.verify ΓÇ║ Auth\VerifyEmailController
  GET|HEAD  exams ................................................................. exams.index ΓÇ║ ExamController@index
  POST      exams/send-to-exam/{batch} .................................. exams.sendToExam ΓÇ║ ExamController@sendToExam
  GET|HEAD  exams/{exam} ............................................................ exams.show ΓÇ║ ExamController@show
  PUT       exams/{exam} ........................................................ exams.update ΓÇ║ ExamController@update
  DELETE    exams/{exam} ...................................................... exams.destroy ΓÇ║ ExamController@destroy
  POST      exams/{exam}/marks ............................................ exams.saveMarks ΓÇ║ ExamController@saveMarks
  GET|HEAD  finance ............................................. finance.dashboard ΓÇ║ FinanceDashboardController@index
  GET|HEAD  finance/audit ......................................... finance.audit.index ΓÇ║ FinanceAuditController@index
  GET|HEAD  finance/budgets/dashboard ...................... finance.budgets.dashboard ΓÇ║ FinanceBudgetController@index
  GET|HEAD  finance/chart-of-accounts ........ finance.chart-of-accounts.index ΓÇ║ FinanceChartOfAccountController@index
  GET|HEAD  finance/education/dashboard ............................. finance.education.dashboard ΓÇ║ routes/web.php:262
  GET|HEAD  finance/education/fee-structures ... finance.education.fee-structures.index ΓÇ║ FeeStructureController@index
  GET|HEAD  finance/exchange-rates ................ finance.exchange-rates.index ΓÇ║ FinanceExchangeRateController@index
  GET|HEAD  finance/fx-revaluations ............. finance.fx-revaluations.index ΓÇ║ FinanceFxRevaluationController@index
  GET|HEAD  finance/invoices ................................. finance.invoices.index ΓÇ║ FinanceInvoiceController@index
  GET|HEAD  finance/journals ................................. finance.journals.index ΓÇ║ FinanceJournalController@index
  GET|HEAD  finance/online-payments/gateways ................... finance.online-payments.gateways ΓÇ║ routes/web.php:276
  GET|HEAD  finance/opening-balances/create . finance.opening-balances.create ΓÇ║ FinanceOpeningBalanceController@create
  GET|HEAD  finance/parties ..................................... finance.parties.index ΓÇ║ FinancePartyController@index
  GET|HEAD  finance/payment-methods ............. finance.payment-methods.index ΓÇ║ FinancePaymentMethodController@index
  GET|HEAD  finance/payments ................................. finance.payments.index ΓÇ║ FinancePaymentController@index
  GET|HEAD  finance/periods .................................... finance.periods.index ΓÇ║ FinancePeriodController@index
  GET|HEAD  finance/reports/trial-balance finance.reports.trial-balance ΓÇ║ Accounting\AccountingReportController@trialΓÇª
  GET|HEAD  forgot-password ..................... password.request ΓÇ║ Auth\ForgotPasswordController@showLinkRequestForm
  POST      forgot-password ........................ password.email ΓÇ║ Auth\ForgotPasswordController@sendResetLinkEmail
  GET|HEAD  guardian .................................. guardian.dashboard ΓÇ║ Guardian\GuardianDashboardController@show
  GET|HEAD  guardian/forgot-password guardian.password.request ΓÇ║ Auth\GuardianForgotPasswordController@showLinkRequesΓÇª
  POST      guardian/forgot-password guardian.password.email ΓÇ║ Auth\GuardianForgotPasswordController@sendResetLinkEmaΓÇª
  GET|HEAD  guardian/login ............................... guardian.login ΓÇ║ Auth\GuardianLoginController@showLoginForm
  POST      guardian/login ................................ guardian.login.submit ΓÇ║ Auth\GuardianLoginController@login
  POST      guardian/logout .................................................. guardian.logout ΓÇ║ Auth\LogoutController
  GET|HEAD  guardian/notifications ............ guardian.notifications ΓÇ║ Guardian\GuardianNotificationController@index
  GET|HEAD  guardian/profile .............................. guardian.profile ΓÇ║ Guardian\GuardianProfileController@show
  PUT       guardian/profile/password .. guardian.profile.password ΓÇ║ Guardian\GuardianProfileController@updatePassword
  POST      guardian/reset-password ............ guardian.password.update ΓÇ║ Auth\GuardianResetPasswordController@reset
  GET|HEAD  guardian/reset-password/{token} guardian.password.reset ΓÇ║ Auth\GuardianResetPasswordController@showResetFΓÇª
  POST      guardian/student/switch ......... guardian.student.switch ΓÇ║ Guardian\GuardianStudentSwitchController@store
  GET|HEAD  guardian/students ........................... guardian.students ΓÇ║ Guardian\GuardianStudentController@index
  GET|HEAD  guardian/students/{student} ............. guardian.students.show ΓÇ║ Guardian\GuardianStudentController@show
  GET|HEAD  guardian/students/{student}/attendance guardian.students.attendance ΓÇ║ Guardian\GuardianAttendanceControllΓÇª
  GET|HEAD  guardian/students/{student}/certificates guardian.students.certificates ΓÇ║ Guardian\GuardianCertificateConΓÇª
  GET|HEAD  guardian/students/{student}/documents guardian.students.documents ΓÇ║ Guardian\GuardianDocumentController@iΓÇª
  GET|HEAD  guardian/students/{student}/documents/{document}/download guardian.students.documents.download ΓÇ║ GuardianΓÇª
  GET|HEAD  guardian/students/{student}/fees ............ guardian.students.fees ΓÇ║ Guardian\GuardianFeeController@show
  GET|HEAD  guardian/students/{student}/results ... guardian.students.results ΓÇ║ Guardian\GuardianResultController@show
  GET|HEAD  hr ......................................................... hr.dashboard ΓÇ║ Hr\HrDashboardController@index
  GET|HEAD  hr/payroll/periods ......................................... hr.payroll.periods.index ΓÇ║ routes/web.php:246
  GET|HEAD  institute/login ........................ institute.login ΓÇ║ Auth\InstituteUserLoginController@showLoginForm
  POST      institute/login ......................... institute.login.submit ΓÇ║ Auth\InstituteUserLoginController@login
  GET|HEAD  institute/register ............ institute.register ΓÇ║ Auth\InstituteUserRegisterController@showRegisterForm
  POST      institute/register ............. institute.register.submit ΓÇ║ Auth\InstituteUserRegisterController@register
  GET|HEAD  livewire-e025ea8a/css/{component}.css vendor/livewire/livewire/src/Features/SupportCssModules/SupportCssMΓÇª
  GET|HEAD  livewire-e025ea8a/css/{component}.global.css vendor/livewire/livewire/src/Features/SupportCssModules/SuppΓÇª
  GET|HEAD  livewire-e025ea8a/js/{component}.js vendor/livewire/livewire/src/Features/SupportJsModules/SupportJsModulΓÇª
  GET|HEAD  livewire-e025ea8a/livewire.csp.min.js.map ................... Livewire\Mechanisms ΓÇ║ FrontendAssets@cspMaps
  GET|HEAD  livewire-e025ea8a/livewire.js ................ Livewire\Mechanisms ΓÇ║ FrontendAssets@returnJavaScriptAsFile
  GET|HEAD  livewire-e025ea8a/livewire.min.js.map .......................... Livewire\Mechanisms ΓÇ║ FrontendAssets@maps
  GET|HEAD  livewire-e025ea8a/preview-file/{filename} livewire.preview-file ΓÇ║ Livewire\Features ΓÇ║ FilePreviewControllΓÇª
  POST      livewire-e025ea8a/update ..... default-livewire.update ΓÇ║ Livewire\Mechanisms ΓÇ║ HandleRequests@handleUpdate
  POST      livewire-e025ea8a/upload-file ..... livewire.upload-file ΓÇ║ Livewire\Features ΓÇ║ FileUploadController@handle
  GET|HEAD  login ..................................................... login ΓÇ║ Auth\UserLoginController@showLoginForm
  POST      login ...................................................... login.submit ΓÇ║ Auth\UserLoginController@login
  POST      logout .................................................................... logout ΓÇ║ Auth\LogoutController
  GET|HEAD  notifications ................................ notifications.index ΓÇ║ InstituteNotificationController@index
  POST      notifications/read-all .............. notifications.read-all ΓÇ║ InstituteNotificationController@markAllRead
  POST      notifications/{notification}/read .......... notifications.read ΓÇ║ InstituteNotificationController@markRead
  GET|HEAD  owner/profile ......................................................... owner.profile ΓÇ║ routes/web.php:274
  GET|HEAD  purchase/orders .................................... purchase.orders.index ΓÇ║ PurchaseOrderController@index
  GET|HEAD  recycle ....................................................... recycle.index ΓÇ║ RecycleBinController@index
  GET|HEAD  register ..................................... owner.register ΓÇ║ Auth\OwnerRegisterController@showSelection
  POST      register ................................... owner.register.submit ΓÇ║ Auth\OwnerRegisterController@register
  GET|HEAD  register/form ................................ owner.register.form ΓÇ║ Auth\OwnerRegisterController@showForm
  POST      register/selection ........................ owner.register.selection ΓÇ║ Auth\OwnerRegisterController@select
  POST      reset-password ...................................... password.update ΓÇ║ Auth\ResetPasswordController@reset
  GET|HEAD  reset-password/{token} ....................... password.reset ΓÇ║ Auth\ResetPasswordController@showResetForm
  GET|HEAD  sales/settings ................................ sales.settings.index ΓÇ║ Sales\SalesSettingsController@index
  GET|HEAD  sanctum/csrf-cookie .................... sanctum.csrf-cookie ΓÇ║ Laravel\Sanctum ΓÇ║ CsrfCookieController@show
  GET|HEAD  settings ................................................ settings.index ΓÇ║ InstituteSettingController@edit
  PUT       settings ............................................. settings.update ΓÇ║ InstituteSettingController@update
  GET|HEAD  settings/account ................................... settings.account ΓÇ║ InstituteSettingController@account
  GET|HEAD  settings/appearance .......................... settings.appearance ΓÇ║ InstituteSettingController@appearance
  GET|HEAD  staff/invite ............................................. staff.invite ΓÇ║ StaffInvitationController@create
  POST      staff/invite ........................................ staff.invite.store ΓÇ║ StaffInvitationController@store
  GET|HEAD  storage/{path} storage.local ΓÇ║ vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemServiceProvidΓÇª
  PUT       storage/{path} storage.local.upload ΓÇ║ vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemServicΓÇª
  GET|HEAD  students ........................................................ students.index ΓÇ║ StudentController@index
  POST      students ........................................................ students.store ΓÇ║ StudentController@store
  GET|HEAD  students/create ............................................... students.create ΓÇ║ StudentController@create
  GET|HEAD  students/{student} ................................................ students.show ΓÇ║ StudentController@show
  PUT       students/{student} ............................................ students.update ΓÇ║ StudentController@update
  DELETE    students/{student} .......................................... students.destroy ΓÇ║ StudentController@destroy
  GET|HEAD  students/{student}/edit ........................................... students.edit ΓÇ║ StudentController@edit
  POST      students/{student}/enroll ..................................... students.enroll ΓÇ║ StudentController@enroll
  POST      students/{student}/photo .................................. students.photo ΓÇ║ StudentController@uploadPhoto
  GET|HEAD  super-admin/database .. super-admin.database.dashboard ΓÇ║ SuperAdmin\DatabaseOperationsController@dashboard
  GET|HEAD  super-admin/database/audit .... super-admin.database.audit ΓÇ║ SuperAdmin\DatabaseOperationsController@audit
  GET|HEAD  super-admin/database/backups super-admin.database.backups ΓÇ║ SuperAdmin\DatabaseOperationsController@backuΓÇª
  POST      super-admin/database/backups/create super-admin.database.backups.create ΓÇ║ SuperAdmin\DatabaseOperationsCoΓÇª
  POST      super-admin/database/backups/retention/execute super-admin.database.retention.execute ΓÇ║ SuperAdmin\DatabaΓÇª
  POST      super-admin/database/backups/{backup}/verify super-admin.database.backups.verify ΓÇ║ SuperAdmin\DatabaseOpeΓÇª
  GET|HEAD  super-admin/database/control-center super-admin.database.control-center ΓÇ║ SuperAdmin\DatabaseControlCenteΓÇª
  GET|HEAD  super-admin/database/control-center/json super-admin.database.control-center.json ΓÇ║ SuperAdmin\DatabaseCoΓÇª
  GET|HEAD  super-admin/database/health . super-admin.database.health ΓÇ║ SuperAdmin\DatabaseOperationsController@health
  GET|HEAD  super-admin/database/integrity super-admin.database.integrity ΓÇ║ SuperAdmin\DatabaseOperationsController@iΓÇª
  GET|HEAD  super-admin/database/monitoring super-admin.database.monitoring ΓÇ║ SuperAdmin\DatabaseMonitoringControllerΓÇª
  POST      super-admin/database/monitoring/refresh super-admin.database.monitoring.refresh ΓÇ║ SuperAdmin\DatabaseMoniΓÇª
  GET|HEAD  super-admin/database/performance super-admin.database.performance ΓÇ║ SuperAdmin\DatabaseOperationsControllΓÇª
  GET|HEAD  super-admin/database/recovery super-admin.database.recovery ΓÇ║ SuperAdmin\DatabaseOperationsController@recΓÇª
  POST      super-admin/database/recovery/drill super-admin.database.recovery.drill ΓÇ║ SuperAdmin\DatabaseOperationsCoΓÇª
  POST      super-admin/database/refresh super-admin.database.refresh ΓÇ║ SuperAdmin\DatabaseOperationsController@refreΓÇª
  GET|HEAD  super-admin/database/status . super-admin.database.status ΓÇ║ SuperAdmin\DatabaseOperationsController@status
  GET|HEAD  sync ............................................................ sync.index ΓÇ║ OfflineSyncController@index
  POST      sync/upload .................................................... sync.upload ΓÇ║ OfflineSyncController@store
  POST      sync/{queue}/approve ........................................ sync.approve ΓÇ║ OfflineSyncController@approve
  POST      sync/{queue}/reject ........................................... sync.reject ΓÇ║ OfflineSyncController@reject
  GET|HEAD  teachers ........................................................ teachers.index ΓÇ║ TeacherController@index
  GET|HEAD  two-factor-challenge ......................... two-factor.login ΓÇ║ Auth\TwoFactorChallengeController@create
  POST      two-factor-challenge .................... two-factor.login.store ΓÇ║ Auth\TwoFactorChallengeController@store
  POST      ui/columns ...................................................... ui.columns ΓÇ║ UiPreferenceController@save
  GET|HEAD  up ........... vendor/laravel/framework/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php:219
  GET|HEAD  verify ........................................................ verify ΓÇ║ InstituteSettingController@verify
  GET|HEAD  verify/certificate .......................... verify.certificate.index ΓÇ║ VerifyCertificateController@index
  POST      verify/certificate .......................... verify.certificate.check ΓÇ║ VerifyCertificateController@check
  GET|HEAD  verify/certificate/{certificate_number} ............ verify.certificate ΓÇ║ VerifyCertificateController@show
  GET|HEAD  workflows ..................................................... workflows.index ΓÇ║ WorkflowController@index
  GET|HEAD  workspace .................................................. workspace.picker ΓÇ║ WorkspaceController@picker
  GET|HEAD  workspace/create ........................................... workspace.create ΓÇ║ WorkspaceController@create
  POST      workspace/create ............................................. workspace.store ΓÇ║ WorkspaceController@store
  POST      workspace/switch/{institutionId} ........................... workspace.switch ΓÇ║ WorkspaceController@switch
```

## Full dumps

- `docs/ROUTES_FULL.txt` � full `php artisan route:list --no-ansi` (﻿ ...)
- `docs/ROUTES_FULL.json` � JSON (0 entries)
- `missing_routes_stub.json` � 778 missing names auto-stubbed in `routes/web.php:279`
