<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * 打刻修正申請モデル
 * 出勤・退勤時刻の修正申請を管理
 * original_*: 修正前の値（証跡として保存）
 * corrected_*: 修正後の値
 */
class StampCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     * attendance_id, user_id, request_date, original_clock_in, original_clock_out, approved_by, approved_atは
     * セキュリティ上の理由で一括代入不可（直接代入で設定）
     */
    protected $fillable = [
        'corrected_clock_in',
        'corrected_clock_out',
        'note',
    ];

    /**
     * 型変換を行う属性
     */
    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * 勤怠とのリレーション（多対1）
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 申請者（ユーザー）とのリレーション（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 承認者（ユーザー）とのリレーション（多対1）
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 休憩時間修正申請とのリレーション（1対多）
     */
    public function breakCorrectionRequests(): HasMany
    {
        return $this->hasMany(BreakCorrectionRequest::class);
    }

    /**
     * 承認済みかどうかを判定
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * 承認待ちかどうかを判定
     */
    public function isPending(): bool
    {
        return $this->approved_at === null;
    }

    /**
     * ユーザーに応じたクエリスコープ
     * 管理者の場合は管轄する部門の申請を表示
     * 一般ユーザーの場合は自分の申請のみ表示
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->role === 'admin') {
            // 全アクセス権限（department_code=1）の場合は全ユーザーの申請を表示
            // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーの申請を表示
            if ($user->hasDepartmentAccess()) {
                $sameDepartmentUserIds = User::where('department_code', $user->department_code)
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('user_id', $sameDepartmentUserIds);
            }
        } else {
            // 一般ユーザーの場合は自分の申請のみ表示
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}

