<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

/**
 * 管理者ログインコントローラ
 *
 * 管理者ログイン画面を表示する。実際のログイン処理はFortifyの標準ルート（/login）で行われる。
 */
class AdminLoginController extends Controller
{
    /**
     * 管理者ログイン画面を表示する。
     *
     * フォームにはis_admin_loginパラメータが含まれており、FortifyServiceProviderで判別される。
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.adminLogin');
    }
}

