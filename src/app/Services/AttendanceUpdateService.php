<?php

namespace App\Services;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 勤怠更新サービス
 *
 * 管理者による勤怠情報の直接修正を行う。
 */
class AttendanceUpdateService
{
    /**
     * 管理者による勤怠情報の直接修正を行う。
     *
     * @param Attendance $attendance 勤怠レコード
     * @param array $clockData 出退勤データ（corrected_clock_in, corrected_clock_out, note）
     * @param array $breakTimesData 休憩時間データ
     * @param int $adminId 管理者のユーザーID
     * @return void
     */
    public function updateByAdmin(Attendance $attendance, array $clockData, array $breakTimesData, int $adminId): void
    {
        DB::beginTransaction();

        try {
            $correctedClockIn = $this->parseTime($clockData['corrected_clock_in'] ?? null) ?? $attendance->clock_in;
            $correctedClockOut = $this->parseTime($clockData['corrected_clock_out'] ?? null) ?? $attendance->clock_out;
            $note = trim($clockData['note'] ?? $attendance->note ?? '');

            $attendance->update([
                'clock_in' => $correctedClockIn,
                'clock_out' => $correctedClockOut,
                'note' => $note,
                'last_modified_by' => $adminId,
                'last_modified_at' => Carbon::now(),
            ]);

            $existingBreakIds = [];

            foreach ($breakTimesData as $break) {
                $breakStart = isset($break['break_start']) && trim($break['break_start'] ?? '') !== ''
                    ? trim($break['break_start']) : null;
                $breakEnd = isset($break['break_end']) && trim($break['break_end'] ?? '') !== ''
                    ? trim($break['break_end']) : null;

                if ($breakStart === null || $breakEnd === null) {
                    continue;
                }

                $formattedStart = Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s');
                $formattedEnd = Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s');

                if (isset($break['id']) && $break['id'] !== '') {
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                    if ($existingBreak) {
                        $existingBreak->update([
                            'break_start' => $formattedStart,
                            'break_end' => $formattedEnd,
                        ]);
                        $existingBreakIds[] = $existingBreak->id;
                    }
                } else {
                    $newBreak = $attendance->breakTimes()->create([
                        'break_start' => $formattedStart,
                        'break_end' => $formattedEnd,
                    ]);
                    $existingBreakIds[] = $newBreak->id;
                }
            }

            $attendance->breakTimes()->whereNotIn('id', $existingBreakIds)->delete();

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * 時刻文字列をパースしてH:i:s形式で返す。
     */
    private function parseTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', trim($value))->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
