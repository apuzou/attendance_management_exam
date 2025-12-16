<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ユーザー登録リクエスト
 * 新規ユーザー登録処理のバリデーションを行う
 */
class RegisterRequest extends FormRequest
{
    /**
     * リクエストの認可を判定（ゲストも登録可能）
     */
    public function authorize()
    {
        return true;
    }

    /**
     * バリデーションルール
     * name: 氏名（必須、最大255文字）
     * email: メールアドレス（必須、メール形式、最大255文字、usersテーブルでユニーク）
     * password: パスワード（必須、8文字以上、確認用パスワードと一致）
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     */
    public function messages()
    {
        return [
            'name.required' => 'お名前を入力してください',
            'name.string' => 'お名前は文字列で入力してください',
            'name.max' => 'お名前は255文字以内で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.string' => 'メールアドレスは文字列で入力してください',
            'email.email' => '有効なメールアドレスを入力してください',
            'email.max' => 'メールアドレスは255文字以内で入力してください',
            'email.unique' => 'このメールアドレスは既に使用されています',
            'password.required' => 'パスワードを入力してください',
            'password.string' => 'パスワードは文字列で入力してください',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'password.confirmed' => 'パスワードと一致しません',
        ];
    }
}

