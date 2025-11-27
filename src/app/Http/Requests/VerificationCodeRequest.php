<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}

