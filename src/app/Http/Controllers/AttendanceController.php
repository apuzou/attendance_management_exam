<?php

namespace App\Http\Controllers;

use App\Http\Requests\StampRequest;
use App\Http\Requests\CorrectionRequest;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\BreakCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function show($id)
    {
        $user = Auth::user();
        
        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['breakTimes', 'stampCorrectionRequests'])
            ->firstOrFail();
        
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->first();
        
        $canEdit = is_null($pendingRequest);
        
        return view('show', [
            'attendance' => $attendance,
            'canEdit' => $canEdit,
            'pendingRequest' => $pendingRequest,
        ]);
    }

    public function update(CorrectionRequest $request, $id)
    {
        $user = Auth::user();
        
        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->with('breakTimes')
            ->firstOrFail();
        
        $pendingRequest = StampCorrectionRequest::where('attendance_id', $attendance->id)
            ->whereNull('approved_at')
            ->first();
        
        if ($pendingRequest) {
            return back()->withErrors(['note' => '承認待ちのため修正はできません。']);
        }
        
        DB::beginTransaction();
        
        try {
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== '' ? trim($request->corrected_clock_in) : null;
            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== '' ? trim($request->corrected_clock_out) : null;
            
            $stampRequest = StampCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'request_date' => Carbon::today()->toDateString(),
                'original_clock_in' => $attendance->clock_in,
                'original_clock_out' => $attendance->clock_out,
                'corrected_clock_in' => $correctedClockIn ? Carbon::createFromFormat('H:i', $correctedClockIn)->format('H:i:s') : null,
                'corrected_clock_out' => $correctedClockOut ? Carbon::createFromFormat('H:i', $correctedClockOut)->format('H:i:s') : null,
                'note' => trim($request->note),
            ]);
            
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
            
            Log::info('修正申請が正常に作成されました', [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'stamp_request_id' => $stampRequest->id,
            ]);
            
            return redirect()->route('correction.index')->with('success', '修正申請を送信しました。');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('修正申請の作成に失敗しました', [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            return back()->withErrors(['note' => '修正申請の作成に失敗しました。エラー: ' . $e->getMessage()])->withInput();
        }
    }
}

