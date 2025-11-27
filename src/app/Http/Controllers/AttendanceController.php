<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
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
            
            switch ($request->stamp_type) {
                case 'clock_in':
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
                    $activeBreak = BreakTime::where('attendance_id', $attendance->id)
                        ->whereNotNull('break_start')
                        ->whereNull('break_end')
                        ->first();
                    
                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start' => $now->format('H:i:s'),
                    ]);
                    break;
                    
                case 'break_end':
                    $activeBreak = BreakTime::where('attendance_id', $attendance->id)
                        ->whereNotNull('break_start')
                        ->whereNull('break_end')
                        ->first();
                    
                    $activeBreak->update([
                        'break_end' => $now->format('H:i:s'),
                    ]);
                    break;
                    
                case 'clock_out':
                    $attendance->update([
                        'clock_out' => $now->format('H:i:s'),
                    ]);
                    break;
            }
            
            DB::commit();
            
            return redirect()->route('attendance.index')->with('success', '打刻が完了しました');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['stamp_type' => '打刻処理に失敗しました']);
        }
    }

    private function getStatus($attendance): string
    {
        if (!$attendance || !$attendance->clock_in) {
            return '勤務外';
        }
        
        if ($attendance->clock_out) {
            return '退勤済';
        }
        
        $activeBreak = BreakTime::where('attendance_id', $attendance->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();
        
        if ($activeBreak) {
            return '休憩中';
        }
        
        return '出勤中';
    }
}

