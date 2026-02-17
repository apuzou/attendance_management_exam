<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\User;
use App\Models\Attendance;
use App\Http\Requests\ApprovalRequest;
use App\Http\Requests\CorrectionRequest;
use App\Services\CorrectionApprovalService;
use App\Services\CorrectionRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 打刻修正申請コントローラ
 *
 * 修正申請の作成、一覧表示、承認画面表示、承認処理を行う。
 */
class StampCorrectionRequestController extends Controller
{
    /**
     * 修正申請を作成する。
     *
     * CorrectionRequestService に委譲する。主に AttendanceController と AdminController から直接
     * CorrectionRequestService が呼ばれるため、このメソッドは後方互換用。
     *
     * @param CorrectionRequest $request 修正申請リクエスト
     * @param int $id 勤怠ID
     * @return StampCorrectionRequest
     */
    public function store(CorrectionRequest $request, $id)
    {
        $attendance = Attendance::where('id', $id)
            ->with('breakTimes')
            ->firstOrFail();

        $correctionRequestService = app(CorrectionRequestService::class);

        return $correctionRequestService->create($attendance, Auth::user(), $request->all());
    }

    /**
     * 申請一覧画面を表示する。
     *
     * 管理者の場合は管轄する部門の申請を表示する。
     * 一般ユーザーの場合は自分の申請のみ表示する。
     *
     * @param Request $request HTTPリクエスト（tabパラメータ）
     * @return \Illuminate\View\View
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
     * 修正申請承認画面を表示する。
     *
     * @param int $attendanceCorrectRequestId 打刻修正申請ID
     * @return \Illuminate\View\View|\Illuminate\Http\Response
     */
    public function show($attendanceCorrectRequestId)
    {
        $correctionRequest = StampCorrectionRequest::where('id', $attendanceCorrectRequestId)
            ->with(['attendance.user', 'attendance.breakTimes', 'user', 'breakCorrectionRequests'])
            ->firstOrFail();

        $this->authorize('view', $correctionRequest);

        $currentUser = Auth::user();
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
     * 修正申請を承認する。
     *
     * @param ApprovalRequest $request 承認リクエスト
     * @param int $attendanceCorrectRequestId 打刻修正申請ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ApprovalRequest $request, $attendanceCorrectRequestId)
    {
        $correctionRequest = StampCorrectionRequest::where('id', $attendanceCorrectRequestId)
            ->with(['attendance.breakTimes', 'breakCorrectionRequests'])
            ->firstOrFail();

        try {
            $correctionApprovalService = app(CorrectionApprovalService::class);
            $correctionApprovalService->approve($correctionRequest, Auth::user());

            return redirect()->route('correction.index')->with('success', '修正申請を承認しました');
        } catch (\Exception $exception) {
            return back()->withErrors(['request' => '承認処理に失敗しました'])->withInput();
        }
    }
}

