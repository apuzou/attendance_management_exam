<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// トップページ（ログイン状態に応じてリダイレクト）
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('attendance.index');
    }
    return redirect('/login');
})->name('home');

// 認証関連（ゲストのみ）
Route::middleware(['guest'])->group(function () {
    // 一般ユーザー向け認証
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');

    // 管理者向け認証
    Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'login']);
});

// 認証済みユーザー向け
Route::middleware(['auth'])->group(function () {
    // ログアウト
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // メール認証
    Route::prefix('email')->name('verification.')->group(function () {
        Route::get('/verify', [VerificationController::class, 'show'])->name('notice');
        Route::get('/verify/code', [VerificationController::class, 'showCodeInput'])->name('code');
        Route::post('/verify', [VerificationController::class, 'verify'])->name('verify');
        Route::post('/verification-notification', [VerificationController::class, 'resend'])->name('resend');
    });

    // 一般ユーザー向け勤怠管理（メール認証済みのみ）
    Route::middleware(['verified.email'])->group(function () {
        // 勤怠打刻・勤怠一覧
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            Route::get('/list', [AttendanceController::class, 'list'])->name('list');
            Route::get('/detail/{id}', [AttendanceController::class, 'show'])->name('show');
            Route::post('/detail/{id}', [AttendanceController::class, 'update'])->name('update');
        });

        // 修正申請
        Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
            ->name('correction.index');
    });

    // 管理者向け機能
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/attendance/list', [AdminController::class, 'index'])->name('index');
    });
});
