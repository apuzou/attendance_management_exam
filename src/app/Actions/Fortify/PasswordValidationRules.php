<?php

namespace App\Actions\Fortify;

/**
 * パスワードバリデーションルール
 *
 * Fortifyのパスワード関連処理で共通して使用するバリデーションルールを提供する。
 */
trait PasswordValidationRules
{
    /**
     * パスワードのバリデーションルールを取得する。
     *
     * @return array<int, string>
     */
    protected function passwordRules()
    {
        return ['required', 'string', 'min:8', 'confirmed'];
    }
}

