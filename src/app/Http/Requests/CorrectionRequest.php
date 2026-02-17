<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * 修正申請リクエスト
 *
 * 出勤・退勤時刻や休憩時間の修正申請のバリデーションを行う。
 */
class CorrectionRequest extends FormRequest
{
    /**
     * 管理者による修正かどうか（キャッシュ用）
     */
    protected ?bool $isAdmin = null;

    /**
     * 管理者による修正かどうかを判定する。
     *
     * 一度判定した結果はプロパティにキャッシュする。
     *
     * @return bool 管理者による修正の場合true
     */
    protected function isAdminRequest(): bool
    {
        if ($this->isAdmin !== null) {
            return $this->isAdmin;
        }

        $user = Auth::user();
        if ($user === null || $user->role !== 'admin') {
            $this->isAdmin = false;
            return false;
        }

        $routeName = $this->route()->getName();
        $this->isAdmin = $routeName === 'admin.update';
        return $this->isAdmin;
    }

    /**
     * リクエストの認可を判定する。
     *
     * 管理者の場合は権限チェック、一般ユーザーの場合は自分の勤怠のみ許可する。
     *
     * @return bool 認可される場合true
     */
    public function authorize()
    {
        $attendance = Attendance::find($this->route('id'));

        if ($attendance === null) {
            return false;
        }

        if ($this->isAdminRequest()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            return $user->canViewAttendance($attendance->user_id);
        }

        return $attendance->user_id === Auth::id();
    }

    /**
     * バリデーションルールを取得する。
     *
     * @return array<string, array<int, string>>
     */
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


    /**
     * バリデーションエラーメッセージを取得する。
     *
     * @return array<string, string>
     */
    public function messages()
    {
        // 管理者と一般ユーザーで異なるメッセージを返す(テストケースID 11, 13に合わせる)
        $isAdmin = $this->isAdminRequest();

        $baseMessages = [
            'corrected_clock_in.date_format' => '修正出勤時間は時間形式で入力してください',
            'corrected_clock_out.date_format' => '修正退勤時間は時間形式で入力してください',
            'note.required' => '備考を記入してください',
            'note.string' => '備考は文字列で入力してください',
            'note.max' => '備考は255文字以内で入力してください',
            'break_times.array' => '休憩時間は配列形式で入力してください',
            'break_times.*.break_start.date_format' => '休憩開始時間は時間形式で入力してください',
            'break_times.*.break_end.date_format' => '休憩終了時間は時間形式で入力してください',
            'attendance.invalid' => '無効な勤怠情報です',
            'note.pending_request' => '承認待ちのため修正はできません。',
            'break_times.*.break_start.required' => '休憩開始時刻を入力してください',
            'break_times.*.break_end.required' => '休憩終了時刻を入力してください',
            'break_times.*.break_end.after_start' => '休憩終了時刻は開始時刻より後に設定してください',
            'break_times.exceeds_work_time' => '休憩時間は実働時間を超えることはできません',
        ];

        if ($isAdmin) {
            // 管理者用メッセージ（ID 13）
            $baseMessages['corrected_clock_in.invalid_time'] = '出勤時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_start.invalid_time'] = '休憩時間が不適切な値です';
            $baseMessages['break_times.*.break_end.invalid_time'] = '休憩時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_start.after_clock_in'] = '休憩時間もしくは出勤時間が不適切な値です';
            $baseMessages['break_times.*.break_start.before_clock_out'] = '休憩時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_end.before_clock_out'] = '休憩時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_end.after_clock_in'] = '休憩時間もしくは出勤時間が不適切な値です';
        } else {
            // 一般ユーザー用メッセージ（ID 11）
            $baseMessages['corrected_clock_in.invalid_time'] = '出勤時間が不適切な値です';
            $baseMessages['break_times.*.break_start.invalid_time'] = '休憩時間が不適切な値です';
            $baseMessages['break_times.*.break_end.invalid_time'] = '休憩終了時間が不適切な値です';
            $baseMessages['break_times.*.break_start.after_clock_in'] = '休憩時間もしくは出勤時間が不適切な値です';
            $baseMessages['break_times.*.break_start.before_clock_out'] = '休憩時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_end.before_clock_out'] = '休憩時間もしくは退勤時間が不適切な値です';
            $baseMessages['break_times.*.break_end.after_clock_in'] = '休憩時間もしくは出勤時間が不適切な値です';
        }

        return $baseMessages;
    }

    /**
     * カスタムバリデーションロジックを追加する。
     *
     * 出勤・退勤時刻の整合性、休憩時間の整合性をチェックする。
     *
     * @param \Illuminate\Validation\Validator $validator バリデーター
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $attendance = Attendance::find($this->route('id'));

            // 承認待ちの修正申請がある場合は新規申請不可
            $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
                ->whereNull('approved_at')
                ->first();

            if ($pendingRequest) {
                $validator->errors()->add('note', $this->messages()['note.pending_request']);
                return;
            }

            $correctedClockIn = $this->corrected_clock_in ? trim($this->corrected_clock_in) : null;
            $correctedClockOut = $this->corrected_clock_out ? trim($this->corrected_clock_out) : null;

            if ($correctedClockIn && $correctedClockOut) {
                $clockInTime = Carbon::parse($correctedClockIn);
                $clockOutTime = Carbon::parse($correctedClockOut);

                if ($clockInTime->gte($clockOutTime)) {
                    $validator->errors()->add('corrected_clock_in', $this->messages()['corrected_clock_in.invalid_time']);
                    return;
                }
            }

            $breakTimes = $this->break_times ?? [];
            $clockInTime = $correctedClockIn ? Carbon::parse($correctedClockIn) : null;
            $clockOutTime = $correctedClockOut ? Carbon::parse($correctedClockOut) : null;

            // 有効な休憩時間を収集（開始時刻と終了時刻の両方が入力されているもの）
            $validBreakTimes = [];
            foreach ($breakTimes as $index => $break) {
                $breakStartValue = isset($break['break_start']) ? trim($break['break_start']) : '';
                $breakEndValue = isset($break['break_end']) ? trim($break['break_end']) : '';

                if ($breakStartValue !== '' && $breakEndValue === '') {
                    $validator->errors()->add("break_times.{$index}.break_end", $this->messages()['break_times.*.break_end.required']);
                    continue;
                }

                if ($breakStartValue === '' && $breakEndValue !== '') {
                    $validator->errors()->add("break_times.{$index}.break_start", $this->messages()['break_times.*.break_start.required']);
                    continue;
                }

                if ($breakStartValue === '' && $breakEndValue === '') {
                    continue;
                }

                $breakStart = Carbon::parse($breakStartValue);
                $breakEnd = Carbon::parse($breakEndValue);

                // 各休憩の開始時刻 < 終了時刻のチェック
                if ($breakEnd->lte($breakStart)) {
                    $validator->errors()->add("break_times.{$index}.break_end", $this->messages()['break_times.*.break_end.after_start']);
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
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_start.after_clock_in' : 'break_times.*.break_start.invalid_time';
                        $validator->errors()->add("break_times.{$break['index']}.break_start", $this->messages()[$messageKey]);
                    }
                }
            }

            // 退勤時間が入力されている場合、すべての休憩終了時間が退勤時間以前であることをチェック
            if ($clockOutTime) {
                foreach ($validBreakTimes as $break) {
                    if ($break['end']->gt($clockOutTime)) {
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_end.invalid_time' : 'break_times.*.break_end.before_clock_out';
                        $validator->errors()->add("break_times.{$break['index']}.break_end", $this->messages()[$messageKey]);
                    }
                }
            }

            // 出勤時間と退勤時間の両方が入力されている場合の詳細チェック
            if ($clockInTime && $clockOutTime) {
                usort($validBreakTimes, function ($firstBreak, $secondBreak) {
                    return $firstBreak['start']->gt($secondBreak['start']) ? 1 : -1;
                });

                // 休憩時間同士の重複・順序チェックと範囲チェック
                for ($index = 0; $index < count($validBreakTimes); $index++) {
                    $currentBreak = $validBreakTimes[$index];

                    // 現在の休憩時間が出勤時間と退勤時間の範囲内にあることをチェック
                    if ($currentBreak['start']->lt($clockInTime)) {
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_start.after_clock_in' : 'break_times.*.break_start.invalid_time';
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_start", $this->messages()[$messageKey]);
                    }

                    if ($currentBreak['start']->gt($clockOutTime)) {
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_start.invalid_time' : 'break_times.*.break_start.before_clock_out';
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_start", $this->messages()[$messageKey]);
                    }

                    if ($currentBreak['end']->gt($clockOutTime)) {
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_end.invalid_time' : 'break_times.*.break_end.before_clock_out';
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", $this->messages()[$messageKey]);
                    }

                    if ($currentBreak['end']->lt($clockInTime)) {
                        $messageKey = $this->isAdminRequest() ? 'break_times.*.break_end.after_clock_in' : 'break_times.*.break_end.invalid_time';
                        $validator->errors()->add("break_times.{$currentBreak['index']}.break_end", $this->messages()[$messageKey]);
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
                    $validator->errors()->add('break_times', $this->messages()['break_times.exceeds_work_time']);
                }
            } elseif (count($validBreakTimes) > 0) {
                // 出勤時間または退勤時間が入力されていない場合でも、休憩時間同士の重複・順序チェック
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

