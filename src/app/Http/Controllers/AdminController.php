<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
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
        // 日付パラメータから日付を取得（デフォルトは今日）
        $dateParam = $request->get('date', Carbon::today()->format('Y-m-d'));
        $currentDate = Carbon::parse($dateParam)->startOfDay();
        
        // 該当日に出勤を打刻した勤怠情報のみを取得
        $attendances = Attendance::where('date', $currentDate->toDateString())
            ->whereNotNull('clock_in')
            ->with(['user', 'breakTimes'])
            ->orderBy('user_id', 'asc')
            ->get();
        
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

