<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class AdminLoginController extends Controller
{
    /**
     * 管理者ログイン画面を表示
     * 実際のログイン処理はFortifyの標準ルート（/login）で処理される
     * フォームにはis_admin_loginパラメータが含まれており、FortifyServiceProviderで判別される
     */
    public function showLoginForm()
    {
        return view('auth.adminLogin');
    }
}

