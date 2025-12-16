<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| アプリケーションのWebルート定義
| 認証状態と権限に応じて適切なルートグループに分類
|
*/

// トップページ（ログイン状態に応じてリダイレクト）
// ログイン済み: 勤怠打刻画面へ、未ログイン: ログイン画面へ
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('attendance.index');
    }
    return redirect('/login');
})->name('home');

// 認証関連（ゲストのみアクセス可能）
Route::middleware(['guest'])->group(function () {
    // Fortifyの標準ルートはFortifyServiceProviderのboot()メソッドで自動的に登録される
    // 管理者ログインも同じ/loginルートを使用（is_admin_loginパラメータで判別）

    // 管理者向け認証（ログイン画面のみカスタムルート）
    // GET /admin/login: 管理者ログイン画面表示（フォームは/loginにPOST）
    Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
});

// 認証済みユーザー向け（ログイン必須）
Route::middleware(['auth'])->group(function () {
    // ログアウト処理はFortifyの標準ルートを使用（LogoutResponseでカスタマイズ）

    // メール認証関連（一般ユーザーのメール認証用）
    Route::prefix('email')->name('verification.')->group(function () {
        // GET /email/verify: メール認証通知画面表示
        Route::get('/verify', [VerificationController::class, 'show'])->name('notice');
        // GET /email/verify/code: 認証コード入力画面表示
        Route::get('/verify/code', [VerificationController::class, 'showCodeInput'])->name('code');
        // POST /email/verify: 認証コード確認処理
        Route::post('/verify', [VerificationController::class, 'verify'])->name('verify');
        // POST /email/verification-notification: 認証メール再送信処理
        Route::post('/verification-notification', [VerificationController::class, 'resend'])->name('resend');
    });

    // 一般ユーザー向け勤怠管理（メール認証済みのみアクセス可能）
    Route::middleware(['verified.email'])->group(function () {
        // 勤怠打刻・勤怠一覧関連
        Route::prefix('attendance')->name('attendance.')->group(function () {
            // GET /attendance: 勤怠打刻画面表示
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            // POST /attendance: 打刻処理実行（出勤/退勤/休憩開始/休憩終了）
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            // GET /attendance/list: 月次勤怠一覧画面表示
            Route::get('/list', [AttendanceController::class, 'list'])->name('list');
            // GET /attendance/detail/{id}: 勤怠詳細画面表示
            Route::get('/detail/{id}', [AttendanceController::class, 'show'])->name('show');
            // POST /attendance/detail/{id}: 修正申請提出
            Route::post('/detail/{id}', [AttendanceController::class, 'update'])->name('update');
        });

        // 修正申請関連（一般ユーザーと管理者の両方が使用）
        // GET /stamp_correction_request/list: 申請一覧画面表示（管理者の場合は管轄部門の申請を表示）
        Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
            ->name('correction.index');
        // GET /stamp_correction_request/approve/{id}: 申請承認画面表示（管理者のみ）
        Route::get('/stamp_correction_request/approve/{id}', [StampCorrectionRequestController::class, 'show'])
            ->name('correction.approve');
        // POST /stamp_correction_request/approve/{id}: 申請承認処理（管理者のみ、自身の申請は承認不可）
        Route::post('/stamp_correction_request/approve/{id}', [StampCorrectionRequestController::class, 'update'])
            ->name('correction.approve');
    });

    // 管理者向け機能（管理者権限必須、AdminControllerのコンストラクタでチェック）
    Route::prefix('admin')->name('admin.')->group(function () {
        // GET /admin/attendance/list: 管理者の勤怠一覧画面表示（指定日の出勤者一覧）
        Route::get('/attendance/list', [AdminController::class, 'index'])->name('index');
        // GET /admin/attendance/{id}: 管理者の勤怠詳細画面表示
        Route::get('/attendance/{id}', [AdminController::class, 'show'])->name('show');
        // POST /admin/attendance/{id}: 管理者による勤怠情報の直接修正（フルアクセス権限の管理者は自身も直接修正可能）
        Route::post('/attendance/{id}', [AdminController::class, 'update'])->name('update');
        // GET /admin/staff/list: スタッフ一覧画面表示（権限に応じて管轄スタッフを表示）
        Route::get('/staff/list', [AdminController::class, 'staff'])->name('staff');
        // GET /admin/attendance/staff/{id}: スタッフ別月次勤怠一覧画面表示（CSV出力も可能）
        Route::get('/attendance/staff/{id}', [AdminController::class, 'list'])->name('list');
    });
});
