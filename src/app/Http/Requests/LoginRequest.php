<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 一般ユーザーログインリクエスト
 * ログイン処理のバリデーションを行う
 */
class LoginRequest extends FormRequest
{
    /**
     * リクエストの認可を判定（ゲストもログイン可能）
     */
    public function authorize()
    {
        return true;
    }

    /**
     * バリデーションルール
     * email: メールアドレス（必須、メール形式、最大255文字）
     * password: パスワード（必須、文字列）
     */
    public function rules()
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     */
    public function messages()
    {
        return [
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスは文字列で入力してください',
            'email.email' => '有効なメールアドレスを入力してください',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードは文字列で入力してください',
        ];
    }
}

