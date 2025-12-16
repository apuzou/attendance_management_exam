<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * メール認証コードリクエスト
 * メール認証コードの検証処理のバリデーションを行う
 */
class VerificationCodeRequest extends FormRequest
{
    /**
     * リクエストの認可を判定（認証済みユーザーのみ）
     */
    public function authorize()
    {
        return true;
    }

    /**
     * バリデーションルール
     * verification_code: 認証コード（必須、6桁の文字列）
     */
    public function rules()
    {
        return [
            'verification_code' => ['required', 'string', 'size:6'],
        ];
    }

    /**
     * バリデーションエラーメッセージ
     */
    public function messages()
    {
        return [
            'verification_code.required' => '認証コードを入力してください',
            'verification_code.string' => '認証コードは文字列で入力してください',
            'verification_code.size' => '認証コードは6桁で入力してください',
        ];
    }

    /**
     * カスタムバリデーション
     * 認証コードの一致確認と有効期限チェックを実行
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var User $user */
            $user = Auth::user();

            // 認証コードが保存されていない場合はエラー
            if (!$user->verification_code) {
                $validator->errors()->add('verification_code', '認証コードが見つかりません。再送信してください。');
                return;
            }

            // 認証コードが一致しない場合はエラー
            if ($user->verification_code !== $this->verification_code) {
                $validator->errors()->add('verification_code', '認証コードが一致しません。');
                return;
            }

            // 認証コードの有効期限が切れている場合はエラー
            if ($user->verification_code_expires_at && Carbon::now()->gt($user->verification_code_expires_at)) {
                $validator->errors()->add('verification_code', '認証コードの有効期限が切れています。再送信してください。');
            }
        });
    }
}

