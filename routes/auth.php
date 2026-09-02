<?php

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PhonePasswordResetController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerificationPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Jetstream-style auth features (backed by Laravel Fortify)
|--------------------------------------------------------------------------
|
| The application keeps its own portal login/register/approval flow, so these
| routes only cover the features added by the Jetstream engine: password
| reset, email verification, two-factor authentication and account security
| (sessions). Everything here reuses the existing Bootstrap/bilingual UI.
|
| All routes are guarded for BOTH portals; the SetFortifyGuard middleware
| pins the active guard per request.
|
*/

Route::middleware(['web', 'fortifyguard'])->group(function () {

    // ---- Password reset (guest — all session guards) ----
    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('password.request');

    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,10'])
        ->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('password.reset');

    Route::post('reset-password', [ResetPasswordController::class, 'reset'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,10'])
        ->name('password.update');

    // ---- Phone OTP password recovery (guest — web guard, enumeration-safe) ----
    Route::get('forgot-password/phone', [PhonePasswordResetController::class, 'showRequestForm'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('password.phone.request');
    Route::post('forgot-password/phone', [PhonePasswordResetController::class, 'requestOtp'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,10'])
        ->name('password.phone.email');
    Route::get('forgot-password/phone/verify', [PhonePasswordResetController::class, 'showVerifyForm'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('password.phone.verify.form');
    Route::post('forgot-password/phone/verify', [PhonePasswordResetController::class, 'verifyOtp'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,10'])
        ->name('password.phone.verify');
    Route::get('reset-password/phone', [PhonePasswordResetController::class, 'showResetForm'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('password.phone.reset.form');
    Route::post('reset-password/phone', [PhonePasswordResetController::class, 'reset'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,10'])
        ->name('password.phone.update');

    // ---- Two-factor challenge (guest — all session guards) ----
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
        ->middleware('guest:institute_user,platform_admin,web')
        ->name('two-factor.login');

    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,1'])
        ->name('two-factor.login.store');

    Route::post('two-factor-challenge/switch', [TwoFactorChallengeController::class, 'switchMethod'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,1'])
        ->name('two-factor.login.switch');

    Route::post('two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])
        ->middleware(['guest:institute_user,platform_admin,web', 'throttle:10,1'])
        ->name('two-factor.login.resend');

    // ---- Email verification (authenticated, any guard — web/institute_user/platform_admin) ----
    Route::get('email/verify', VerificationPromptController::class)
        ->middleware('auth:platform_admin,institute_user,web')
        ->name('verification.notice');

    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['auth:platform_admin,institute_user,web', 'signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['auth:platform_admin,institute_user,web', 'throttle:6,1'])
        ->name('verification.send');
});

Route::middleware(['fortifyguard'])->group(function () {

    // ---- Institute user security (2FA + sessions) ----
    Route::middleware(['auth:institute_user,web', 'tenant'])->group(function () {
        Route::get('account/security', SecurityController::class)->name('account.security');

        Route::post('account/security/two-factor/enable', [SecurityController::class, 'enable'])
            ->middleware('verified')->name('account.two-factor.enable');
        Route::post('account/security/two-factor/confirm', [SecurityController::class, 'confirm'])
            ->middleware('verified')->name('account.two-factor.confirm');
        Route::post('account/security/two-factor/disable', [SecurityController::class, 'disable'])
            ->middleware('verified')->name('account.two-factor.disable');
        Route::get('account/security/two-factor/qr-code', [SecurityController::class, 'qrCode'])
            ->middleware('verified')->name('account.two-factor.qr-code');
        Route::get('account/security/two-factor/recovery-codes', [SecurityController::class, 'recoveryCodes'])
            ->middleware('verified')->name('account.two-factor.recovery-codes');
        Route::post('account/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])
            ->middleware('verified')->name('account.two-factor.regenerate-recovery-codes');

        Route::post('account/security/sessions/revoke', [SecurityController::class, 'revokeSessions'])
            ->name('account.sessions.revoke');
        Route::post('account/security/email/resend', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')->name('account.verification.send');

        // E18/E19.1: Optional SMS/Email 2FA (allow unverified to get friendly message)
        Route::post('account/security/two-factor/sms/enable', [SecurityController::class, 'enableSms'])->name('account.two-factor.sms.enable');
        Route::post('account/security/two-factor/sms/disable', [SecurityController::class, 'disableSms'])->name('account.two-factor.sms.disable');
        Route::post('account/security/two-factor/email/enable', [SecurityController::class, 'enableEmail'])->name('account.two-factor.email.enable');
        Route::post('account/security/two-factor/email/disable', [SecurityController::class, 'disableEmail'])->name('account.two-factor.email.disable');
        Route::post('account/security/two-factor/preferred', [SecurityController::class, 'updatePreferred'])->name('account.two-factor.preferred');
    });

    // ---- Platform admin security (2FA + sessions) ----
    Route::middleware(['auth:platform_admin'])->group(function () {
        Route::get('admin/security', SecurityController::class)->name('admin.security');

        Route::post('admin/security/two-factor/enable', [SecurityController::class, 'enable'])
            ->middleware('verified')->name('admin.two-factor.enable');
        Route::post('admin/security/two-factor/confirm', [SecurityController::class, 'confirm'])
            ->middleware('verified')->name('admin.two-factor.confirm');
        Route::post('admin/security/two-factor/disable', [SecurityController::class, 'disable'])
            ->middleware('verified')->name('admin.two-factor.disable');
        Route::get('admin/security/two-factor/qr-code', [SecurityController::class, 'qrCode'])
            ->middleware('verified')->name('admin.two-factor.qr-code');
        Route::get('admin/security/two-factor/recovery-codes', [SecurityController::class, 'recoveryCodes'])
            ->middleware('verified')->name('admin.two-factor.recovery-codes');
        Route::post('admin/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])
            ->middleware('verified')->name('admin.two-factor.regenerate-recovery-codes');

        Route::post('admin/security/sessions/revoke', [SecurityController::class, 'revokeSessions'])
            ->name('admin.sessions.revoke');
        Route::post('admin/security/sessions/flush-all', [SecurityController::class, 'flushAllSessions'])
            ->name('admin.sessions.flush-all');
        Route::post('admin/security/email/resend', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')->name('admin.verification.send');

        Route::post('admin/security/two-factor/sms/enable', [SecurityController::class, 'enableSms'])->name('admin.two-factor.sms.enable');
        Route::post('admin/security/two-factor/sms/disable', [SecurityController::class, 'disableSms'])->name('admin.two-factor.sms.disable');
        Route::post('admin/security/two-factor/email/enable', [SecurityController::class, 'enableEmail'])->name('admin.two-factor.email.enable');
        Route::post('admin/security/two-factor/email/disable', [SecurityController::class, 'disableEmail'])->name('admin.two-factor.email.disable');
        Route::post('admin/security/two-factor/preferred', [SecurityController::class, 'updatePreferred'])->name('admin.two-factor.preferred');
    });

    // ---- Guardian security (2FA + sessions) – E8.2 reuse existing TOTP  ----
    Route::middleware(['auth:guardian'])->group(function () {
        Route::get('guardian/security', SecurityController::class)->name('guardian.security');
        Route::post('guardian/security/two-factor/enable', [SecurityController::class, 'enable'])->name('guardian.two-factor.enable');
        Route::post('guardian/security/two-factor/confirm', [SecurityController::class, 'confirm'])->name('guardian.two-factor.confirm');
        Route::post('guardian/security/two-factor/disable', [SecurityController::class, 'disable'])->name('guardian.two-factor.disable');
        Route::get('guardian/security/two-factor/qr-code', [SecurityController::class, 'qrCode'])->name('guardian.two-factor.qr-code');
        Route::get('guardian/security/two-factor/recovery-codes', [SecurityController::class, 'recoveryCodes'])->name('guardian.two-factor.recovery-codes');
        Route::post('guardian/security/two-factor/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('guardian.two-factor.regenerate-recovery-codes');
        Route::post('guardian/security/sessions/revoke', [SecurityController::class, 'revokeSessions'])->name('guardian.sessions.revoke');
        Route::post('guardian/security/two-factor/sms/enable', [SecurityController::class, 'enableSms'])->name('guardian.two-factor.sms.enable');
        Route::post('guardian/security/two-factor/sms/disable', [SecurityController::class, 'disableSms'])->name('guardian.two-factor.sms.disable');
        Route::post('guardian/security/two-factor/email/enable', [SecurityController::class, 'enableEmail'])->name('guardian.two-factor.email.enable');
        Route::post('guardian/security/two-factor/email/disable', [SecurityController::class, 'disableEmail'])->name('guardian.two-factor.email.disable');
        Route::post('guardian/security/two-factor/preferred', [SecurityController::class, 'updatePreferred'])->name('guardian.two-factor.preferred');
    });
});
