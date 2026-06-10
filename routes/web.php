<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;

// Public routes
Route::get('/', [PageController::class, 'landing'])->name('landing');
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::post('/login', [PageController::class, 'storeLogin'])->name('login.store');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/register', [PageController::class, 'register'])->name('register');
Route::post('/register', [PageController::class, 'storeRegister'])->name('register.store');
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/logout',[PageController::class, 'logout'])->name('logout');

//guest routes
Route::middleware('guest')->group(function(){
    Route::get('/register', [PageController::class, 'register'])->name('register');
    Route::post('/register', [PageController::class, 'storeRegister'])->name('register.store');
    Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('forgot-password');
});

// App pages (UI prototype)
Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
Route::get('/iot-monitor', [PageController::class, 'iotMonitor'])->name('iot-monitor');
Route::get('/donations', [PageController::class, 'donations'])->name('donations');
Route::get('/reports', [PageController::class, 'reports'])->name('reports');


Route::middleware('auth')->group(function(){
    Route::get('/user',[PageController::class, 'user'])->name('user');
    Route::get('fund-request',[PageController::class, 'fundrequest'])->name('fund-request');
    Route::get('/admin',[PageController::class, 'admin'])->name('admin');
    Route::get('/dashboarduser',[PageController::class, 'dashboardUser'])->name('dashboarduser');
    Route::get('/settings', [PageController::class, 'settings'])->name('settings');

});