<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('attendance.index');
    }
    return redirect('/login');
})->name('home');

Route::middleware(['guest'])->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
});

Route::middleware(['auth'])->group(function () {
    Route::prefix('email')->name('verification.')->group(function () {
        Route::get('/verify', [VerificationController::class, 'show'])->name('notice');
        Route::get('/verify/code', [VerificationController::class, 'showCodeInput'])->name('code');
        Route::post('/verify', [VerificationController::class, 'verify'])->name('verify');
        Route::post('/verification-notification', [VerificationController::class, 'resend'])->name('resend');
    });

    Route::middleware(['verified.email'])->group(function () {
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            Route::get('/list', [AttendanceController::class, 'list'])->name('list');
            Route::get('/detail/{id}', [AttendanceController::class, 'show'])->name('show');
            Route::post('/detail/{id}', [AttendanceController::class, 'update'])->name('update');
        });

        Route::prefix('stamp_correction_request')->name('correction.')->group(function () {
            Route::get('/list', [StampCorrectionRequestController::class, 'index'])->name('index');
            Route::get('/approve/{id}', [StampCorrectionRequestController::class, 'show'])->name('approve');
            Route::post('/approve/{id}', [StampCorrectionRequestController::class, 'update'])->name('approve');
        });
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/attendance/list', [AdminController::class, 'index'])->name('index');
        Route::get('/attendance/{id}', [AdminController::class, 'show'])->name('show');
        Route::post('/attendance/{id}', [AdminController::class, 'update'])->name('update');
        Route::get('/staff/list', [AdminController::class, 'staff'])->name('staff');
        Route::get('/attendance/staff/{id}', [AdminController::class, 'list'])->name('list');
    });
});
