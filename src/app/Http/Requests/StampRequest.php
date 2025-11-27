<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StampRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'stamp_type' => ['required', 'string', 'in:clock_in,clock_out,break_start,break_end'],
        ];
    }

    public function messages()
    {
        return [
            'stamp_type.required' => '打刻タイプを入力してください',
            'stamp_type.string' => '打刻タイプは文字列で入力してください',
            'stamp_type.in' => '無効な打刻タイプです',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            $today = Carbon::today();

            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->with('breakTimes')
                ->first();

            $stampType = $this->stamp_type;

            switch ($stampType) {
                case 'clock_in':
                    if ($attendance && $attendance->clock_in) {
                        $validator->errors()->add('stamp_type', '既に出勤しています');
                    }
                    return;

                case 'break_start':
                case 'break_end':
                case 'clock_out':
                    if (!$attendance || !$attendance->clock_in) {
                        $validator->errors()->add('stamp_type', 'まだ出勤していません');
                        return;
                    }

                    if ($stampType !== 'break_end' && $attendance->clock_out) {
                        $validator->errors()->add('stamp_type', '既に退勤しています');
                        return;
                    }

                    $activeBreak = BreakTime::where('attendance_id', $attendance->id)
                        ->whereNotNull('break_start')
                        ->whereNull('break_end')
                        ->first();

                    if ($stampType === 'break_start' && $activeBreak) {
                        $validator->errors()->add('stamp_type', '既に休憩中です');
                        return;
                    }

                    if ($stampType === 'break_end' && !$activeBreak) {
                        $validator->errors()->add('stamp_type', '休憩中ではありません');
                        return;
                    }

                    if ($stampType === 'clock_out' && $activeBreak) {
                        $validator->errors()->add('stamp_type', '休憩を終了してから退勤してください');
                    }
                    break;
            }
        });
    }
}

