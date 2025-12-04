<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest'])
    ->name('login');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('logout');

Route::middleware(['guest'])->group(function () {
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register');
});

// メール認証関連
Route::middleware(['auth'])->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'show'])
        ->name('verification.notice');
    
    Route::get('/email/verify/code', [VerificationController::class, 'showCodeInput'])
        ->name('verification.code');
    
    Route::post('/email/verify', [VerificationController::class, 'verify'])
        ->name('verification.verify');
    
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])
        ->name('verification.resend');
});

// 勤怠関連（一般ユーザー）
Route::middleware(['auth', 'verified.email'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/detail/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
    
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
        ->name('correction.index');
});
