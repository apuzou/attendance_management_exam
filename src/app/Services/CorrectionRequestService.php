<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BreakCorrectionRequest;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 修正申請サービス
 *
 * 打刻修正申請と休憩修正申請の作成を行う。
 */
class CorrectionRequestService
{
    /**
     * 修正申請を作成する。
     *
     * @param Attendance $attendance 勤怠レコード
     * @param User $applicant 申請者
     * @param array $input 入力データ（corrected_clock_in, corrected_clock_out, note, break_times）
     * @return StampCorrectionRequest 作成された打刻修正申請
     */
    public function create(Attendance $attendance, User $applicant, array $input): StampCorrectionRequest
    {
        DB::beginTransaction();

        try {
            $correctedClockIn = $this->parseTime($input['corrected_clock_in'] ?? null);
            $correctedClockOut = $this->parseTime($input['corrected_clock_out'] ?? null);
            $note = trim($input['note'] ?? '');

            $stampRequest = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $applicant->id,
                'request_date' => Carbon::today()->toDateString(),
                'original_clock_in' => $attendance->clock_in,
                'original_clock_out' => $attendance->clock_out,
                'corrected_clock_in' => $correctedClockIn,
                'corrected_clock_out' => $correctedClockOut,
                'note' => $note,
            ]);

            $breakTimes = $input['break_times'] ?? [];

            foreach ($breakTimes as $break) {
                $breakStart = isset($break['break_start']) && trim($break['break_start'] ?? '') !== ''
                    ? trim($break['break_start']) : null;
                $breakEnd = isset($break['break_end']) && trim($break['break_end'] ?? '') !== ''
                    ? trim($break['break_end']) : null;

                if ($breakStart === null || $breakEnd === null) {
                    continue;
                }

                $existingBreak = null;
                if (isset($break['id']) && $break['id'] !== '') {
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                }

                BreakCorrectionRequest::create([
                    'stamp_correction_request_id' => $stampRequest->id,
                    'break_time_id' => $existingBreak ? $existingBreak->id : null,
                    'original_break_start' => $existingBreak ? $existingBreak->break_start : null,
                    'original_break_end' => $existingBreak ? $existingBreak->break_end : null,
                    'corrected_break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                    'corrected_break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                ]);
            }

            DB::commit();

            Log::info('修正申請が正常に作成されました', [
                'user_id' => $applicant->id,
                'attendance_id' => $attendance->id,
                'stamp_request_id' => $stampRequest->id,
            ]);

            return $stampRequest;
        } catch (\Exception $exception) {
            DB::rollBack();

            Log::error('修正申請の作成に失敗しました', [
                'user_id' => $applicant->id,
                'attendance_id' => $attendance->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_data' => $input,
            ]);

            throw $exception;
        }
    }

    /**
     * 時刻文字列をパースしてH:i:s形式で返す。
     */
    private function parseTime(?string $value): ?string
    {
        if ($value === null || trim($value ?? '') === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', trim($value))->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
