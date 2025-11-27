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

            $correctedClockIn = $this->corrected_clock_in;
            $correctedClockOut = $this->corrected_clock_out;

            if ($correctedClockIn && $correctedClockOut) {
                $clockInTime = Carbon::parse($correctedClockIn);
                $clockOutTime = Carbon::parse($correctedClockOut);

                if ($clockInTime->gte($clockOutTime)) {
                    $validator->errors()->add('corrected_clock_in', '出勤時刻または退勤時刻が不適切な値です');
                    return;
                }

                $breakTimes = $this->break_times ?? [];
                $totalBreakMinutes = 0;

                foreach ($breakTimes as $break) {
                    if (!empty($break['break_start']) && !empty($break['break_end'])) {
                        $breakStart = Carbon::parse($break['break_start']);
                        $breakEnd = Carbon::parse($break['break_end']);

                        if ($breakStart->lt($clockInTime)) {
                            $validator->errors()->add('break_times', '休憩時間が不適切な値です');
                            return;
                        }

                        if ($breakStart->gt($clockOutTime)) {
                            $validator->errors()->add('break_times', '休憩時間が不適切な値です');
                            return;
                        }

                        if ($breakEnd->gt($clockOutTime)) {
                            $validator->errors()->add('break_times', '休憩時間または退勤時刻が不適切な値です');
                            return;
                        }

                        if ($breakEnd->lte($breakStart)) {
                            $validator->errors()->add('break_times', '休憩開始時刻より前に休憩を終了できません');
                            return;
                        }

                        $totalBreakMinutes += $breakStart->diffInMinutes($breakEnd);
                    }
                }

                $workMinutes = $clockInTime->diffInMinutes($clockOutTime);
                if ($totalBreakMinutes > $workMinutes) {
                    $validator->errors()->add('break_times', '休憩時間は実働時間を超えることはできません');
                }
            }
        });
    }
}

