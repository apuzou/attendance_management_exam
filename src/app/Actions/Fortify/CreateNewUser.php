<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Mail\VerificationCodeMail;

/**
 * 新規ユーザー作成アクション
 *
 * Fortifyのユーザー登録処理で使用される。一般ユーザーとして作成し、
 * メール認証コードを送信する。
 */
class CreateNewUser implements CreatesNewUsers
{
    /**
     * 新規ユーザーを作成する。
     *
     * バリデーションはRegisterRequestフォームリクエストのルールとメッセージを使用する。
     *
     * @param array $input 入力データ（name, email, password）
     * @return User 作成されたユーザー
     */
    public function create(array $input): User
    {
        // RegisterRequestのバリデーションルールとメッセージを使用
        $registerRequest = new RegisterRequest();
        Validator::make($input, $registerRequest->rules(), $registerRequest->messages())->validate();

        // 認証コードを生成
        $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // ユーザーを作成（常に一般ユーザーとして作成）
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'role' => 'general',
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addMinutes(30),
        ]);

        // 認証コードメールを送信
        Mail::to($user->email)->send(new VerificationCodeMail($user, $verificationCode));

        return $user;
    }
}

