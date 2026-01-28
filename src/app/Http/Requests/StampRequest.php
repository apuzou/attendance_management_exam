<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * 打刻リクエスト
 * 出勤・退勤・休憩開始・休憩終了の打刻処理のバリデーションを行う
 */
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
            'stamp_type.already_clocked_in' => '既に出勤しています',
            'stamp_type.not_clocked_in' => 'まだ出勤していません',
            'stamp_type.already_clocked_out' => '既に退勤しています',
            'stamp_type.already_on_break' => '既に休憩中です',
            'stamp_type.not_on_break' => '休憩中ではありません',
            'stamp_type.break_before_clock_out' => '休憩を終了してから退勤してください',
        ];
    }

    /**
     * カスタムバリデーションロジック
     * 打刻タイプに応じた状態チェックを行う
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();
            if (!$user) {
                return;
            }

            $today = Carbon::today();
            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->with('breakTimes')
                ->first();

            $stampType = $this->stamp_type;

            switch ($stampType) {
                case 'clock_in':
                    if ($attendance && $attendance->clock_in) {
                        $validator->errors()->add('stamp_type', $this->messages()['stamp_type.already_clocked_in']);
                    }
                    break;

                case 'break_start':
                case 'break_end':
                case 'clock_out':
                    if ($attendance === null || $attendance->clock_in === null) {
                        $validator->errors()->add('stamp_type', $this->messages()['stamp_type.not_clocked_in']);
                        return;
                    }

                    if ($stampType !== 'break_end' && $attendance->clock_out) {
                        $validator->errors()->add('stamp_type', $this->messages()['stamp_type.already_clocked_out']);
                        return;
                    }

                    $activeBreak = $attendance->getActiveBreak();

                    if ($stampType === 'break_start') {
                        if ($activeBreak) {
                            $validator->errors()->add('stamp_type', $this->messages()['stamp_type.already_on_break']);
                        }
                    } elseif ($stampType === 'break_end') {
                        if ($activeBreak === null) {
                            $validator->errors()->add('stamp_type', $this->messages()['stamp_type.not_on_break']);
                        }
                    } elseif ($stampType === 'clock_out') {
                        if ($activeBreak) {
                            $validator->errors()->add('stamp_type', $this->messages()['stamp_type.break_before_clock_out']);
                        }
                    }
                    break;
            }
        });
    }
}
