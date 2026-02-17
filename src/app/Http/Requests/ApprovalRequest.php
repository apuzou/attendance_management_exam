<?php

namespace App\Http\Requests;

use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
     * 以下の条件をすべて満たす必要がある:
     * - 管理者であること
     * - 管轄する部門の申請であること（canViewAttendance）
     * - 自身の申請ではないこと
     * - 既に承認済みでないこと
     *
     * @return bool 認可される場合true
     */
    public function authorize()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null || $user->role !== 'admin') {
            return false;
        }

        $correctionRequest = StampCorrectionRequest::find($this->route('attendance_correct_request_id'));

        if ($correctionRequest === null) {
            return false;
        }

        // 既に承認済みの場合は認可を拒否
        if ($correctionRequest->approved_at !== null) {
            return false;
        }

        if ($user->canViewAttendance($correctionRequest->user_id) === false) {
            return false;
        }

        if ($correctionRequest->user_id === $user->id) {
            return false;
        }

        return true;
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

