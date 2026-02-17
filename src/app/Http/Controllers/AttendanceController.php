<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Http\Requests\CorrectionRequest;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Services\CorrectionRequestService;
use App\Services\StampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * 勤怠コントローラ
 *
 * 打刻、勤怠一覧・詳細、修正申請の提出を処理する。
 */
class AttendanceController extends Controller
{
    /**
     * 勤怠打刻画面を表示する。
     *
     * 当日の勤怠情報を取得し、打刻可能な状態を判定して表示する。
     *
     * @return \Illuminate\View\View
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
     * 打刻処理を実行する。
     *
     * 出勤/退勤/休憩開始/休憩終了の各打刻を処理し、状態遷移の整合性をチェックする。
     *
     * @param StampRequest $request 打刻リクエスト
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StampRequest $request)
    {
        $user = Auth::user();

        try {
            $stampService = app(StampService::class);
            $stampService->recordStamp($user, $request->stamp_type);

            return redirect()->route('attendance.index')->with('success', '打刻が完了しました');
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['stamp_type' => $exception->getMessage()]);
        } catch (\Exception $exception) {
            return back()->withErrors(['stamp_type' => '打刻処理に失敗しました']);
        }
    }

    /**
     * 現在の勤怠状態を取得する。
     *
     * 勤務外/出勤中/休憩中/退勤済のいずれかを返す。
     *
     * @param \App\Models\Attendance|null $attendance 勤怠レコード
     * @return string 状態（勤務外/出勤中/休憩中/退勤済）
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
     * 勤怠一覧画面を表示する。
     *
     * 指定月の勤怠情報を取得して表示する。
     *
     * @param Request $request HTTPリクエスト（monthパラメータで月を指定）
     * @return \Illuminate\View\View
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
     * 勤怠詳細画面を表示する。
     *
     * 指定された勤怠情報を取得し、承認待ちの修正申請があるかチェックする。
     *
     * @param int $id 勤怠ID
     * @return \Illuminate\View\View|\Illuminate\Http\Response
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
     * 出勤・退勤時刻や休憩時間の修正申請を提出する。
     *
     * @param CorrectionRequest $request 修正申請リクエスト
     * @param int $id 勤怠ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(CorrectionRequest $request, $id)
    {
        try {
            $attendance = Attendance::where('id', $id)
                ->where('user_id', Auth::id())
                ->with('breakTimes')
                ->firstOrFail();

            $correctionRequestService = app(CorrectionRequestService::class);
            $correctionRequestService->create($attendance, Auth::user(), $request->all());

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

