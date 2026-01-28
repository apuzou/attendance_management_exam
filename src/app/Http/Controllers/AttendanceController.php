<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Http\Requests\CorrectionRequest;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use Illuminate\Contracts\Auth\Authenticatable;
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

        $status = $attendance ? $attendance->getStatus() : Attendance::getStatusForNull();
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
     * 出勤/退勤/休憩開始/休憩終了の各打刻を処理
     * バリデーションはStampRequestで行われる
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
                    $this->handleClockIn($attendance, $today, $now, $user);
                    break;

                case 'break_start':
                    $this->handleBreakStart($attendance, $now);
                    break;

                case 'break_end':
                    $this->handleBreakEnd($attendance, $now);
                    break;

                case 'clock_out':
                    $this->handleClockOut($attendance, $now);
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
     * 出勤打刻を処理
     */
    private function handleClockIn(?Attendance $attendance, Carbon $today, Carbon $now, Authenticatable $user): void
    {
        if ($attendance === null) {
            $attendance = Attendance::create([
                'date' => $today,
                'clock_in' => $now->format('H:i:s'),
            ]);
            // 一括代入できない属性（user_id）は直接代入で設定
            $attendance->user_id = $user->id;
            $attendance->save();
        } else {
            $attendance->update([
                'clock_in' => $now->format('H:i:s'),
            ]);
        }
    }

    /**
     * 休憩開始打刻を処理
     */
    private function handleBreakStart(Attendance $attendance, Carbon $now): void
    {
        $breakTime = new BreakTime([
            'break_start' => $now->format('H:i:s'),
        ]);
        $breakTime->attendance_id = $attendance->id;
        $breakTime->save();
    }

    /**
     * 休憩終了打刻を処理
     */
    private function handleBreakEnd(Attendance $attendance, Carbon $now): void
    {
        $activeBreak = $attendance->getActiveBreak();
        $activeBreak->update([
            'break_end' => $now->format('H:i:s'),
        ]);
    }

    /**
     * 退勤打刻を処理
     */
    private function handleClockOut(Attendance $attendance, Carbon $now): void
    {
        $attendance->update([
            'clock_out' => $now->format('H:i:s'),
        ]);
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

