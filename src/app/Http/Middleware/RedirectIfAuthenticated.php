<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 認証済みリダイレクトミドルウェア
 *
 * 既に認証済みのユーザーがログイン/登録画面にアクセスした場合、
 * 役割に応じた適切な画面へリダイレクトする。
 */
class RedirectIfAuthenticated
{
    /**
     * リクエストを処理する。
     *
     * 認証済みの場合、役割（一般ユーザー/管理者）に応じた画面へリダイレクトする。
     *
     * @param Request $request リクエスト
     * @param \Closure $next 次のミドルウェア
     * @param string|null ...$guards ガード名
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                
                if ($user->role === 'general' && $user->email_verified_at === null) {
                    return redirect()->route('verification.notice');
                }
                
                if ($user->role === 'admin') {
                    return redirect()->route('admin.index');
                }
                
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
