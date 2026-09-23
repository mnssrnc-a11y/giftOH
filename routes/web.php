<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConfigController;
// Public routes
Route::get('/config/firebase', [ConfigController::class, 'firebaseConfig'])->name('config.firebase');
Route::get('/', [PageController::class, 'landing'])->name('landing');
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::post('/login', [AccountController::class, 'storeLogin'])->name('login.store');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/register', [AccountController::class, 'register'])->name('register');
Route::post('/register', [AccountController::class, 'storeRegister'])->name('register.store');
Route::get('/register/verify', [AccountController::class, 'showRegisterVerifyForm'])->name('register.verify-code.form');
Route::post('/register/verify', [VerificationController::class, 'verifyRegister'])->name('register.verify-code');
Route::post('/register/resend-code', [VerificationController::class, 'resendRegisterCode'])->name('register.resend-code');
Route::post('/logout', [PageController::class, 'logout'])->name('logout');
Route::get('/login/verify', [AccountController::class, 'showLoginVerifyForm'])->name('login.verify-code.form');
Route::post('/login/verify', [VerificationController::class, 'verifyLogin'])->name('login.verify-code');
Route::post('/login/resend-code', [VerificationController::class, 'resendLoginCode'])->name('login.resend-code');
Route::post('/notifications/{id}/read', [PageController::class, 'markNotificationRead'])->name('notifications.read');
Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
Route::get('/settings', [PageController::class, 'settings'])->name('settings');
Route::post('/settings', [PageController::class, 'updateSettings'])->name('settings.update');
Route::post('/settings/profile-picture', [AccountController::class, 'updateProfilePicture'])->name('settings.profile-picture');

// Password Reset Flow
Route::get('/forgot-password', [AccountController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/forgot-password/send-code', [AccountController::class, 'sendResetCode'])->name('password.send-code');
Route::get('/forgot-password/verify-code', [AccountController::class, 'showVerifyCodeForm'])->name('password.verify-code.form');
Route::post('/forgot-password/verify-code', [VerificationController::class, 'verifyPasswordReset'])->name('password.verify-code');
Route::get('/reset-password', [AccountController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/reset-password', [AccountController::class, 'updatePassword'])->name('password.update');

// App pages (UI prototype)
Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
Route::get('/iot-monitor', [PageController::class, 'iotMonitor'])->name('iot-monitor');
Route::get('/reports', [PageController::class, 'reports'])->name('reports');
Route::get('/donations', [PageController::class, 'donations'])->name('donations');


Route::middleware(['auth', 'role:user'])->group(function(){
    foreach (['groups', 'fundraisers'] as $screen) {
        Route::view('/'.$screen, 'users.flow', ['screen' => $screen])->name($screen);
    }
    Route::get('/request-status', [PageController::class, 'requestStatus'])->name('request-status');
    Route::get('/activity', [PageController::class, 'activity'])->name('activity');
    Route::get('/user', [PageController::class, 'user'])->name('user');

    // Fund Requests & Transactions
    Route::get('/fund-request', [PageController::class, 'fundrequest'])->name('fund-request');
    Route::post('/fund-request', [FundController::class, 'storeFund'])->name('fund-request.store');
    Route::get('/fund-request/verify', [PageController::class, 'showFundRequestVerifyForm'])->name('fund-request.verify.form');
    Route::post('/fund-request/verify', [VerificationController::class, 'verifyFundRequest'])->name('fund-request.verify');
    Route::post('/fund-request/resend-code', [VerificationController::class, 'resendFundRequestCode'])->name('fund-request.resend-code');
    Route::get('/fund-request/{id}', [PageController::class, 'showFundRequest'])->name('fund-request.show');

    Route::get('/dashboarduser', [PageController::class, 'dashboardUser'])->name('dashboarduser');
    Route::get('/user/update', fn () => view('users.update'))->name('user.edit');
    Route::post('/user/update', [AccountController::class, 'updateUser'])->name('user.update');
    Route::get('/change-password', [AccountController::class, 'showChangePasswordForm'])->name('change-password.form');
    Route::post('/change-password', [AccountController::class, 'changePassword'])->name('change-password');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/iot-monitor', [PageController::class, 'iotMonitor'])->name('iot-monitor');
    Route::get('/reports', [PageController::class, 'reports'])->name('reports');
    Route::get('/donations', [PageController::class, 'donations'])->name('donations');
    Route::get('/admin', [AdminController::class, 'admin'])->name('admin');
    Route::get('/admin/fund-approval-verify', [AdminController::class, 'adminApproval'])->name('admin.fund-approval-verify');
    Route::post('/admin/fund-request/{id}/approve', [AdminController::class, 'adminFundApprove'])->name('admin.fund-request.approve');
    Route::post('/admin/fund-request/{id}/reject', [AdminController::class, 'adminFundReject'])->name('admin.fund-request.reject');
    Route::get('/admin/approval-verify', [AdminController::class, 'adminApproval'])->name('admin.approval-verify');
    Route::post('/admin/fund-request/{id}/action', [AdminController::class, 'initiateApprovalAction'])->name('admin.fund-request.action');
    Route::get('/admin/fund-request/verify', [AdminController::class, 'showApprovalVerifyForm'])->name('admin.fund-request.verify.form');
    Route::post('/admin/fund-request/verify', [VerificationController::class, 'verifyApprovalAction'])->name('admin.fund-request.verify');
    Route::post('/admin/fund-request/resend-code', [VerificationController::class, 'resendApprovalCode'])->name('admin.fund-request.resend-code');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/superadmin', function () {
        return view('supperAdminPage.spAd_dashB');
    })->name('superadmin');
});