<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Http\Requests\CorrectionRequest;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\BreakCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 勤怠打刻画面を表示
     * 当日の勤怠情報を取得し、打刻可能な状態を判定して表示
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();
        
        // 当日の勤怠情報を取得（休憩時間も一緒に取得）
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->with('breakTimes')
            ->first();
        
        // 現在の勤怠状態を取得（勤務外/出勤中/休憩中/退勤済）
        $status = $this->getStatus($attendance);
        $currentTime = Carbon::now();
        
        return view('home', [
            'attendance' => $attendance,
            'status' => $status,
            'date' => $today,
            'currentTime' => $currentTime,
        ]);
    }

    /**
     * 打刻処理を実行
     * 出勤/退勤/休憩開始/休憩終了の各打刻を処理し、状態遷移の整合性をチェック
     */
    public function store(StampRequest $request)
    {
        $user = Auth::user();
        $today = Carbon::today();
        $now = Carbon::now();
        
        DB::beginTransaction();
        
        try {
            // 当日の勤怠情報を取得
            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->with('breakTimes')
                ->first();
            
            // 打刻タイプに応じて処理を分岐
            switch ($request->stamp_type) {
                case 'clock_in':
                    // 既に出勤している場合はエラー
                    if ($attendance && $attendance->clock_in) {
                        return back()->withErrors(['stamp_type' => '既に出勤しています']);
                    }
                    
                    // 勤怠レコードが存在しない場合は新規作成、存在する場合は更新
                    if (!$attendance) {
                        $attendance = Attendance::create([
                            'user_id' => $user->id,
                            'date' => $today,
                            'clock_in' => $now->format('H:i:s'),
                        ]);
                    } else {
                        $attendance->update([
                            'clock_in' => $now->format('H:i:s'),
                        ]);
                    }
                    break;
                    
                case 'break_start':
                case 'break_end':
                case 'clock_out':
                    // 出勤していない場合はエラー
                    if (!$attendance || !$attendance->clock_in) {
                        return back()->withErrors(['stamp_type' => 'まだ出勤していません']);
                    }
                    
                    // 既に退勤している場合（break_endを除く）はエラー
                    if ($request->stamp_type !== 'break_end' && $attendance->clock_out) {
                        return back()->withErrors(['stamp_type' => '既に退勤しています']);
                    }
                    
                    // 現在アクティブな休憩（終了時刻が未設定）を取得
                    $activeBreak = BreakTime::where('attendance_id', $attendance->id)
                        ->whereNotNull('break_start')
                        ->whereNull('break_end')
                        ->first();
                    
                    if ($request->stamp_type === 'break_start') {
                        // 既に休憩中の場合はエラー
                        if ($activeBreak) {
                            return back()->withErrors(['stamp_type' => '既に休憩中です']);
                        }
                        
                        // 新しい休憩レコードを作成
                        BreakTime::create([
                            'attendance_id' => $attendance->id,
                            'break_start' => $now->format('H:i:s'),
                        ]);
                    } elseif ($request->stamp_type === 'break_end') {
                        // 休憩中でない場合はエラー
                        if (!$activeBreak) {
                            return back()->withErrors(['stamp_type' => '休憩中ではありません']);
                        }
                        
                        // アクティブな休憩の終了時刻を更新
                        $activeBreak->update([
                            'break_end' => $now->format('H:i:s'),
                        ]);
                    } elseif ($request->stamp_type === 'clock_out') {
                        // 休憩中の場合はエラー（休憩終了後に退勤可能）
                        if ($activeBreak) {
                            return back()->withErrors(['stamp_type' => '休憩を終了してから退勤してください']);
                        }
                        
                        // 退勤時刻を更新
                        $attendance->update([
                            'clock_out' => $now->format('H:i:s'),
                        ]);
                    }
                    break;
            }
            
            DB::commit();
            
            return redirect()->route('attendance.index')->with('success', '打刻が完了しました');
            
        } catch (\Exception $exception) {
            DB::rollBack();
            return back()->withErrors(['stamp_type' => '打刻処理に失敗しました']);
        }
    }

    /**
     * 現在の勤怠状態を取得
     * 勤務外/出勤中/休憩中/退勤済のいずれかを返す
     */
    private function getStatus($attendance): string
    {
        // 勤怠レコードが存在しない、または出勤時刻が未設定の場合は勤務外
        if (!$attendance || !$attendance->clock_in) {
            return '勤務外';
        }
        
        // 退勤時刻が設定されている場合は退勤済
        if ($attendance->clock_out) {
            return '退勤済';
        }
        
        // アクティブな休憩（終了時刻が未設定）を検索
        $activeBreak = BreakTime::where('attendance_id', $attendance->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();
        
        // アクティブな休憩が存在する場合は休憩中
        if ($activeBreak) {
            return '休憩中';
        }
        
        // 上記以外（出勤済みで退勤していない場合）は出勤中
        return '出勤中';
    }

    /**
     * 勤怠一覧画面を表示
     * 指定月の勤怠情報を取得し、前月・翌月のナビゲーション情報も生成
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        
        // クエリパラメータから月を取得（デフォルトは当月）
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        
        // 指定月の勤怠情報を取得（休憩時間も一緒に取得）
        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->with('breakTimes')
            ->orderBy('date', 'asc')
            ->get();
        
        // 前月・翌月の日付を生成（ナビゲーション用）
        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');
        
        return view('list', [
            'attendances' => $attendances,
            'currentMonth' => $currentMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    /**
     * 勤怠詳細画面を表示
     * 指定された勤怠情報を取得し、承認待ちの修正申請があるかチェック
     */
    public function show($id)
    {
        $user = Auth::user();
        
        // 自分の勤怠情報を取得（休憩時間と修正申請も一緒に取得）
        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();
        
        // 承認待ちの修正申請を取得
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->with('breakCorrectionRequests')
            ->first();
        
        // 承認待ちの申請がない場合のみ編集可能
        $canEdit = is_null($pendingRequest);
        
        return view('show', [
            'attendance' => $attendance,
            'canEdit' => $canEdit,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    /**
     * 修正申請を提出
     * 出勤・退勤時刻や休憩時間の修正申請を作成
     * 承認待ちチェックはCorrectionRequestのwithValidator()で行われる
     */
    public function update(CorrectionRequest $request, $id)
    {
        $user = Auth::user();
        
        // 自分の勤怠情報を取得
        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->with('breakTimes')
            ->firstOrFail();
        
        DB::beginTransaction();
        
        try {
            // 修正後の出勤・退勤時刻を取得（空文字の場合はnull）
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== '' ? trim($request->corrected_clock_in) : null;
            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== '' ? trim($request->corrected_clock_out) : null;
            
            // 修正申請レコードを作成（元の値と修正後の値を保存）
            $stampRequest = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'request_date' => Carbon::today()->toDateString(),
                'original_clock_in' => $attendance->clock_in,
                'original_clock_out' => $attendance->clock_out,
                'corrected_clock_in' => $correctedClockIn ? Carbon::createFromFormat('H:i', $correctedClockIn)->format('H:i:s') : null,
                'corrected_clock_out' => $correctedClockOut ? Carbon::createFromFormat('H:i', $correctedClockOut)->format('H:i:s') : null,
                'note' => trim($request->note),
            ]);
            
            // 休憩時間の修正申請を作成
            $breakTimes = $request->break_times ?? [];
            
            foreach ($breakTimes as $break) {
                $breakStart = isset($break['break_start']) && trim($break['break_start']) !== '' ? trim($break['break_start']) : null;
                $breakEnd = isset($break['break_end']) && trim($break['break_end']) !== '' ? trim($break['break_end']) : null;
                
                // 開始時刻と終了時刻の両方が入力されている場合のみ処理
                if (!$breakStart || !$breakEnd) {
                    continue;
                }
                
                // 既存の休憩時間かどうかを判定
                $existingBreak = null;
                if (isset($break['id']) && !empty($break['id'])) {
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                }
                
                // 休憩時間の修正申請を作成（既存の休憩の修正か新規追加かをbreak_time_idで判定）
                BreakCorrectionRequest::create([
                    'stamp_correction_request_id' => $stampRequest->id,
                    'break_time_id' => $existingBreak ? $existingBreak->id : null, // nullの場合は新規追加
                    'original_break_start' => $existingBreak ? $existingBreak->break_start : null,
                    'original_break_end' => $existingBreak ? $existingBreak->break_end : null,
                    'corrected_break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                    'corrected_break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                ]);
            }
            
            DB::commit();
            
            Log::info('修正申請が正常に作成されました', [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'stamp_request_id' => $stampRequest->id,
            ]);
            
            return redirect()->route('correction.index')->with('success', '修正申請を送信しました。');
            
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('修正申請の作成に失敗しました', [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            return back()->withErrors(['note' => '修正申請の作成に失敗しました。エラー: ' . $exception->getMessage()])->withInput();
        }
    }
}

