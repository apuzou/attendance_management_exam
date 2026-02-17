<?php

namespace App\Http\Requests;

use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 修正申請承認リクエスト
 *
 * 修正申請の承認処理の認可を行う。
 */
class ApprovalRequest extends FormRequest
{
    /**
     * リクエストの認可を判定する。
     *
     * StampCorrectionRequestPolicy::approve に委譲する。
     *
     * @return bool 認可される場合true
     */
    public function authorize()
    {
        $correctionRequest = StampCorrectionRequest::find($this->route('attendance_correct_request_id'));

        if ($correctionRequest === null) {
            return false;
        }

        return $this->user()->can('approve', $correctionRequest);
    }

    /**
     * バリデーションルールを取得する。
     *
     * @return array<int, mixed>
     */
    public function rules()
    {
        return [];
    }

    /**
     * バリデーションエラーメッセージを取得する。
     *
     * @return array<int, mixed>
     */
    public function messages()
    {
        return [];
    }
}

