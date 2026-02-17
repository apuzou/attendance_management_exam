<?php

namespace App\Services;

use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 修正申請承認サービス
 *
 * 修正申請の承認処理を行い、勤怠・休憩に反映する。
 */
class CorrectionApprovalService
{
    /**
     * 修正申請を承認する。
     *
     * @param StampCorrectionRequest $correctionRequest 打刻修正申請
     * @param User $approver 承認者
     * @return void
     */
    public function approve(StampCorrectionRequest $correctionRequest, User $approver): void
    {
        DB::beginTransaction();

        try {
            $attendance = $correctionRequest->attendance;

            if ($correctionRequest->corrected_clock_in) {
                $attendance->clock_in = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_in)->format('H:i:s');
            }
            if ($correctionRequest->corrected_clock_out) {
                $attendance->clock_out = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_out)->format('H:i:s');
            }
            if ($correctionRequest->note) {
                $attendance->note = $correctionRequest->note;
            }
            $attendance->last_modified_by = $approver->id;
            $attendance->last_modified_at = Carbon::now();
            $attendance->save();

            foreach ($correctionRequest->breakCorrectionRequests as $breakCorrectionRequest) {
                if ($breakCorrectionRequest->break_time_id) {
                    $breakTime = $attendance->breakTimes->where('id', $breakCorrectionRequest->break_time_id)->first();
                    if ($breakTime) {
                        $breakTime->break_start = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s');
                        $breakTime->break_end = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s');
                        $breakTime->save();
                    }
                } else {
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s'),
                        'break_end' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s'),
                    ]);
                }
            }

            $correctionRequest->approved_by = $approver->id;
            $correctionRequest->approved_at = Carbon::now();
            $correctionRequest->save();

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
