<?php

namespace App\Http\Requests;

use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * 修正申請承認リクエスト
 * 修正申請の承認処理のバリデーションと認可を行う
 */
class ApprovalRequest extends FormRequest
{
    /**
     * リクエストの認可を判定
     * 以下の条件をすべて満たす必要がある:
     * 1. 管理者であること
     * 2. 管轄する部門の申請であること（canViewAttendance）
     * 3. 自身の申請ではないこと
     */
    public function authorize()
    {
        /** @var User|null $user */
        $user = Auth::user();
        
        // 管理者でない場合は認可不可
        if (!$user || $user->role !== 'admin') {
            return false;
        }
        
        // 修正申請レコードを取得
        $correctionRequest = StampCorrectionRequest::find($this->route('id'));
        
        if (!$correctionRequest) {
            return false;
        }
        
        // 管理者が管轄する部門の申請かどうかをチェック
        if (!$user->canViewAttendance($correctionRequest->user_id)) {
            return false;
        }
        
        // 自身の申請を承認することはできない
        if ($correctionRequest->user_id === $user->id) {
            return false;
        }
        
        return true;
    }

    /**
     * バリデーションルール
     * 承認処理ではリクエストボディからデータを取得しないため、ルールは空
     */
    public function rules()
    {
        return [];
    }

    /**
     * バリデーションエラーメッセージ
     */
    public function messages()
    {
        return [];
    }

    /**
     * カスタムバリデーションロジック
     * 既に承認済みの場合はバリデーションエラーを追加
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $correctionRequest = StampCorrectionRequest::find($this->route('id'));

            if ($correctionRequest && !is_null($correctionRequest->approved_at)) {
                $validator->errors()->add('request', 'この申請は既に承認済みです');
            }
        });
    }
}

