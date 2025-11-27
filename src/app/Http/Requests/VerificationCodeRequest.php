<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class VerificationCodeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'verification_code' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages()
    {
        return [
            'verification_code.required' => '認証コードを入力してください',
            'verification_code.string' => '認証コードは文字列で入力してください',
            'verification_code.size' => '認証コードは6桁で入力してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var User $user */
            $user = Auth::user();

            if (!$user->verification_code) {
                $validator->errors()->add('verification_code', '認証コードが見つかりません。再送信してください。');
                return;
            }

            if ($user->verification_code !== $this->verification_code) {
                $validator->errors()->add('verification_code', '認証コードが一致しません。');
                return;
            }

            if ($user->verification_code_expires_at && Carbon::now()->gt($user->verification_code_expires_at)) {
                $validator->errors()->add('verification_code', '認証コードの有効期限が切れています。再送信してください。');
            }
        });
    }
}

