<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ユーザーモデル
 * 一般ユーザーと管理者の両方に対応
 * role: 'general'（一般ユーザー）または 'admin'（管理者）
 * department_code: 部門コード（1=フルアクセス権限、2以降=部門コード）
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_code',
        'email_verified_at',
        'verification_code',
        'verification_code_expires_at',
    ];

    /**
     * シリアライズ時に非表示にする属性
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    /**
     * 型変換を行う属性
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
    ];

    /**
     * 管理者かどうかを判定
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * 一般ユーザーかどうかを判定
     */
    public function isGeneral()
    {
        return $this->role === 'general';
    }

    /**
     * 全ユーザーの勤怠を参照できる権限を持つか判定（role='admin' && department_code=1）
     */
    public function hasFullAccess(): bool
    {
        return $this->role === 'admin' && $this->department_code === 1;
    }

    /**
     * 部門単位で勤怠を参照できる権限を持つか判定（role='admin' && department_code!=1）
     */
    public function hasDepartmentAccess(): bool
    {
        return $this->role === 'admin' && $this->department_code !== 1 && $this->department_code !== null;
    }

    /**
     * 指定されたユーザーの勤怠を参照できるかを判定
     */
    public function canViewAttendance(int $targetUserId): bool
    {
        // 自分の勤怠は常に参照可能
        if ($this->id === $targetUserId) {
            return true;
        }

        $targetUser = self::find($targetUserId);
        if (!$targetUser) {
            return false;
        }

        // 全アクセス権限（admin + department_code=1）を持つ場合は全員の勤怠を見られる
        if ($this->hasFullAccess()) {
            return true;
        }

        // 部門アクセス権限（admin + department_code!=1）を持つ場合は同じ部門のメンバーの勤怠を見られる
        if ($this->hasDepartmentAccess() && $this->department_code === $targetUser->department_code) {
            return true;
        }

        // 一般ユーザー（general）は自分の勤怠のみ
        return false;
    }

    /**
     * 自身の承認ができるかを判定
     * 部門アクセス権限を持つ管理者は自身の承認ができない
     */
    public function canApproveOwnRequest(): bool
    {
        // 部門アクセス権限（admin + department_code!=1）を持つ場合は自身の承認ができない
        if ($this->hasDepartmentAccess()) {
            return false;
        }

        // 全アクセス権限（admin + department_code=1）を持つ場合は自身の承認ができる
        return $this->hasFullAccess();
    }
}
