<?php

use App\Http\Controllers\Auth\GuardianForgotPasswordController;
use App\Http\Controllers\Auth\GuardianLoginController;
use App\Http\Controllers\Auth\GuardianResetPasswordController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Guardian\GuardianAttendanceController;
use App\Http\Controllers\Guardian\GuardianCertificateController;
use App\Http\Controllers\Guardian\GuardianDashboardController;
use App\Http\Controllers\Guardian\GuardianDocumentController;
use App\Http\Controllers\Guardian\GuardianFeeController;
use App\Http\Controllers\Guardian\GuardianNotificationController;
use App\Http\Controllers\Guardian\GuardianProfileController;
use App\Http\Controllers\Guardian\GuardianResultController;
use App\Http\Controllers\Guardian\GuardianStudentController;
use App\Http\Controllers\Guardian\GuardianStudentSwitchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parent / Guardian Portal (Step 47)
|--------------------------------------------------------------------------
|
| Dedicated, strictly read-only portal for guardians. Guardians authenticate
| through their own 'guardian' guard; every student-scoped route is authorized
| server-side by GuardianService::requireStudent() so an unrelated guardian can
| never reach another institute's student.
|
*/

Route::prefix('guardian')->name('guardian.')->group(function () {

    // ---- Guest: login / password reset ----
    Route::get('login', [GuardianLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [GuardianLoginController::class, 'login'])
        ->middleware('throttle:30,15')
        ->name('login.submit');

    Route::get('forgot-password', [GuardianForgotPasswordController::class, 'showLinkRequestForm'])
        ->middleware('guest:guardian')
        ->name('password.request');
    Route::post('forgot-password', [GuardianForgotPasswordController::class, 'sendResetLinkEmail'])
        ->middleware(['guest:guardian', 'throttle:10,10'])
        ->name('password.email');

    Route::get('reset-password/{token}', [GuardianResetPasswordController::class, 'showResetForm'])
        ->middleware('guest:guardian')
        ->name('password.reset');
    Route::post('reset-password', [GuardianResetPasswordController::class, 'reset'])
        ->middleware(['guest:guardian', 'throttle:10,10'])
        ->name('password.update');

    // GET fallback outside auth — allows logout even when session/CSRF expired (non-destructive)
    Route::get('logout', LogoutController::class)->name('logout.get');

    // ---- Authenticated portal (read-only) ----
    Route::middleware(['auth:guardian', 'tenant'])->group(function () {

        Route::post('logout', LogoutController::class)->name('logout');

        Route::get('/', [GuardianDashboardController::class, 'show'])->name('dashboard');

        Route::get('students', [GuardianStudentController::class, 'index'])->name('students');

        Route::get('students/{student}', [GuardianStudentController::class, 'show'])
            ->whereNumber('student')
            ->name('students.show');

        Route::get('students/{student}/attendance', [GuardianAttendanceController::class, 'show'])
            ->whereNumber('student')
            ->name('students.attendance');

        Route::get('students/{student}/results', [GuardianResultController::class, 'show'])
            ->whereNumber('student')
            ->name('students.results');

        Route::get('students/{student}/fees', [GuardianFeeController::class, 'show'])
            ->whereNumber('student')
            ->name('students.fees');

        Route::get('students/{student}/certificates', [GuardianCertificateController::class, 'show'])
            ->whereNumber('student')
            ->name('students.certificates');

        Route::get('students/{student}/documents', [GuardianDocumentController::class, 'index'])
            ->whereNumber('student')
            ->name('students.documents');

        Route::get('students/{student}/documents/{document}/download', [GuardianDocumentController::class, 'download'])
            ->whereNumber('student')
            ->whereNumber('document')
            ->name('students.documents.download');

        Route::get('notifications', [GuardianNotificationController::class, 'index'])->name('notifications');

        Route::get('profile', [GuardianProfileController::class, 'show'])->name('profile');
        Route::put('profile/password', [GuardianProfileController::class, 'updatePassword'])->name('profile.password');

        Route::post('student/switch', [GuardianStudentSwitchController::class, 'store'])->name('student.switch');
    });
});
