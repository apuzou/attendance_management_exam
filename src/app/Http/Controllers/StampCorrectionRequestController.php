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
use Illuminate\Contracts\Auth\Authenticatable;
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
            $stampRequest = $this->createStampCorrectionRequest($request, $attendance, $currentUser);
            $this->createBreakCorrectionRequests($request, $attendance, $stampRequest);

            DB::commit();

            Log::info('修正申請が正常に作成されました', [
                'user_id' => $currentUser->id,
                'attendance_id' => $id,
                'stamp_request_id' => $stampRequest->id,
            ]);

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
     * 打刻修正申請を作成
     */
    private function createStampCorrectionRequest(
        CorrectionRequest $request,
        Attendance $attendance,
        Authenticatable $currentUser
    ): StampCorrectionRequest {
        $correctedClockIn = $request->filled('corrected_clock_in') && trim($request->corrected_clock_in) !== ''
            ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_in))->format('H:i:s')
            : null;
        $correctedClockOut = $request->filled('corrected_clock_out') && trim($request->corrected_clock_out) !== ''
            ? Carbon::createFromFormat('H:i', trim($request->corrected_clock_out))->format('H:i:s')
            : null;

        $stampRequest = new StampCorrectionRequest([
            'corrected_clock_in' => $correctedClockIn,
            'corrected_clock_out' => $correctedClockOut,
            'note' => trim($request->note),
        ]);
        $stampRequest->attendance_id = $attendance->id;
        $stampRequest->user_id = $currentUser->id;
        $stampRequest->request_date = Carbon::today()->toDateString();
        $stampRequest->original_clock_in = $attendance->clock_in;
        $stampRequest->original_clock_out = $attendance->clock_out;
        $stampRequest->save();

        return $stampRequest;
    }

    /**
     * 休憩時間修正申請を作成
     */
    private function createBreakCorrectionRequests(
        CorrectionRequest $request,
        Attendance $attendance,
        StampCorrectionRequest $stampRequest
    ): void {
        $breakTimes = $request->break_times ?? [];

        foreach ($breakTimes as $break) {
            $breakStart = isset($break['break_start']) && trim($break['break_start']) !== ''
                ? trim($break['break_start'])
                : null;
            $breakEnd = isset($break['break_end']) && trim($break['break_end']) !== ''
                ? trim($break['break_end'])
                : null;

            if ($breakStart === null || $breakEnd === null) {
                continue;
            }

            $breakId = isset($break['id']) && $break['id'] !== '' ? (int)$break['id'] : null;
            $existingBreak = $breakId !== null ? $attendance->findBreakById($breakId) : null;

            $breakCorrectionRequest = new BreakCorrectionRequest([
                'corrected_break_start' => Carbon::createFromFormat('H:i', $breakStart)->format('H:i:s'),
                'corrected_break_end' => Carbon::createFromFormat('H:i', $breakEnd)->format('H:i:s'),
            ]);
            $breakCorrectionRequest->stamp_correction_request_id = $stampRequest->id;
            $breakCorrectionRequest->break_time_id = $existingBreak ? $existingBreak->id : null;
            $breakCorrectionRequest->original_break_start = $existingBreak ? $existingBreak->break_start : null;
            $breakCorrectionRequest->original_break_end = $existingBreak ? $existingBreak->break_end : null;
            $breakCorrectionRequest->save();
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

        // buildQueryForUser()はUser型を要求するため、型アサーションを追加
        /** @var User $user */
        $query = StampCorrectionRequest::forUser($user);

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
            'isAdmin' => $user->role === 'admin',
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

        // Undefined method 'canViewAttendance'. エラーの修正
        if ($currentUser instanceof User && $currentUser->role !== 'admin') {
            abort(403, 'アクセスが拒否されました');
        }

        if ($currentUser instanceof User && $currentUser->canViewAttendance($correctionRequest->user_id) === false) {
            abort(403, 'アクセスが拒否されました');
        }

        $canApprove = $correctionRequest->user_id !== $currentUser->id;
        $isApproved = $correctionRequest->approved_at !== null;

        return view('admin.approval', [
            'request' => $correctionRequest,
            'attendance' => $correctionRequest->attendance,
            'user' => $correctionRequest->user,
            'canApprove' => $canApprove && !$isApproved,
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
            $this->updateAttendance($correctionRequest, $currentUser);
            $this->updateBreakTimes($correctionRequest);
            $this->approveCorrectionRequest($correctionRequest, $currentUser);

            DB::commit();

            return redirect()->route('correction.index')->with('success', '修正申請を承認しました');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()->withErrors(['request' => '承認処理に失敗しました'])->withInput();
        }
    }

    /**
     * 勤怠情報を更新
     */
    private function updateAttendance(StampCorrectionRequest $correctionRequest, Authenticatable $currentUser): void
    {
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
    }

    /**
     * 休憩時間を更新
     */
    private function updateBreakTimes(StampCorrectionRequest $correctionRequest): void
    {
        $attendance = $correctionRequest->attendance;

        foreach ($correctionRequest->breakCorrectionRequests as $breakCorrectionRequest) {
            if ($breakCorrectionRequest->break_time_id) {
                $breakTime = $attendance->breakTimes->where('id', $breakCorrectionRequest->break_time_id)->first();
                if ($breakTime) {
                    $breakTime->break_start = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s');
                    $breakTime->break_end = Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s');
                    $breakTime->save();
                }
            } else {
                // 新規休憩時間を作成
                $newBreakTime = new BreakTime([
                    'break_start' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_start)->format('H:i:s'),
                    'break_end' => Carbon::createFromFormat('H:i:s', $breakCorrectionRequest->corrected_break_end)->format('H:i:s'),
                ]);
                $newBreakTime->attendance_id = $attendance->id;
                $newBreakTime->save();
            }
        }
    }

    /**
     * 修正申請を承認
     */
    private function approveCorrectionRequest(StampCorrectionRequest $correctionRequest, Authenticatable $currentUser): void
    {
        $correctionRequest->approved_by = $currentUser->id;
        $correctionRequest->approved_at = Carbon::now();
        $correctionRequest->save();
    }
}
