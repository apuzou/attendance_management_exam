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
 */
class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    /**
     * ログイン処理
     * LoginRequestを使用してバリデーションを実行後、Fortifyの標準処理を実行
     */
    public function store(Request $request)
    {
        $loginRequest = new LoginRequest();
        $validator = Validator::make(
            $request->all(),
            $loginRequest->rules(),
            $loginRequest->messages()
        );

        if ($validator->fails()) {
            $redirectRoute = $request->has('is_admin_login') && $request->is_admin_login == '1'
                ? route('admin.login')
                : route('login');

            throw (new ValidationException($validator))
                ->errorBag('default')
                ->redirectTo($redirectRoute);
        }

        $fortifyLoginRequest = FortifyLoginRequest::createFrom($request);
        $fortifyLoginRequest->setContainer(app());
        $fortifyLoginRequest->setRedirector(app('redirect'));

        return parent::store($fortifyLoginRequest);
    }
}

