<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

/**
 * 認証ミドルウェア
 *
 * 未認証ユーザーが保護されたルートにアクセスした場合、
 * ログイン画面へのリダイレクト先を指定する。
 */
class Authenticate extends Middleware
{
    /**
     * 未認証時にリダイレクトするパスを取得する。
     *
     * @param \Illuminate\Http\Request $request リクエスト
     * @return string|null リダイレクト先URL（JSON期待時はnull）
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson() === false) {
            return route('login');
        }
    }
}
