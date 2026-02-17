<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\StampCorrectionRequest;
use App\Http\Requests\CorrectionRequest;
use App\Services\AttendanceCsvService;
use App\Services\AttendanceUpdateService;
use App\Services\CorrectionRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

/**
 * 管理者コントローラ
 *
 * 勤怠一覧・詳細、勤怠修正、スタッフ一覧、月次勤怠の閲覧・CSV出力を処理する。
 */
class AdminController extends Controller
{
    /**
     * コンストラクタ
     *
     * 管理者権限チェックのミドルウェアを適用する。
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::check() || Auth::user()->role !== 'admin') {
                abort(403, 'アクセスが拒否されました');
            }
            return $next($request);
        });
    }

    /**
     * 管理者の勤怠一覧画面を表示する。
     *
     * 指定日の出勤者一覧を表示する。権限に応じてフィルタリングする。
     *
     * @param Request $request HTTPリクエスト（date, calendar_monthパラメータ）
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();

        $dateParam = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentDate = Carbon::parse($dateParam)->startOfDay();

        // カレンダー表示月の処理（calendar_monthパラメータがあればその月、なければ現在の日付の月）
        $calendarMonthParam = $request->get('calendar_month');
        if ($calendarMonthParam) {
            $calendarMonth = Carbon::parse($calendarMonthParam)->startOfMonth();
        } else {
            $calendarMonth = $currentDate->copy()->startOfMonth();
        }

        $query = Attendance::where('date', $currentDate->toDateString())
            ->whereNotNull('clock_in')
            ->with(['user', 'breakTimes']);

        // 全アクセス権限（department_code=1）の場合は全員の勤怠を表示
        // 部門アクセス権限（department_code!=1）の場合は同じ部門スタッフの勤怠のみ表示
        if ($currentUser->hasDepartmentAccess()) {
            $sameDepartmentUserIds = User::where('department_code', $currentUser->department_code)
                ->pluck('id');

            $query->whereIn('user_id', $sameDepartmentUserIds);
        }

        $attendances = $query->orderBy('user_id', 'asc')->get();

        $prevDate = $currentDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $currentDate->copy()->addDay()->format('Y-m-d');
        $prevCalendarMonth = $calendarMonth->copy()->subMonth()->format('Y-m');
        $nextCalendarMonth = $calendarMonth->copy()->addMonth()->format('Y-m');

        return view('admin.index', [
            'attendances' => $attendances,
            'currentDate' => $currentDate,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'calendarMonth' => $calendarMonth,
            'prevCalendarMonth' => $prevCalendarMonth,
            'nextCalendarMonth' => $nextCalendarMonth,
        ]);
    }

    /**
     * 管理者の勤怠詳細画面を表示する。
     *
     * 指定された勤怠情報を取得し、承認待ちの修正申請があるかチェックして編集可否を判定する。
     *
     * @param int $id 勤怠ID
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function show($id)
    {
        $attendance = Attendance::where('id', $id)
            ->with(['user', 'breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();

        $this->authorize('view', $attendance);

        // 承認待ちの修正申請があるかチェック
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->with('breakCorrectionRequests')
            ->first();

        // 承認待ちの申請がない場合のみ編集可能
        $canEdit = is_null($pendingRequest);

        return view('admin.dailyShow', [
            'attendance' => $attendance,
            'canEdit' => $canEdit,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    /**
     * 管理者による勤怠情報の直接修正を行う。
     *
     * フルアクセス権限の管理者は自身の勤怠も直接修正可能。
     * 部門アクセス権限の管理者が自身の勤怠を修正する場合は申請として扱う。
     * 認可チェックはCorrectionRequestのauthorize()で行われる。
     *
     * @param CorrectionRequest $request 修正申請リクエスト
     * @param int $id 勤怠ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(CorrectionRequest $request, $id)
    {
        $currentUser = Auth::user();
        $attendance = Attendance::where('id', $id)
            ->with(['breakTimes'])
            ->firstOrFail();

        if ($currentUser->cannot('update', $attendance)) {
            return $this->createCorrectionRequest($request, $attendance);
        }

        try {
            $clockData = [
                'corrected_clock_in' => $request->filled('corrected_clock_in') ? trim($request->corrected_clock_in) : null,
                'corrected_clock_out' => $request->filled('corrected_clock_out') ? trim($request->corrected_clock_out) : null,
                'note' => trim($request->note ?? ''),
            ];

            $attendanceUpdateService = app(AttendanceUpdateService::class);
            $attendanceUpdateService->updateByAdmin(
                $attendance,
                $clockData,
                $request->break_times ?? [],
                $currentUser->id
            );

            return redirect()->route('admin.show', $attendance->id)->with('success', '勤怠情報を修正しました');
        } catch (\Exception $exception) {
            return back()->withErrors(['attendance' => '修正に失敗しました'])->withInput();
        }
    }

    /**
     * 部門アクセス権限の管理者が自身の勤怠を修正する場合に申請として作成する。
     *
     * @param CorrectionRequest $request 修正申請リクエスト
     * @param Attendance $attendance 勤怠レコード
     * @return \Illuminate\Http\RedirectResponse
     */
    private function createCorrectionRequest(CorrectionRequest $request, Attendance $attendance)
    {
        try {
            $correctionRequestService = app(CorrectionRequestService::class);
            $correctionRequestService->create($attendance, Auth::user(), $request->all());

            return redirect()->route('admin.show', $attendance->id)->with('success', '修正申請を提出しました');
        } catch (\Exception $exception) {
            return back()->withErrors(['attendance' => '修正申請の作成に失敗しました'])->withInput();
        }
    }

    /**
     * 管理者のスタッフ一覧画面を表示する。
     *
     * 権限に応じて管轄するスタッフの一覧を表示する。
     *
     * @return \Illuminate\View\View
     */
    public function staff()
    {
        $currentUser = Auth::user();

        // 全アクセス権限（department_code=1）の場合は全ユーザーを表示
        // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーを表示（自身も含む）
        $query = User::query();

        if ($currentUser->hasDepartmentAccess()) {
            $query->where('department_code', $currentUser->department_code);
        }

        $users = $query->select('id', 'name', 'email')
            ->orderBy('id', 'asc')
            ->get();

        return view('admin.staffList', [
            'users' => $users,
        ]);
    }

    /**
     * 管理者のスタッフ別月次勤怠一覧画面を表示する。
     *
     * download=csv の場合はCSV出力を行う。
     *
     * @param Request $request HTTPリクエスト（month, downloadパラメータ）
     * @param int $id 対象ユーザーID
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function list(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);

        $attendance = Attendance::where('user_id', $targetUser->id)->first();
        $attendanceForAuth = $attendance ?? Attendance::make(['user_id' => $targetUser->id]);
        $this->authorize('view', $attendanceForAuth);

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $attendances = Attendance::where('user_id', $targetUser->id)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->with('breakTimes')
            ->orderBy('date', 'asc')
            ->get();

        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

        if ($request->get('download') === 'csv') {
            $attendanceCsvService = app(AttendanceCsvService::class);
            $csvContent = $attendanceCsvService->generateMonthlyCsv($attendances, $currentMonth, $targetUser);
            $filename = $attendanceCsvService->generateFilename($targetUser->id, $currentMonth);

            return Response::make($csvContent, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return view('admin.monthlyShow', [
            'user' => $targetUser,
            'attendances' => $attendances,
            'currentMonth' => $currentMonth,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
        ]);
    }
}

