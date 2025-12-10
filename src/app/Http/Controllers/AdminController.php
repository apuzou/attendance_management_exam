<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Models\StampCorrectionRequest;
use App\Http\Requests\CorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
}

