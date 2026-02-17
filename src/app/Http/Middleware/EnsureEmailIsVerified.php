<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * メール認証ミドルウェア
 *
 * 一般ユーザーがメール認証を完了していない場合、認証画面にリダイレクトする。
 * ログアウトとメール認証関連のルートは除外する。
 */
class EnsureEmailIsVerified
{
    /**
     * リクエストを処理する。
     *
     * 一般ユーザーでメール認証が完了していない場合、認証画面にリダイレクトする。
     *
     * @param Request $request リクエスト
     * @param \Closure $next 次のミドルウェア
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // 一般ユーザーでメール認証が完了していない場合
            if ($user->role === 'general' && $user->email_verified_at === null) {
                // ログアウトとメール認証関連のルート以外は、メール認証画面にリダイレクト
                $isLogoutRoute = $request->routeIs('logout');
                $isVerificationRoute = $request->routeIs('verification.*');
                if ($isLogoutRoute === false && $isVerificationRoute === false) {
                    return redirect()->route('verification.notice');
                }
            }
        }

        return $next($request);
    }
}

