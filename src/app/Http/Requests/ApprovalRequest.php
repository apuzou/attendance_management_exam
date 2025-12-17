<?php

namespace App\Http\Requests;

use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * 修正申請承認リクエスト
 * 修正申請の承認処理の認可を行う
 */
class ApprovalRequest extends FormRequest
{
    /**
     * リクエストの認可を判定
     * 以下の条件をすべて満たす必要がある:
     * 1. 管理者であること
     * 2. 管轄する部門の申請であること（canViewAttendance）
     * 3. 自身の申請ではないこと
     * 4. 既に承認済みでないこと
     */
    public function authorize()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user || $user->role !== 'admin') {
            return false;
        }

        $correctionRequest = StampCorrectionRequest::find($this->route('id'));

        if (!$correctionRequest) {
            return false;
        }

        // 既に承認済みの場合は認可を拒否
        if (!is_null($correctionRequest->approved_at)) {
            return false;
        }

        if (!$user->canViewAttendance($correctionRequest->user_id)) {
            return false;
        }

        if ($correctionRequest->user_id === $user->id) {
            return false;
        }

        return true;
    }

    public function rules()
    {
        return [];
    }

    public function messages()
    {
        return [];
    }
}

