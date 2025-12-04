<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminLoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.adminLogin');
    }

    public function login(AdminLoginRequest $request)
    {
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            // 管理者ユーザーのみログイン可能
            if ($user->role !== 'admin') {
                return back()->withErrors([
                    'email' => 'ログイン情報が登録されていません',
                ])->withInput($request->only('email'));
            }

            Auth::login($user, $request->filled('remember'));
            
            // 管理者ログイン画面からのログインであることをセッションに記録
            session(['is_admin_login' => true]);
            
            return redirect()->route('admin.index');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->withInput($request->only('email'));
    }
}

