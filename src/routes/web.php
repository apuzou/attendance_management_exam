<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

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

// ログインルート（Fortifyのルートを上書き）
Route::middleware(['guest'])->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');
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
