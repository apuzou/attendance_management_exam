<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * プロフィール情報更新アクション
 *
 * Fortifyのプロフィール更新処理で使用される。
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * ユーザーのプロフィール情報を更新する。
     *
     * @param \App\Models\User $user 対象ユーザー
     * @param array $input 入力データ（name, email）
     * @return void
     */
    public function update($user, array $input)
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ])->validateWithBag('updateProfile');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * メールアドレス変更時、認証済みユーザーの再認証を促す。
     *
     * @param \App\Models\User $user 対象ユーザー
     * @param array $input 入力データ
     * @return void
     */
    protected function updateVerifiedUser($user, array $input)
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}

