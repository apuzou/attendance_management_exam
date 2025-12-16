<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * メール認証ミドルウェア
 * 一般ユーザーがメール認証を完了していない場合、認証画面にリダイレクト
 * ログアウトとメール認証関連のルートは除外
 */
class EnsureEmailIsVerified
{
    /**
     * リクエストを処理
     * 一般ユーザーでメール認証が完了していない場合、認証画面にリダイレクト
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // 一般ユーザーでメール認証が完了していない場合
            if ($user->role === 'general' && !$user->email_verified_at) {
                // ログアウトとメール認証関連のルート以外は、メール認証画面にリダイレクト
                if (!$request->routeIs('logout') && !$request->routeIs('verification.*')) {
                    return redirect()->route('verification.notice');
                }
            }
        }

        return $next($request);
    }
}

