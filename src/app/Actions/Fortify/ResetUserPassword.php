<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * パスワードリセットアクション
 *
 * Fortifyのパスワードリセット処理で使用される。
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * ユーザーのパスワードをリセットする。
     *
     * @param \App\Models\User $user 対象ユーザー
     * @param array $input 入力データ（password, password_confirmation）
     * @return void
     */
    public function reset($user, array $input)
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}

