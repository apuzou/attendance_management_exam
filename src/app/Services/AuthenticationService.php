<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * 認証サービス
 * Fortifyとカスタムコントローラーで共通して使用する認証ロジック
 */
class AuthenticationService
{
    /**
     * メールアドレスとパスワードでユーザーを認証
     * 
     * @param string $email メールアドレス
     * @param string $password パスワード
     * @return User|null 認証に成功した場合はUserオブジェクト、失敗した場合はnull
     */
    public static function authenticate(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }

        return null;
    }
}

