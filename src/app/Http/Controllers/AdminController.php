<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\StampCorrectionRequest;
use App\Models\BreakCorrectionRequest;
use App\Http\Requests\CorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::check() || Auth::user()->role !== 'admin') {
                abort(403, 'アクセスが拒否されました');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        
        // 日付パラメータから日付を取得（デフォルトは今日）
        $dateParam = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentDate = Carbon::parse($dateParam)->startOfDay();
        
        // 該当日に出勤を打刻した勤怠情報を取得
        $query = Attendance::where('date', $currentDate->toDateString())
            ->whereNotNull('clock_in')
            ->with(['user', 'breakTimes']);
        
        // 全アクセス権限（department_code=1）の場合は全員の勤怠を表示
        // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーの勤怠のみ表示
        if ($currentUser->hasDepartmentAccess()) {
            // 同じ部門コードのユーザーIDを取得
            $sameDepartmentUserIds = User::where('department_code', $currentUser->department_code)
                ->pluck('id');
            
            $query->whereIn('user_id', $sameDepartmentUserIds);
        }
        // 全アクセス権限の場合はフィルタリングなし（全員の勤怠を表示）
        
        $attendances = $query->orderBy('user_id', 'asc')->get();
        
        // 前日・翌日の日付
        $prevDate = $currentDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $currentDate->copy()->addDay()->format('Y-m-d');
        
        return view('admin.index', [
            'attendances' => $attendances,
            'currentDate' => $currentDate,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
        ]);
    }

    public function show($id)
    {
        $currentUser = Auth::user();
        
        // 勤怠レコード取得（FN037）
        $attendance = Attendance::where('id', $id)
            ->with(['user', 'breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();
        
        // 権限チェック（FN037）
        if (!$currentUser->canViewAttendance($attendance->user_id)) {
            abort(403, 'アクセスが拒否されました');
        }
        
        // 承認待ちの修正申請があるかチェック（FN038）
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->with('breakCorrectionRequests')
            ->first();
        
        $canEdit = is_null($pendingRequest);
        
        return view('admin.dailyShow', [
            'attendance' => $attendance,
            'canEdit' => $canEdit,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    public function update(CorrectionRequest $request, $id)
    {
        $currentUser = Auth::user();
        
        // 勤怠レコード取得
        $attendance = Attendance::where('id', $id)
            ->with(['breakTimes'])
            ->firstOrFail();
        
        // 権限チェック
        if (!$currentUser->canViewAttendance($attendance->user_id)) {
            abort(403, 'アクセスが拒否されました');
        }
        
        // 部門アクセス権限の管理者が自身の勤怠を修正する場合は申請として扱う
        // フルアクセス権限の管理者は自身の直接修正が可能
        if ($currentUser->role === 'admin' && $attendance->user_id === $currentUser->id && !$currentUser->hasFullAccess()) {
            return $this->createCorrectionRequest($request, $attendance);
        }
        
        // 承認待ちの修正申請がある場合は編集不可（FN038）
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->first();
        
        if ($pendingRequest) {
            return back()->withErrors(['note' => '承認待ちのため修正はできません。'])->withInput();
        }
        
        DB::beginTransaction();
        
        try {
            // 出勤・退勤時刻の更新（FN040）
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== '' 
                ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_in))->format('H:i:s') 
                : $attendance->clock_in;
            
            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== '' 
                ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_out))->format('H:i:s') 
                : $attendance->clock_out;
            
            $attendance->update([
                'clock_in' => $correctedClockIn,
                'clock_out' => $correctedClockOut,
                'note' => trim($request->note),
                'last_modified_by' => $currentUser->id,
                'last_modified_at' => Carbon::now(),
            ]);
            
            // 休憩時間の更新（FN040）
            $breakTimes = $request->break_times ?? [];
            $existingBreakIds = [];
            
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
            
            // 削除された休憩時間を削除
            $attendance->breakTimes()->whereNotIn('id', $existingBreakIds)->delete();
            
            DB::commit();
            
            return redirect()->route('admin.show', $attendance->id)->with('success', '勤怠情報を修正しました');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors(['attendance' => '修正に失敗しました'])->withInput();
        }
    }

    /**
     * 管理者が自身の勤怠を修正する場合は申請として作成
     */
    private function createCorrectionRequest(CorrectionRequest $request, Attendance $attendance)
    {
        $currentUser = Auth::user();
        
        // 承認待ちの修正申請がある場合は編集不可
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->first();
        
        if ($pendingRequest) {
            return back()->withErrors(['note' => '承認待ちのため修正はできません。'])->withInput();
        }
        
        DB::beginTransaction();
        
        try {
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== '' ? trim($request->corrected_clock_in) : null;
            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== '' ? trim($request->corrected_clock_out) : null;
            
            // 修正申請を作成
            $stampRequest = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $currentUser->id,
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
                
                if (!$breakStart || !$breakEnd) {
                    continue;
                }
                
                $existingBreak = null;
                if (isset($break['id']) && !empty($break['id'])) {
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                }
                
                BreakCorrectionRequest::create([
                    'stamp_correction_request_id' => $stampRequest->id,
                    'break_time_id' => $existingBreak ? $existingBreak->id : null,
                    'original_break_start' => $existingBreak ? $existingBreak->break_start : null,
                    'original_break_end' => $existingBreak ? $existingBreak->break_end : null,
                    'corrected_break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                    'corrected_break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                ]);
            }
            
            DB::commit();
            
            return redirect()->route('admin.show', $attendance->id)->with('success', '修正申請を提出しました');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors(['attendance' => '修正申請の作成に失敗しました'])->withInput();
        }
    }

    public function staff()
    {
        $currentUser = Auth::user();
        
        // 全アクセス権限（department_code=1）の場合は全ユーザーを表示
        // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーを表示（自身も含む）
        $query = User::query();
        
        if ($currentUser->hasDepartmentAccess()) {
            // 同じ部門コードのユーザーを取得（自身も含む）
            $query->where('department_code', $currentUser->department_code);
        }
        // 全アクセス権限の場合はフィルタリングなし（全ユーザーを表示）
        
        // 氏名とメールアドレスを表示（FN041）
        $users = $query->select('id', 'name', 'email')
            ->orderBy('id', 'asc')
            ->get();
        
        return view('admin.staffList', [
            'users' => $users,
        ]);
    }

    public function list(Request $request, $id)
    {
        $currentUser = Auth::user();
        
        // 対象ユーザー取得
        $targetUser = User::findOrFail($id);
        
        // 権限チェック（FN043）
        if (!$currentUser->canViewAttendance($targetUser->id)) {
            abort(403, 'アクセスが拒否されました');
        }
        
        // 月パラメータから月を取得（デフォルトは当月）（FN044）
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $currentMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        
        // 指定ユーザーの指定月の勤怠情報取得（FN043）
        $attendances = Attendance::where('user_id', $targetUser->id)
            ->whereYear('date', $currentMonth->year)
            ->whereMonth('date', $currentMonth->month)
            ->with('breakTimes')
            ->orderBy('date', 'asc')
            ->get();
        
        // 前月・翌月の月（FN044）
        $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');
        
        // CSV出力機能（FN045）
        if ($request->get('download') === 'csv') {
            $filename = 'attendance_' . $targetUser->id . '_' . $currentMonth->format('Ymd') . '.csv';
            
            // CSVデータ生成
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
            
            // CSV出力（UTF-8 BOM付き）
            $output = fopen('php://temp', 'r+');
            
            // UTF-8 BOMを追加
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

