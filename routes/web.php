<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConfigController;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Auth;
// Public routes
Route::get(uri: '/config/firebase', action: [ConfigController::class, 'firebaseConfig'])->name('config.firebase');
Route::get(uri: '/', action: [PageController::class, 'landing'])->name('landing');
Route::get(uri: '/login', action: [PageController::class, 'login'])->name('login');
Route::post(uri: '/login', action: [AccountController::class, 'storeLogin'])->name('login.store');
Route::get(uri: '/about', action: [PageController::class, 'about'])->name('about');
Route::get(uri: '/register', action: [AccountController::class, 'register'])->name('register');
Route::post(uri: '/register', action: [AccountController::class, 'storeRegister'])->name('register.store');
Route::get(uri: '/register/verify', action: [AccountController::class, 'showRegisterVerifyForm'])->name('register.verify-code.form');
Route::post(uri: '/register/verify', action: [VerificationController::class, 'verifyRegister'])->name('register.verify-code');
Route::post(uri: '/register/resend-code', action: [VerificationController::class, 'resendRegisterCode'])->name('register.resend-code');
Route::post(uri: '/logout', action: [PageController::class, 'logout'])->name('logout');
Route::get(uri: '/login/verify', action: [AccountController::class, 'showLoginVerifyForm'])->name('login.verify-code.form');
Route::post(uri: '/login/verify', action: [VerificationController::class, 'verifyLogin'])->name('login.verify-code');
Route::post(uri: '/login/resend-code', action: [VerificationController::class, 'resendLoginCode'])->name('login.resend-code');

// Password Reset Flow
Route::get(uri: '/forgot-password', action: [AccountController::class, 'forgotPassword'])->name('forgot-password');
Route::post(uri: '/forgot-password/send-code', action: [AccountController::class, 'sendResetCode'])->name('password.send-code');
Route::get(uri: '/forgot-password/verify-code', action: [AccountController::class, 'showVerifyCodeForm'])->name('password.verify-code.form');
Route::post(uri: '/forgot-password/verify-code', action: [VerificationController::class, 'verifyPasswordReset'])->name('password.verify-code');
Route::get(uri: '/reset-password', action: [AccountController::class, 'showResetForm'])->name('password.reset.form');
Route::post(uri: '/reset-password', action: [AccountController::class, 'updatePassword'])->name('password.update');

// App pages (UI prototype)
Route::get(uri: '/dashboard', action: [PageController::class, 'dashboard'])->name('dashboard');
Route::get(uri: '/iot-monitor', action: [PageController::class, 'iotMonitor'])->name('iot-monitor');
Route::get(uri: '/reports', action: [PageController::class, 'reports'])->name('reports');
Route::get(uri: '/donations', action: [PageController::class, 'donations'])->name('donations');


Route::middleware('auth')->group(function(){
    Route::get(uri: '/user', action: [PageController::class, 'user'])->name('user');

    // Fund Requests & Transactions
    Route::get(uri: '/fund-request', action: [PageController::class, 'fundrequest'])->name('fund-request');
    Route::post(uri: '/fund-request', action: [PageController::class, 'storeFundRequest'])->name('fund-request.store');
    Route::get(uri: '/fund-request/verify', action: [PageController::class, 'showFundRequestVerifyForm'])->name('fund-request.verify.form');
    Route::post(uri: '/fund-request/verify', action: [VerificationController::class, 'verifyFundRequest'])->name('fund-request.verify');
    Route::post(uri: '/fund-request/resend-code', action:[VerificationController::class, 'resendFundRequestCode'])->name('fund-request.resend-code');

    // Admin Panel & Approvals
    Route::get(uri: '/admin', action: [AdminController::class, 'admin'])->name('admin');
    Route::get(uri: '/admin/fund-approval-verify', action: [AdminController::class, 'adminApproval'])->name('admin.fund-approval-verify');
    Route::post(uri: '/admin/fund-request/{id}/approve', action: [AdminController::class, 'adminFundApprove'])->name('admin.fund-request.approve');
    Route::post(uri: '/admin/fund-request/{id}/reject', action: [AdminController::class, 'adminFundReject'])->name('admin.fund-request.reject');
    Route::get(uri: '/admin/approval-verify', action: [AdminController::class, 'adminApproval'])->name('admin.approval-verify');
    Route::post(uri: '/admin/fund-request/{id}/action', action: [AdminController::class, 'initiateApprovalAction'])->name('admin.fund-request.action');
    Route::get(uri: '/admin/fund-request/verify', action: [AdminController::class, 'showApprovalVerifyForm'])->name('admin.fund-request.verify.form');
    Route::post(uri: '/admin/fund-request/verify', action: [VerificationController::class, 'verifyApprovalAction'])->name('admin.fund-request.verify');
    Route::post(uri: '/admin/fund-request/resend-code', action: [VerificationController::class, 'resendApprovalCode'])->name('admin.fund-request.resend-code');

    Route::get(uri: '/dashboarduser', action: [PageController::class, 'dashboardUser'])->name('dashboarduser');
    Route::get(uri: '/settings', action: [PageController::class, 'settings'])->name('settings');
    Route::post(uri: '/settings', action: [PageController::class, 'updateSettings'])->name('settings.update');
    Route::get(uri: '/change-password', action: [AccountController::class, 'showChangePasswordForm'])->name('change-password.form');
    Route::post(uri: '/change-password', action: [AccountController::class, 'changePassword'])->name('change-password');
});