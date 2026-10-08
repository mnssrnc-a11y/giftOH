<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminRequestController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\FundingFileController;
use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\RequestConversationController;
use App\Http\Controllers\SuperAdminPriceController;
use App\Http\Controllers\SuperAdminController;
// Public routes
Route::get('/config/firebase', [ConfigController::class, 'firebaseConfig'])->name('config.firebase');
Route::get('/', [PageController::class, 'landing'])->name('landing');
// Public uploads (profile pictures) when they are kept in Firebase instead of public/storage.
Route::get('/storage/{path}', [PublicFileController::class, 'show'])->where('path', '.*')->name('storage.public');
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
// Account pages and request files: any signed-in role, never guests.
Route::middleware('auth')->group(function () {
    // Notifications for every role: requesters about their requests, staff about work waiting for them.
    Route::post('/notifications/{id}/read', [PageController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [PageController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
    Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
    Route::get('/settings', [PageController::class, 'settings'])->name('settings');
    Route::post('/settings', [PageController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/profile-picture', [AccountController::class, 'updateProfilePicture'])->middleware('throttle:10,1')->name('settings.profile-picture');
    // Every role manages its own name, phone and password (the controller only touches the signed-in account).
    Route::get('/user/update', fn () => view('users.update'))->name('user.edit');
    Route::post('/user/update', [AccountController::class, 'updateUser'])->name('user.update');
    Route::get('/change-password', [AccountController::class, 'showChangePasswordForm'])->name('change-password.form');
    Route::post('/change-password', [AccountController::class, 'changePassword'])->middleware('throttle:5,1')->name('change-password');
    // Private files of a funding request: the requester and admins/super admins only (checked in the controller).
    Route::get('/fund-request/{id}/files/{path}', [FundingFileController::class, 'show'])->where('path', '.*')->name('fund-request.file');
    // Requester ↔ foundation conversation on a request (owner or staff; checked in the controller).
    Route::get('/fund-request/{id}/messages', [RequestConversationController::class, 'index'])->name('fund-request.messages');
    Route::post('/fund-request/{id}/messages', [RequestConversationController::class, 'store'])->middleware('throttle:30,1')->name('fund-request.messages.store');
});

// Password Reset Flow
Route::get('/forgot-password', [AccountController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/forgot-password/send-code', [AccountController::class, 'sendResetCode'])->name('password.send-code');
Route::get('/forgot-password/verify-code', [AccountController::class, 'showVerifyCodeForm'])->name('password.verify-code.form');
Route::post('/forgot-password/verify-code', [VerificationController::class, 'verifyPasswordReset'])->name('password.verify-code');
Route::get('/reset-password', [AccountController::class, 'showResetForm'])->name('password.reset.form');
Route::post('/reset-password', [AccountController::class, 'updatePassword'])->name('password.update');

// Old UI prototype page, now behind login.
Route::get('/dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))->middleware('auth')->name('dashboard');


Route::middleware(['auth', 'role:user'])->group(function(){
    Route::get('/request-status', [PageController::class, 'requestStatus'])->name('request-status');
    Route::get('/activity', [PageController::class, 'activity'])->name('activity');
    Route::get('/user', [PageController::class, 'user'])->name('user');

    // Fund Requests & Transactions
    Route::get('/fund-request', [PageController::class, 'fundrequest'])->name('fund-request');
    Route::post('/fund-request', [FundController::class, 'storeFund'])->middleware('throttle:5,1')->name('fund-request.store');
    Route::get('/fund-request/{id}', [PageController::class, 'showFundRequest'])->name('fund-request.show');
    Route::post('/fund-request/{id}/liquidation', [FundController::class, 'submitLiquidation'])->middleware('throttle:5,1')->name('fund-request.liquidation');
    Route::post('/fund-request/{id}/appeal', [FundController::class, 'appeal'])->middleware('throttle:5,1')->name('fund-request.appeal');
    Route::post('/fund-request/{id}/interview-response', [RequestConversationController::class, 'interviewResponse'])->name('fund-request.interview-response');
    Route::post('/fund-request/{id}/documents/{field}', [RequestConversationController::class, 'resubmitDocument'])->middleware('throttle:10,1')->name('fund-request.document');

    Route::get('/dashboarduser', [PageController::class, 'dashboardUser'])->name('dashboarduser');
});

// Live smart box monitor: admins, and the super admin from System monitor.
Route::get('/iot-monitor', [PageController::class, 'iotMonitor'])->middleware(['auth', 'role:admin,super_admin'])->name('iot-monitor');

Route::middleware(['auth', 'role:admin'])->group(function () {
    // The real reports live in the admin workspace's Reports tab.
    Route::get('/reports', fn () => redirect()->to(route('admin') . '#reports'))->name('reports');
    Route::get('/donations', [AdminController::class, 'donations'])->name('donations');
    Route::get('/admin', [AdminController::class, 'admin'])->name('admin');
    // Admin decisions are recommendations; the super admin finalizes them.
    Route::get('/admin/fund-request/verify', [AdminController::class, 'showApprovalVerifyForm'])->name('admin.fund-request.verify.form');
    Route::post('/admin/fund-request/verify', [VerificationController::class, 'verifyApprovalAction'])->name('admin.fund-request.verify');
    Route::post('/admin/fund-request/resend-code', [VerificationController::class, 'resendApprovalCode'])->name('admin.fund-request.resend-code');
    Route::get('/admin/fund-request/{id}/recommendation', [AdminController::class, 'recommendation'])->name('admin.fund-request.recommendation');
    // Request processing: social worker assessment, AI scoring, release of funds, liquidation review.
    Route::get('/admin/fund-request/{id}', [AdminRequestController::class, 'show'])->name('admin.fund-request.show');
    Route::post('/admin/fund-request/{id}/interview', [AdminRequestController::class, 'scheduleInterview'])->name('admin.fund-request.interview');
    Route::post('/admin/fund-request/{id}/assessment', [AdminRequestController::class, 'recordAssessment'])->name('admin.fund-request.assessment');
    Route::post('/admin/fund-request/{id}/budget', [AdminRequestController::class, 'setBudget'])->name('admin.fund-request.budget');
    Route::post('/admin/fund-request/{id}/notes', [AdminRequestController::class, 'addNote'])->middleware('throttle:30,1')->name('admin.fund-request.notes');
    Route::post('/admin/fund-request/{id}/documents/{field}/review', [AdminRequestController::class, 'reviewDocument'])->name('admin.fund-request.document-review');
    Route::post('/admin/fund-request/{id}/ai-score', [AdminRequestController::class, 'rescore'])->name('admin.fund-request.ai-score');
    Route::post('/admin/fund-request/{id}/disbursement', [AdminRequestController::class, 'recordDisbursement'])->name('admin.fund-request.disbursement');
    Route::post('/admin/fund-request/{id}/liquidation', [AdminRequestController::class, 'reviewLiquidation'])->name('admin.fund-request.liquidation');
    Route::post('/admin/fund-request/{id}/action', [AdminController::class, 'initiateApprovalAction'])->name('admin.fund-request.action');
    Route::post('/admin/posts', [AdminController::class, 'storePost'])->name('admin.posts.store');
    Route::delete('/admin/posts/{id}', [AdminController::class, 'destroyPost'])->name('admin.posts.destroy');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/superadmin', [SuperAdminController::class, 'index'])->name('superadmin');
    // Full request (requester, documents, assessment, budget, notes, conversation) for the final check.
    Route::get('/superadmin/fund-request/{id}', [SuperAdminController::class, 'showRequest'])->name('superadmin.fund-request.show');
    Route::post('/superadmin/fund-request/{id}/finalize', [SuperAdminController::class, 'finalize'])->name('superadmin.fund-request.finalize');
    Route::post('/superadmin/accounts/{id}', [SuperAdminController::class, 'updateAccount'])->name('superadmin.accounts.update');
    Route::post('/superadmin/settings/scores', [SuperAdminController::class, 'updateScores'])->name('superadmin.settings.scores');
    Route::post('/superadmin/settings/interval', [SuperAdminController::class, 'updateInterval'])->name('superadmin.settings.interval');
    Route::post('/superadmin/settings/mail', [SuperAdminController::class, 'startMailChange'])->middleware('throttle:5,10')->name('superadmin.settings.mail');
    Route::post('/superadmin/settings/mail/confirm', [SuperAdminController::class, 'confirmMailChange'])->name('superadmin.settings.mail.confirm');
    Route::post('/superadmin/settings/mail/test', [SuperAdminController::class, 'testMail'])->middleware('throttle:5,10')->name('superadmin.settings.mail.test');
    // Price list (Food, Medical, Cleaning materials): only the super admin changes items and prices.
    Route::post('/superadmin/prices/update', [SuperAdminPriceController::class, 'runUpdate'])->name('superadmin.prices.update');
    Route::get('/superadmin/prices/status', [SuperAdminPriceController::class, 'status'])->name('superadmin.prices.status');
    Route::post('/superadmin/prices/items', [SuperAdminPriceController::class, 'store'])->middleware('throttle:20,1')->name('superadmin.prices.store');
    Route::put('/superadmin/prices/items/{key}', [SuperAdminPriceController::class, 'update'])->name('superadmin.prices.update-item');
    Route::delete('/superadmin/prices/items/{key}', [SuperAdminPriceController::class, 'destroy'])->name('superadmin.prices.destroy');
});