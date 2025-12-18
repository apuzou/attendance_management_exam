<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Http\Requests\CorrectionRequest;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
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

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->with('breakTimes')
            ->first();

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
            $attendance = Attendance::where('user_id', $user->id)
                ->where('date', $today)
                ->with('breakTimes')
                ->first();

            // 打刻タイプに応じて処理を分岐
            switch ($request->stamp_type) {
                case 'clock_in':
                    if ($attendance && $attendance->clock_in) {
                        return back()->withErrors(['stamp_type' => '既に出勤しています']);
                    }

                    if ($attendance === null) {
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
                    if ($attendance === null || $attendance->clock_in === null) {
                        return back()->withErrors(['stamp_type' => 'まだ出勤していません']);
                    }

                    if ($request->stamp_type !== 'break_end' && $attendance->clock_out) {
                        return back()->withErrors(['stamp_type' => '既に退勤しています']);
                    }

                    // 現在アクティブな休憩（終了時刻が未設定）を取得
                    $activeBreak = BreakTime::where('attendance_id', $attendance->id)
                        ->whereNotNull('break_start')
                        ->whereNull('break_end')
                        ->first();

                    if ($request->stamp_type === 'break_start') {
                        if ($activeBreak) {
                            return back()->withErrors(['stamp_type' => '既に休憩中です']);
                        }

                        BreakTime::create([
                            'attendance_id' => $attendance->id,
                            'break_start' => $now->format('H:i:s'),
                        ]);
                    } elseif ($request->stamp_type === 'break_end') {
                        if ($activeBreak === null) {
                            return back()->withErrors(['stamp_type' => '休憩中ではありません']);
                        }

                        $activeBreak->update([
                            'break_end' => $now->format('H:i:s'),
                        ]);
                    } elseif ($request->stamp_type === 'clock_out') {
                        if ($activeBreak) {
                            return back()->withErrors(['stamp_type' => '休憩を終了してから退勤してください']);
                        }

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
        if ($attendance === null || $attendance->clock_in === null) {
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
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->with('breakTimes')
            ->orderBy('date', 'asc')
            ->get();

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

        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();

        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->with('breakCorrectionRequests')
            ->first();

        $canEdit = is_null($pendingRequest);

        return view('show', [
            'attendance' => $attendance,
            'canEdit' => $canEdit,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    /**
     * 出勤・退勤時刻や休憩時間の修正申請を提出
     */
    public function update(CorrectionRequest $request, $id)
    {
        try {
            $stampRequestController = app(StampCorrectionRequestController::class);
            $stampRequestController->store($request, $id);

            return redirect()->route('correction.index')->with('success', '修正申請を送信しました。');
        } catch (\Exception $exception) {
            Log::error('修正申請の作成に失敗しました', [
                'user_id' => Auth::id(),
                'attendance_id' => $id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return back()->withErrors(['note' => '修正申請の作成に失敗しました。エラー: ' . $exception->getMessage()])->withInput();
        }
    }
}

