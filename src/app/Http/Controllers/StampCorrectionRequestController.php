<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\User;
use App\Models\BreakTime;
use App\Models\Attendance;
use App\Models\BreakCorrectionRequest;
use App\Http\Requests\ApprovalRequest;
use App\Http\Requests\CorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller
{
    /**
     * 修正申請を作成
     */
    public function store(CorrectionRequest $request, $id)
    {
        $currentUser = Auth::user();

        $attendance = Attendance::where('id', $id)
            ->with('breakTimes')
            ->firstOrFail();

        DB::beginTransaction();

        try {
            $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== '' ? trim($request->corrected_clock_in) : null;
            $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== '' ? trim($request->corrected_clock_out) : null;

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

            $breakTimes = $request->break_times ?? [];

            foreach ($breakTimes as $break) {
                $breakStart = isset($break['break_start']) && trim($break['break_start']) !== '' ? trim($break['break_start']) : null;
                $breakEnd = isset($break['break_end']) && trim($break['break_end']) !== '' ? trim($break['break_end']) : null;

                if ($breakStart === null || $breakEnd === null) {
                    continue;
                }

                // 既存の休憩時間かどうかを判定
                $existingBreak = null;
                if (isset($break['id']) && $break['id'] !== '') {
                    $existingBreak = $attendance->breakTimes->where('id', $break['id'])->first();
                }

                BreakCorrectionRequest::create([
                    'stamp_correction_request_id' => $stampRequest->id,
                    'break_time_id' => $existingBreak ? $existingBreak->id : null, // nullの場合は新規追加
                    'original_break_start' => $existingBreak ? $existingBreak->break_start : null,
                    'original_break_end' => $existingBreak ? $existingBreak->break_end : null,
                    'corrected_break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                    'corrected_break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
                ]);
            }

            DB::commit();

            Log::info('修正申請が正常に作成されました', [
                'user_id' => $currentUser->id,
                'attendance_id' => $id,
                'stamp_request_id' => $stampRequest->id,
            ]);

            // リダイレクト先は呼び出し元で処理（戻り値はStampCorrectionRequestのみ）
            return $stampRequest;
        } catch (\Exception $exception) {
            DB::rollBack();

            Log::error('修正申請の作成に失敗しました', [
                'user_id' => $currentUser->id,
                'attendance_id' => $id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            throw $exception;
        }
    }

    /**
     * 申請一覧画面を表示
     * 管理者の場合は管轄する部門の申請を表示
     * 一般ユーザーの場合は自分の申請のみ表示
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->get('tab', 'pending');

        if ($user->role === 'admin') {
            $query = StampCorrectionRequest::query();

            // 全アクセス権限（department_code=1）の場合は全ユーザーの申請を表示
            // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーの申請を表示
            if ($user->hasDepartmentAccess()) {
                $sameDepartmentUserIds = User::where('department_code', $user->department_code)
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('user_id', $sameDepartmentUserIds);
            }

            $pendingRequests = (clone $query)
                ->whereNull('approved_at')
                ->with(['attendance', 'user'])
                ->orderBy('request_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $approvedRequests = (clone $query)
                ->whereNotNull('approved_at')
                ->with(['attendance', 'user', 'approvedBy'])
                ->orderBy('approved_at', 'desc')
                ->orderBy('request_date', 'desc')
                ->get();

            return view('stampCorrectionRequest', [
                'pendingRequests' => $pendingRequests,
                'approvedRequests' => $approvedRequests,
                'isAdmin' => true,
                'tab' => $tab,
            ]);
        }

        $pendingRequests = StampCorrectionRequest::where('user_id', $user->id)
            ->whereNull('approved_at')
            ->with(['attendance', 'user'])
            ->orderBy('request_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedRequests = StampCorrectionRequest::where('user_id', $user->id)
            ->whereNotNull('approved_at')
            ->with(['attendance', 'user', 'approvedBy'])
            ->orderBy('approved_at', 'desc')
            ->orderBy('request_date', 'desc')
            ->get();

        return view('stampCorrectionRequest', [
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'isAdmin' => false,
            'tab' => $tab,
        ]);
    }

    /**
     * 修正申請承認画面を表示
     */
    public function show($attendanceCorrectRequestId)
    {
        $currentUser = Auth::user();

        $correctionRequest = StampCorrectionRequest::where('id', $attendanceCorrectRequestId)
            ->with(['attendance.user', 'attendance.breakTimes', 'user', 'breakCorrectionRequests'])
            ->firstOrFail();

        if ($currentUser->role !== 'admin') {
            abort(403, 'アクセスが拒否されました');
        }

        if ($currentUser->canViewAttendance($correctionRequest->user_id) === false) {
            abort(403, 'アクセスが拒否されました');
        }

        $canApprove = $correctionRequest->user_id !== $currentUser->id;

        $isApproved = $correctionRequest->approved_at !== null;

        return view('admin.approval', [
            'request' => $correctionRequest,
            'attendance' => $correctionRequest->attendance,
            'user' => $correctionRequest->user,
            'canApprove' => $canApprove && $isApproved === false, // 管理者自身の申請でない、かつ未承認の場合のみ承認可能
            'isApproved' => $isApproved,
        ]);
    }

    /**
     * 修正申請を承認
     */
    public function update(ApprovalRequest $request, $attendanceCorrectRequestId)
    {
        $currentUser = Auth::user();

        $correctionRequest = StampCorrectionRequest::where('id', $attendanceCorrectRequestId)
            ->with(['attendance.breakTimes', 'breakCorrectionRequests'])
            ->firstOrFail();

        DB::beginTransaction();

        try {
            $attendance = $correctionRequest->attendance;

            if ($correctionRequest->corrected_clock_in) {
                $attendance->clock_in = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_in)->format('H:i:s');
            }
            if ($correctionRequest->corrected_clock_out) {
                $attendance->clock_out = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_out)->format('H:i:s');
            }
            if ($correctionRequest->note) {
                $attendance->note = $correctionRequest->note;
            }
            $attendance->last_modified_by = $currentUser->id;
            $attendance->last_modified_at = Carbon::now();
            $attendance->save();

            $existingBreakIds = [];
            foreach ($correctionRequest->breakCorrectionRequests as $breakCorrectionRequest) {
                if ($breakCorrectionRequest->break_time_id) {
                    $breakTime = $attendance->breakTimes->where('id', $breakCorrectionRequest->break_time_id)->first();
                    if ($breakTime) {
                        $breakTime->break_start = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s');
                        $breakTime->break_end = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s');
                        $breakTime->save();
                        $existingBreakIds[] = $breakTime->id;
                    }
                } else {
                    // 新規休憩時間を作成
                    $newBreakTime = BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s'),
                        'break_end' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s'),
                    ]);
                    $existingBreakIds[] = $newBreakTime->id;
                }
            }

            $correctionRequest->approved_by = $currentUser->id;
            $correctionRequest->approved_at = Carbon::now();
            $correctionRequest->save();

            DB::commit();

            return redirect()->route('correction.index')->with('success', '修正申請を承認しました');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()->withErrors(['request' => '承認処理に失敗しました'])->withInput();
        }
    }
}

