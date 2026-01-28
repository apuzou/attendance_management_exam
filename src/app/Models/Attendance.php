<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * 勤怠モデル
 * ユーザーの出勤・退勤情報と休憩時間を管理
 */
class Attendance extends Model
{
    use HasFactory;

    /**
     * 一括代入可能な属性
     * user_id, last_modified_by, last_modified_atはセキュリティ上の理由で一括代入不可（直接代入で設定）
     */
    protected $fillable = [
        'date',
        'clock_in',
        'clock_out',
        'note',
    ];

    /**
     * 型変換を行う属性
     */
    protected $casts = [
        'date' => 'date',
        'last_modified_at' => 'datetime',
    ];

    /**
     * ユーザーとのリレーション（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 休憩時間とのリレーション（1対多）
     */
    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * 最終更新者とのリレーション（多対1）
     */
    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    /**
     * 修正申請とのリレーション（1対多）
     */
    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    /**
     * 現在の勤怠状態(ステータス)の判定
     * 勤務外/出勤中/休憩中/退勤済のいずれかを返す
     */
    public function getStatus(): string
    {
        // 出勤時刻が未設定の場合は勤務外
        if ($this->clock_in === null) {
            return '勤務外';
        }

        // 退勤時刻が設定されている場合は退勤済
        if ($this->clock_out) {
            return '退勤済';
        }

        // アクティブな休憩（終了時刻が未設定）を検索
        // リレーションを活用（eager loading済みの場合も考慮）
        $activeBreak = $this->breakTimes
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();

        // アクティブな休憩が存在する場合は休憩中
        if ($activeBreak) {
            return '休憩中';
        }

        // 上記以外（出勤済みで退勤していない場合）は出勤中
        return '出勤中';
    }

    /**
     * 静的メソッド：勤怠レコードが存在しない場合の状態を取得
     */
    public static function getStatusForNull(): string
    {
        return '勤務外';
    }

    /**
     * アクティブな休憩（終了時刻が未設定）を取得
     */
    public function getActiveBreak(): ?BreakTime
    {
        return $this->breakTimes
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();
    }

    /**
     * IDで休憩時間を検索
     */
    public function findBreakById(int $breakId): ?BreakTime
    {
        return $this->breakTimes->where('id', $breakId)->first();
    }

    /**
     * 合計休憩時間を分単位で取得
     * 全ての休憩時間の合計を計算
     */
    public function getTotalBreakMinutes(): int
    {
        $totalMinutes = 0;

        // 各休憩時間の開始時刻と終了時刻の差を合計
        foreach ($this->breakTimes as $breakTime) {
            if ($breakTime->break_start && $breakTime->break_end) {
                $start = Carbon::parse($breakTime->break_start);
                $end = Carbon::parse($breakTime->break_end);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        return $totalMinutes;
    }

    /**
     * 合計休憩時間を文字列（H:MM形式）で取得
     */
    public function getTotalBreakTime(): string
    {
        $totalMinutes = $this->getTotalBreakMinutes();
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * 実働時間を分単位で取得
     * 出勤時刻と退勤時刻の差から休憩時間を引いた値
     */
    public function getWorkMinutes(): int
    {
        // 出勤時刻または退勤時刻が未設定の場合は0を返す
        if ($this->clock_in === null || $this->clock_out === null) {
            return 0;
        }

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        // 出勤時刻と退勤時刻の差を分単位で計算
        $totalMinutes = $clockIn->diffInMinutes($clockOut);
        // 休憩時間を引く
        $breakMinutes = $this->getTotalBreakMinutes();

        // 実働時間が0未満にならないようにmax(0, ...)で保護
        return max(0, $totalMinutes - $breakMinutes);
    }

    /**
     * 実働時間を文字列（H:MM形式）で取得
     */
    public function getWorkTime(): string
    {
        $workMinutes = $this->getWorkMinutes();
        $hours = floor($workMinutes / 60);
        $minutes = $workMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }
}
