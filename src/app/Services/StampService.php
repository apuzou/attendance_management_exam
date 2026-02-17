<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 打刻サービス
 *
 * 出勤・退勤・休憩開始・休憩終了の打刻処理を行う。
 */
class StampService
{
    /**
     * 打刻を記録する。
     *
     * @param User $user 打刻を行うユーザー
     * @param string $stampType 打刻タイプ（clock_in, break_start, break_end, clock_out）
     * @param Carbon|null $at 打刻日時（nullの場合は現在時刻）
     * @return void
     * @throws \RuntimeException 状態に合わない打刻の場合
     */
    public function recordStamp(User $user, string $stampType, ?Carbon $at = null): void
    {
        $at = $at ?? Carbon::now();
        $today = $at->copy()->startOfDay();
        $now = $at->copy();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->with('breakTimes')
            ->first();

        DB::beginTransaction();

        try {
            switch ($stampType) {
                case 'clock_in':
                    $this->handleClockIn($user, $attendance, $today, $now);
                    break;

                case 'break_start':
                case 'break_end':
                case 'clock_out':
                    $this->handleBreakOrClockOut($attendance, $stampType, $now);
                    break;

                default:
                    throw new \RuntimeException('無効な打刻タイプです');
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 出勤打刻を処理する。
     */
    private function handleClockIn(User $user, ?Attendance $attendance, Carbon $today, Carbon $now): void
    {
        if ($attendance && $attendance->clock_in) {
            throw new \RuntimeException('既に出勤しています');
        }

        if ($attendance === null) {
            Attendance::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => $now->format('H:i:s'),
            ]);
        } else {
            $attendance->update([
                'clock_in' => $now->format('H:i:s'),
            ]);
        }
    }

    /**
     * 休憩開始・休憩終了・退勤打刻を処理する。
     */
    private function handleBreakOrClockOut(?Attendance $attendance, string $stampType, Carbon $now): void
    {
        if ($attendance === null || $attendance->clock_in === null) {
            throw new \RuntimeException('まだ出勤していません');
        }

        if ($stampType !== 'break_end' && $attendance->clock_out) {
            throw new \RuntimeException('既に退勤しています');
        }

        $activeBreak = BreakTime::where('attendance_id', $attendance->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();

        if ($stampType === 'break_start') {
            if ($activeBreak) {
                throw new \RuntimeException('既に休憩中です');
            }

            BreakTime::create([
                'attendance_id' => $attendance->id,
                'break_start' => $now->format('H:i:s'),
            ]);
        } elseif ($stampType === 'break_end') {
            if ($activeBreak === null) {
                throw new \RuntimeException('休憩中ではありません');
            }

            $activeBreak->update([
                'break_end' => $now->format('H:i:s'),
            ]);
        } elseif ($stampType === 'clock_out') {
            if ($activeBreak) {
                throw new \RuntimeException('休憩を終了してから退勤してください');
            }

            $attendance->update([
                'clock_out' => $now->format('H:i:s'),
            ]);
        }
    }
}
