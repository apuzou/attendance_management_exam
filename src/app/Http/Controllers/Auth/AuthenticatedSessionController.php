<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * 認証セッションコントローラー
 * Fortifyの標準コントローラーを継承し、FormRequestを使用したバリデーションを追加
 */
class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    /**
     * ログイン処理
     * LoginRequestを使用してバリデーションを実行後、Fortifyの標準処理を実行
     */
    public function store(Request $request)
    {
        // LoginRequestのルールとメッセージを使用してバリデーションを実行
        $loginRequest = new LoginRequest();
        $validator = Validator::make(
            $request->all(),
            $loginRequest->rules(),
            $loginRequest->messages()
        );

        if ($validator->fails()) {
            // 管理者ログイン画面からのリクエストの場合は管理者ログイン画面にリダイレクト
            $redirectRoute = $request->has('is_admin_login') && $request->is_admin_login == '1'
                ? route('admin.login')
                : route('login');

            throw (new ValidationException($validator))
                ->errorBag('default')
                ->redirectTo($redirectRoute);
        }

        // FortifyLoginRequestのインスタンスを作成して親クラスのstoreメソッドに渡す
        $fortifyLoginRequest = FortifyLoginRequest::createFrom($request);
        $fortifyLoginRequest->setContainer(app());
        $fortifyLoginRequest->setRedirector(app('redirect'));

        // バリデーション成功後、Fortifyの標準ログイン処理を実行
        return parent::store($fortifyLoginRequest);
    }
}

