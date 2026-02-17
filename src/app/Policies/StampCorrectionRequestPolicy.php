<?php

namespace App\Policies;

use App\Models\StampCorrectionRequest;
use App\Models\User;

/**
 * 打刻修正申請ポリシー
 *
 * 修正申請の閲覧・承認に関する認可を行う。
 */
class StampCorrectionRequestPolicy
{
    /**
     * 修正申請の詳細（承認画面）を閲覧できるか判定する。
     *
     * 管理者かつ管轄範囲の申請のみ閲覧可能。一般ユーザーは承認画面にアクセスできない。
     *
     * @param User $user 認証ユーザー
     * @param StampCorrectionRequest $stampCorrectionRequest 打刻修正申請
     * @return bool 閲覧可能な場合true
     */
    public function view(User $user, StampCorrectionRequest $stampCorrectionRequest): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        return $user->canViewAttendance($stampCorrectionRequest->user_id);
    }

    /**
     * 修正申請を承認できるか判定する。
     *
     * 管理者かつ管轄範囲かつ自身の申請でなく未承認の場合にtrue。
     *
     * @param User $user 認証ユーザー
     * @param StampCorrectionRequest $stampCorrectionRequest 打刻修正申請
     * @return bool 承認可能な場合true
     */
    public function approve(User $user, StampCorrectionRequest $stampCorrectionRequest): bool
    {
        if (!$user->isAdmin()) {
            return false;
        }

        if ($stampCorrectionRequest->approved_at !== null) {
            return false;
        }

        if (!$user->canViewAttendance($stampCorrectionRequest->user_id)) {
            return false;
        }

        if ($stampCorrectionRequest->user_id === $user->id) {
            return false;
        }

        return true;
    }
}
