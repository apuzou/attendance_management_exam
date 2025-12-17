<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 打刻リクエスト
 * 出勤・退勤・休憩開始・休憩終了の打刻処理のバリデーションを行う
 */
class StampRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'stamp_type' => ['required', 'string', 'in:clock_in,clock_out,break_start,break_end'],
        ];
    }

    public function messages()
    {
        return [
            'stamp_type.required' => '打刻タイプを入力してください',
            'stamp_type.string' => '打刻タイプは文字列で入力してください',
            'stamp_type.in' => '無効な打刻タイプです',
        ];
    }
}

