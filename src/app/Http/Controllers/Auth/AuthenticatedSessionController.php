<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            Auth::login($user, $request->filled('remember'));
            
            // 通常ログイン画面からのログインなので、管理者ログインフラグをクリア
            session()->forget('is_admin_login');
            
            // ログイン後、最新のユーザー情報を取得
            $user->refresh();

            if ($user->role === 'general' && !$user->email_verified_at) {
                return redirect()->route('verification.notice');
            }
            
            // 管理者も一般ユーザーと同じく勤怠画面に遷移
            return redirect()->route('attendance.index');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput($request->only('email'));
    }

    public function destroy(Request $request)
    {
        // ログアウト前にセッションフラグを確認
        $isAdminLogin = session('is_admin_login');
        
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // 管理者ログイン画面からログインしていた場合のみ管理者ログイン画面にリダイレクト
        if ($isAdminLogin) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('login');
    }
}

