<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}

