<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 休憩時間モデル
 *
 * 勤怠レコードに紐づく休憩時間を管理する。
 */
class BreakTime extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'attendance_id',
        'break_start',
        'break_end',
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
}

