<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 休憩時間修正申請モデル
 *
 * 打刻修正申請に紐づく休憩時間の修正内容を管理する。
 * - break_time_idがnull: 新規追加の休憩時間
 * - break_time_idが設定されている: 既存の休憩時間の修正
 * - original_*: 修正前の値（証跡として保存）
 * - corrected_*: 修正後の値
 */
class BreakCorrectionRequest extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'stamp_correction_request_id',
        'break_time_id',
        'original_break_start',
        'original_break_end',
        'corrected_break_start',
        'corrected_break_end',
    ];

    /**
     * 打刻修正申請とのリレーション（多対1）を取得する。
     *
     * @return BelongsTo
     */
    public function stampCorrectionRequest(): BelongsTo
    {
        return $this->belongsTo(StampCorrectionRequest::class);
    }

    /**
     * 休憩時間とのリレーション（多対1、null許容）を取得する。
     *
     * @return BelongsTo
     */
    public function breakTime(): BelongsTo
    {
        return $this->belongsTo(BreakTime::class);
    }

    /**
     * 既存の休憩時間の修正かどうかを判定する。
     *
     * @return bool 既存休憩の修正の場合true
     */
    public function isModification(): bool
    {
        return $this->break_time_id !== null;
    }

    /**
     * 新規追加の休憩時間かどうかを判定する。
     *
     * @return bool 新規追加の場合true
     */
    public function isAddition(): bool
    {
        return $this->break_time_id === null;
    }
}

