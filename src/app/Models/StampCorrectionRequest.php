<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 打刻修正申請モデル
 *
 * 出勤・退勤時刻の修正申請を管理する。
 * - original_*: 修正前の値（証跡として保存）
 * - corrected_*: 修正後の値
 */
class StampCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'attendance_id',
        'user_id',
        'request_date',
        'original_clock_in',
        'original_clock_out',
        'corrected_clock_in',
        'corrected_clock_out',
        'note',
        'approved_by',
        'approved_at',
    ];

    /**
     * 型変換を行う属性
     */
    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * 勤怠とのリレーション（多対1）を取得する。
     *
     * @return BelongsTo
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 申請者（ユーザー）とのリレーション（多対1）を取得する。
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 承認者（ユーザー）とのリレーション（多対1）を取得する。
     *
     * @return BelongsTo
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 休憩時間修正申請とのリレーション（1対多）を取得する。
     *
     * @return HasMany
     */
    public function breakCorrectionRequests(): HasMany
    {
        return $this->hasMany(BreakCorrectionRequest::class);
    }

    /**
     * 承認済みかどうかを判定する。
     *
     * @return bool 承認済みの場合true
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * 承認待ちかどうかを判定する。
     *
     * @return bool 承認待ちの場合true
     */
    public function isPending(): bool
    {
        return $this->approved_at === null;
    }
}

