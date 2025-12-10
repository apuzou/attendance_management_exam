<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CorrectionRequest extends FormRequest
{
    /**
     * 管理者による修正かどうかを判定
     */
    protected function isAdminRequest(): bool
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin') {
            return false;
        }
        
        // ルート名で判定
        $routeName = $this->route()->getName();
        return $routeName === 'admin.update';
    }

    public function authorize()
    {
        $attendance = Attendance::find($this->route('id'));
        
        if (!$attendance) {
            return false;
        }
        
        // 管理者の場合は権限チェック
        if ($this->isAdminRequest()) {
            return Auth::user()->canViewAttendance($attendance->user_id);
        }
        
        // 一般ユーザーの場合は自分の勤怠のみ
        return $attendance->user_id === Auth::id();
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
            'note.required' => '備考を記入してください',
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
            $isAdmin = $this->isAdminRequest();

            if (!$attendance) {
                $validator->errors()->add('attendance', '無効な勤怠情報です');
                return;
            }

            // 権限チェック
            if ($isAdmin) {
                if (!Auth::user()->canViewAttendance($attendance->user_id)) {
                    $validator->errors()->add('attendance', '無効な勤怠情報です');
                    return;
                }
            } else {
                if ($attendance->user_id !== Auth::id()) {
                    $validator->errors()->add('attendance', '無効な勤怠情報です');
                    return;
                }
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
            
            // 管理者の場合は既存の出退勤時刻も考慮（入力がない場合）
            if ($isAdmin && !$clockInTime && $attendance->clock_in) {
                $clockInTime = Carbon::parse($attendance->clock_in);
            }
            if ($isAdmin && !$clockOutTime && $attendance->clock_out) {
                $clockOutTime = Carbon::parse($attendance->clock_out);
            }

            // 有効な休憩時間を収集（開始時刻と終了時刻の両方が入力されているもの）
            $validBreakTimes = [];
            foreach ($breakTimes as $index => $break) {
                $breakStartValue = isset($break['break_start']) ? trim($break['break_start']) : '';
                $breakEndValue = isset($break['break_end']) ? trim($break['break_end']) : '';

                // 片方だけ入力されている場合はエラー（一般ユーザー用のみ）
                if (!$isAdmin) {
                    if (!empty($breakStartValue) && empty($breakEndValue)) {
                        $validator->errors()->add("break_times.{$index}.break_end", '休憩終了時刻を入力してください');
                        continue;
                    }

                    if (empty($breakStartValue) && !empty($breakEndValue)) {
                        $validator->errors()->add("break_times.{$index}.break_start", '休憩開始時刻を入力してください');
                        continue;
                    }
                }

                // 両方空の場合はスキップ（新規休憩欄が空の場合）
                if (empty($breakStartValue) && empty($breakEndValue)) {
                    continue;
                }

                // 片方だけ入力されている場合（管理者用はスキップ）
                if (empty($breakStartValue) || empty($breakEndValue)) {
                    continue;
                }

                $breakStart = Carbon::parse($breakStartValue);
                $breakEnd = Carbon::parse($breakEndValue);

                // 各休憩の開始時刻 < 終了時刻のチェック（一般ユーザー用のみ）
                if ($breakEnd->lte($breakStart)) {
                    if (!$isAdmin) {
                        $validator->errors()->add("break_times.{$index}.break_end", '休憩終了時刻は開始時刻より後に設定してください');
                    }
                    continue;
                }

                $validBreakTimes[] = [
                    'index' => $index,
                    'start' => $breakStart,
                    'end' => $breakEnd,
                    'start_value' => $breakStartValue,
                    'end_value' => $breakEndValue,
                ];
            }

            // 出勤時間が入力されている場合、すべての休憩開始時間が出勤時間以降であることをチェック
            if ($clockInTime) {
                foreach ($validBreakTimes as $break) {
                    if ($break['start']->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$break['index']}.break_start", '休憩開始時間は出勤時間より後に設定してください');
                    }
                }
            }

            // 退勤時間が入力されている場合、すべての休憩終了時間が退勤時間以前であることをチェック
            if ($clockOutTime) {
                foreach ($validBreakTimes as $break) {
                    if ($break['end']->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$break['index']}.break_end", '休憩終了時間は退勤時間より前に設定してください');
                    }
                }
            }

            // 出勤時間と退勤時間の両方が入力されている場合の詳細チェック（一般ユーザー用のみ）
            if ($clockInTime && $clockOutTime && !$isAdmin) {
                // 休憩時間を時系列でソート（開始時刻でソート）
                // これにより、休憩時間同士の重複・順序チェックが正確に行える
                usort($validBreakTimes, function ($firstBreak, $secondBreak) {
                    return $firstBreak['start']->gt($secondBreak['start']) ? 1 : -1;
                });

                // 休憩時間同士の重複・順序チェックと範囲チェック
                for ($index = 0; $index < count($validBreakTimes); $index++) {
                    $currentBreak = $validBreakTimes[$index];

                    // 現在の休憩時間が出勤時間と退勤時間の範囲内にあることをチェック
                    if ($currentBreak['start']->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_start", '休憩開始時間は出勤時間より後に設定してください');
                    }

                    if ($currentBreak['start']->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_start", '休憩開始時間は退勤時間より前に設定してください');
                    }

                    if ($currentBreak['end']->gt($clockOutTime)) {
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", '休憩終了時間は退勤時間より前に設定してください');
                    }

                    if ($currentBreak['end']->lt($clockInTime)) {
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", '休憩終了時間は出勤時間より後に設定してください');
                    }

                    // 次の休憩時間との重複・順序チェック
                    // 現在の休憩終了時刻が次の休憩開始時刻より後である場合（重複）を検出
                    if ($index < count($validBreakTimes) - 1) {
                        $nextBreak = $validBreakTimes[$index + 1];

                        if ($currentBreak['end']->gt($nextBreak['start'])) {
                            $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", "休憩終了時刻（{$currentBreak['end_value']}）は、次の休憩開始時刻（{$nextBreak['start_value']}）より前に設定してください");
                        }
                        // 現在の休憩終了時刻が次の休憩開始時刻と等しい場合は許可（連続している場合）
                    }
                }

                // 休憩時間の合計が実働時間を超えないかチェック
                $totalBreakMinutes = 0;
                foreach ($validBreakTimes as $break) {
                    $totalBreakMinutes += $break['start']->diffInMinutes($break['end']);
                }

                $workMinutes = $clockInTime->diffInMinutes($clockOutTime);
                if ($totalBreakMinutes > $workMinutes) {
                    $validator->errors()->add('break_times', '休憩時間は実働時間を超えることはできません');
                }
            } elseif (count($validBreakTimes) > 0) {
                // 出勤時間または退勤時間が入力されていない場合でも、休憩時間同士の重複・順序チェック
                // 休憩時間を時系列でソート
                usort($validBreakTimes, function ($firstBreak, $secondBreak) {
                    return $firstBreak['start']->gt($secondBreak['start']) ? 1 : -1;
                });

                // 休憩時間同士の重複・順序チェック
                // 前の休憩終了時刻が次の休憩開始時刻より後になっていないか確認
                for ($index = 0; $index < count($validBreakTimes) - 1; $index++) {
                    $currentBreak = $validBreakTimes[$index];
                    $nextBreak = $validBreakTimes[$index + 1];

                    if ($currentBreak['end']->gt($nextBreak['start'])) {
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", "休憩終了時刻（{$currentBreak['end_value']}）は、次の休憩開始時刻（{$nextBreak['start_value']}）より前に設定してください");
                    }
                }
            }
        });
    }
}

