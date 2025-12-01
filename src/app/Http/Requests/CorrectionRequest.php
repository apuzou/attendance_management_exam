<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CorrectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'corrected_clock_in' => ['nullable', 'date_format:H:i'],
            'corrected_clock_out' => ['nullable', 'date_format:H:i'],
            'note' => ['required', 'string', 'max:255'],
            'break_times' => ['nullable', 'array'],
            'break_times.*.break_start' => ['nullable', 'date_format:H:i'],
            'break_times.*.break_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages()
    {
        return [
            'corrected_clock_in.date_format' => '修正出勤時間は時間形式で入力してください',
            'corrected_clock_out.date_format' => '修正退勤時間は時間形式で入力してください',
            'note.required' => '備考を入力してください',
            'note.string' => '備考は文字列で入力してください',
            'note.max' => '備考は255文字以内で入力してください',
            'break_times.array' => '休憩時間は配列形式で入力してください',
            'break_times.*.break_start.date_format' => '休憩開始時間は時間形式で入力してください',
            'break_times.*.break_end.date_format' => '休憩終了時間は時間形式で入力してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $attendance = Attendance::find($this->route('id'));

            if (!$attendance || $attendance->user_id !== Auth::id()) {
                $validator->errors()->add('attendance', '無効な勤怠情報です');
                return;
            }

            $correctedClockIn = $this->corrected_clock_in ? trim($this->corrected_clock_in) : null;
            $correctedClockOut = $this->corrected_clock_out ? trim($this->corrected_clock_out) : null;

            // 出勤時間と退勤時間の両方が入力されている場合の整合性チェック
            if ($correctedClockIn && $correctedClockOut) {
                $clockInTime = Carbon::parse($correctedClockIn);
                $clockOutTime = Carbon::parse($correctedClockOut);

                if ($clockInTime->gte($clockOutTime)) {
                    $validator->errors()->add('corrected_clock_in', '出勤時刻または退勤時刻が不適切な値です');
                    return;
                }
            }

            $breakTimes = $this->break_times ?? [];
            $clockInTime = $correctedClockIn ? Carbon::parse($correctedClockIn) : null;
            $clockOutTime = $correctedClockOut ? Carbon::parse($correctedClockOut) : null;

            // 出勤時間が入力されている場合、すべての休憩開始時間が出勤時間以降であることをチェック
            if ($clockInTime) {
                foreach ($breakTimes as $index => $break) {
                    $breakStartValue = isset($break['break_start']) ? trim($break['break_start']) : '';

                    if (empty($breakStartValue)) {
                        continue;
                    }

                    $breakStart = Carbon::parse($breakStartValue);

                    if ($breakStart->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$index}.break_start", '休憩開始時間は出勤時間より後に設定してください');
                    }
                }
            }

            // 退勤時間が入力されている場合、すべての休憩終了時間が退勤時間以前であることをチェック
            if ($clockOutTime) {
                foreach ($breakTimes as $index => $break) {
                    $breakEndValue = isset($break['break_end']) ? trim($break['break_end']) : '';

                    if (empty($breakEndValue)) {
                        continue;
                    }

                    $breakEnd = Carbon::parse($breakEndValue);

                    if ($breakEnd->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$index}.break_end", '休憩終了時間は退勤時間より前に設定してください');
                    }
                }
            }

            // 出勤時間と退勤時間の両方が入力されている場合の詳細チェック
            if ($clockInTime && $clockOutTime) {
                $totalBreakMinutes = 0;

                foreach ($breakTimes as $index => $break) {
                    $breakStartValue = isset($break['break_start']) ? trim($break['break_start']) : '';
                    $breakEndValue = isset($break['break_end']) ? trim($break['break_end']) : '';

                    if (empty($breakStartValue) || empty($breakEndValue)) {
                        continue;
                    }

                    $breakStart = Carbon::parse($breakStartValue);
                    $breakEnd = Carbon::parse($breakEndValue);

                    // 休憩開始時間と終了時間が同じまたは開始時間が終了時間より後の場合はスキップ
                    if ($breakEnd->lte($breakStart)) {
                        continue;
                    }

                    // 休憩時間が出勤時間と退勤時間の範囲内にあることをチェック
                    if ($breakStart->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$index}.break_start", '休憩開始時間は出勤時間より後に設定してください');
                        continue;
                    }

                    if ($breakStart->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$index}.break_start", '休憩開始時間は退勤時間より前に設定してください');
                        continue;
                    }

                    if ($breakEnd->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$index}.break_end", '休憩終了時間は退勤時間より前に設定してください');
                        continue;
                    }

                    if ($breakEnd->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$index}.break_end", '休憩終了時間は出勤時間より後に設定してください');
                        continue;
                    }

                    $totalBreakMinutes += $breakStart->diffInMinutes($breakEnd);
                }

                $workMinutes = $clockInTime->diffInMinutes($clockOutTime);
                if ($totalBreakMinutes > $workMinutes) {
                    $validator->errors()->add('break_times', '休憩時間は実働時間を超えることはできません');
                }
            }
        });
    }
}

