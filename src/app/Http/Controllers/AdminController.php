<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\StampCorrectionRequest;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Requests\CorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * コンストラクタ
     * 管理者権限チェックのミドルウェアを適用
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
     * 管理者の勤怠一覧画面を表示
     * 指定日の出勤者一覧を表示（権限に応じてフィルタリング）
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();

        $dateParam = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentDate = Carbon::parse($dateParam)->startOfDay();

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

        return view('admin.index', [
            'attendances' => $attendances,
            'currentDate' => $currentDate,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
        ]);
    }

    /**
     * 管理者の勤怠詳細画面を表示
     * 指定された勤怠情報を取得し、承認待ちの修正申請があるかチェックして編集可否を判定
     */
    public function show($id)
    {
        $currentUser = Auth::user();

        // 勤怠レコード取得（ユーザー情報、休憩時間、修正申請も一緒に取得）
        $attendance = Attendance::where('id', $id)
            ->with(['user', 'breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();

        // 権限チェック（管理者が閲覧可能な勤怠かどうか 部門外の勤怠は閲覧不可、部門1のみ全閲覧可能）
        if (!$currentUser->canViewAttendance($attendance->user_id)) {
            abort(403, 'アクセスが拒否されました');
        }

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
     * 管理者による勤怠情報の直接修正
     * フルアクセス権限の管理者は自身の勤怠も直接修正可能
     * 部門アクセス権限の管理者が自身の勤怠を修正する場合は申請として扱う
     * 認可チェックはCorrectionRequestのauthorize()で行われる
     */
    public function update(CorrectionRequest $request, $id)
    {
        $currentUser = Auth::user();

        // 勤怠レコード取得（休憩時間も一緒に取得）
        $attendance = Attendance::where('id', $id)
            ->with(['breakTimes'])
            ->firstOrFail();

        // 部門アクセス権限の管理者が自身の勤怠を修正する場合は申請として扱う
        // フルアクセス権限の管理者は自身の直接修正が可能
        if ($currentUser->role === 'admin' && $attendance->user_id === $currentUser->id && !$currentUser->hasFullAccess()) {
            return $this->createCorrectionRequest($request, $attendance);
        }

        // 承認待ちチェックはCorrectionRequestのwithValidator()で行われる

        DB::beginTransaction();

        try {
            // 出勤・退勤時刻の更新（入力がない場合は既存の値を維持）
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== ''
                ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_in))->format('H:i:s')
                : $attendance->clock_in;

            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== ''
                ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_out))->format('H:i:s')
                : $attendance->clock_out;

            // 勤怠レコードを更新（最終更新者・最終更新日時も記録）
            $attendance->update([
                'clock_in' => $correctedClockIn,
                'clock_out' => $correctedClockOut,
                'note' => trim($request->note),
                'last_modified_by' => $currentUser->id,
                'last_modified_at' => Carbon::now(),
            ]);

            // 休憩時間の更新
            $breakTimes = $request->break_times ?? [];
            $existingBreakIds = []; // 更新・作成された休憩時間のIDを保持

            foreach ($breakTimes as $break) {
                $breakStart = isset($break['break_start']) && trim($break['break_start']) !== '' ? trim($break['break_start']) : null;
                $breakEnd = isset($break['break_end']) && trim($break['break_end']) !== '' ? trim($break['break_end']) : null;

                if (!$breakStart || !$breakEnd) {
                    continue;
                }

                if (isset($break['id']) && !empty($break['id'])) {
                    // 既存の休憩時間を更新
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                    if ($existingBreak) {
                        $existingBreak->update([
                            'break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                            'break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                        ]);
                        $existingBreakIds[] = $existingBreak->id;
                    }
                } else {
                    // 新規休憩時間を作成
                    $newBreak = $attendance->breakTimes()->create([
                        'break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                        'break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                    ]);
                    $existingBreakIds[] = $newBreak->id;
                }
            }

            // 更新・作成された休憩時間に含まれない既存の休憩時間を削除
            $attendance->breakTimes()->whereNotIn('id', $existingBreakIds)->delete();

            DB::commit();

            return redirect()->route('admin.show', $attendance->id)->with('success', '勤怠情報を修正しました');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['attendance' => '修正に失敗しました'])->withInput();
        }
    }

    /**
     * 部門アクセス権限の管理者が自身の勤怠を修正する場合には申請として作成する
     */
    private function createCorrectionRequest(CorrectionRequest $request, Attendance $attendance)
    {
        try {
            $stampRequestController = app(StampCorrectionRequestController::class);
            $stampRequestController->store($request, $attendance->id);

            return redirect()->route('admin.show', $attendance->id)->with('success', '修正申請を提出しました');
        } catch (\Exception $e) {
            return back()->withErrors(['attendance' => '修正申請の作成に失敗しました'])->withInput();
        }
    }

    /**
     * 管理者のスタッフ一覧画面を表示
     * 権限に応じて管轄するスタッフの一覧を表示
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
     * 管理者のスタッフ別月次勤怠一覧画面を表示
     */
    public function list(Request $request, $id)
    {
        $currentUser = Auth::user();

        $targetUser = User::findOrFail($id);

        if (!$currentUser->canViewAttendance($targetUser->id)) {
            abort(403, 'アクセスが拒否されました');
        }

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

        // CSV出力機能（downloadパラメータがcsvの場合）
        if ($request->get('download') === 'csv') {
            $filename = 'attendance_' . $targetUser->id . '_' . $currentMonth->format('Ymd') . '.csv';

            $csvData = [];
            $csvData[] = ['日付', '出勤時刻', '退勤時刻', '休憩時間', '実働時間'];

            $daysInMonth = $currentMonth->daysInMonth;
            $firstDay = $currentMonth->copy()->startOfMonth();

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $currentDate = $firstDay->copy()->addDays($day - 1);
                $attendance = $attendances->first(function ($att) use ($currentDate) {
                    return $att->date->format('Y-m-d') === $currentDate->format('Y-m-d');
                });

                $csvData[] = [
                    $currentDate->format('Y-m-d'),
                    $attendance && $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '',
                    $attendance && $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '',
                    $attendance ? $attendance->getTotalBreakTime() : '',
                    $attendance ? $attendance->getWorkTime() : '',
                ];
            }

            // CSV出力（excelで開けるようにUTF-8 BOM付き）
            $output = fopen('php://temp', 'r+');

            fwrite($output, "\xEF\xBB\xBF");

            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }

            rewind($output);
            $csvContent = stream_get_contents($output);
            fclose($output);

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

