<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * 勤怠ポリシー
 *
 * 勤怠の閲覧・直接修正・修正申請に関する認可を行う。
 */
class AttendancePolicy
{
    /**
     * 勤怠を閲覧できるか判定する。
     *
     * @param User $user 認証ユーザー
     * @param Attendance $attendance 勤怠レコード
     * @return bool 閲覧可能な場合true
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $user->canViewAttendance($attendance->user_id);
    }

    /**
     * 勤怠を直接修正できるか判定する。
     *
     * フルアクセス管理者は自身の勤怠も直接修正可能。
     * 部門アクセス管理者が自身の勤怠を修正する場合は申請として扱うためfalse。
     *
     * @param User $user 認証ユーザー
     * @param Attendance $attendance 勤怠レコード
     * @return bool 直接修正可能な場合true
     */
    public function update(User $user, Attendance $attendance): bool
    {
        if (!$user->canViewAttendance($attendance->user_id)) {
            return false;
        }

        // 部門アクセス管理者が自身の勤怠を修正する場合は申請扱いのため直接修正不可
        if ($user->role === 'admin' && $attendance->user_id === $user->id && !$user->hasFullAccess()) {
            return false;
        }

        return true;
    }

    /**
     * 修正申請を提出できるか判定する。
     *
     * 自分の勤怠の場合は常に可能。管理者は管轄範囲の勤怠に対して可能。
     *
     * @param User $user 認証ユーザー
     * @param Attendance $attendance 勤怠レコード
     * @return bool 修正申請可能な場合true
     */
    public function requestCorrection(User $user, Attendance $attendance): bool
    {
        if ($attendance->user_id === $user->id) {
            return true;
        }

        return $user->isAdmin() && $user->canViewAttendance($attendance->user_id);
    }
}
