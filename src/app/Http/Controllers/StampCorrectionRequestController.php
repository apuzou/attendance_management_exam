<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use App\Models\User;
use App\Models\BreakTime;
use App\Http\Requests\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller
{
    /**
     * 申請一覧画面を表示
     * 管理者の場合は管轄する部門の申請を表示
     * 一般ユーザーの場合は自分の申請のみ表示
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->get('tab', 'pending'); // タブ選択状態（承認待ち/承認済み）
        
        // 管理者の場合は管轄する部門の申請を表示
        if ($user->role === 'admin') {
            $query = StampCorrectionRequest::query();
            
            // 全アクセス権限（department_code=1）の場合は全ユーザーの申請を表示
            // 部門アクセス権限（department_code!=1）の場合は同じ部門のメンバーの申請を表示（自身も含む）
            if ($user->hasDepartmentAccess()) {
                $sameDepartmentUserIds = User::where('department_code', $user->department_code)
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('user_id', $sameDepartmentUserIds);
            }
            // 全アクセス権限の場合はフィルタリングなし（全ユーザーの申請を表示）
            
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
        
        // 一般ユーザーの場合は自分の申請のみ（FN006, FN007）
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
     * 修正申請承認画面を表示（FN050）
     * 申請詳細情報を表示し、承認可否を判定
     */
    public function show($id)
    {
        $currentUser = Auth::user();
        
        // 修正申請レコード取得（関連する勤怠情報、申請者情報、休憩修正申請も一緒に取得）
        $correctionRequest = StampCorrectionRequest::where('id', $id)
            ->with(['attendance.user', 'attendance.breakTimes', 'user', 'breakCorrectionRequests'])
            ->firstOrFail();
        
        // 権限チェック（管理者のみ承認画面にアクセス可能）
        if ($currentUser->role !== 'admin') {
            abort(403, 'アクセスが拒否されました');
        }
        
        // 管理者が管轄する部門の申請のみ閲覧可能
        if (!$currentUser->canViewAttendance($correctionRequest->user_id)) {
            abort(403, 'アクセスが拒否されました');
        }
        
        // 自身の承認が可能かチェック（アクセスしているユーザーIDが現在のユーザーIDと同じ場合は承認不可）
        $canApprove = $correctionRequest->user_id !== $currentUser->id;
        
        // 既に承認済みかチェック
        $isApproved = !is_null($correctionRequest->approved_at);
        
        return view('admin.approval', [
            'request' => $correctionRequest,
            'attendance' => $correctionRequest->attendance,
            'user' => $correctionRequest->user,
            'canApprove' => $canApprove && !$isApproved, // 自身の申請でない、かつ未承認の場合のみ承認可能
            'isApproved' => $isApproved,
        ]);
    }

    /**
     * 修正申請を承認
     * 申請内容を勤怠レコードに反映し、承認状態を更新
     * 認可チェックとバリデーションはApprovalRequestで行われる
     */
    public function update(ApprovalRequest $request, $id)
    {
        $currentUser = Auth::user();
        
        // 修正申請レコード取得（関連する勤怠情報、休憩修正申請も一緒に取得）
        $correctionRequest = StampCorrectionRequest::where('id', $id)
            ->with(['attendance.breakTimes', 'breakCorrectionRequests'])
            ->firstOrFail();
        
        DB::beginTransaction();
        
        try {
            // 勤怠レコードの更新
            $attendance = $correctionRequest->attendance;
            
            // 出勤・退勤時刻の更新（修正申請に記載がある場合のみ更新）
            if ($correctionRequest->corrected_clock_in) {
                $attendance->clock_in = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_in)->format('H:i:s');
            }
            if ($correctionRequest->corrected_clock_out) {
                $attendance->clock_out = Carbon::createFromFormat('H:i:s', $correctionRequest->corrected_clock_out)->format('H:i:s');
            }
            
            // 備考の更新（修正申請に記載がある場合のみ更新）
            if ($correctionRequest->note) {
                $attendance->note = $correctionRequest->note;
            }
            
            // 最終更新者・最終更新日時を記録
            $attendance->last_modified_by = $currentUser->id;
            $attendance->last_modified_at = Carbon::now();
            $attendance->save();
            
            // 休憩時間の更新
            $existingBreakIds = []; // 更新・作成された休憩時間のIDを保持
            foreach ($correctionRequest->breakCorrectionRequests as $breakCorrectionRequest) {
                if ($breakCorrectionRequest->break_time_id) {
                    // 既存の休憩時間を更新
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
            
            // 修正申請にない既存の休憩時間は削除しない（修正申請で指定されていないものはそのまま残す）
            // これは要件による。もし削除が必要な場合は、修正申請に削除フラグを追加する必要がある
            
            // 修正申請の承認状態を更新（承認者・承認日時を記録）
            $correctionRequest->approved_by = $currentUser->id;
            $correctionRequest->approved_at = Carbon::now();
            $correctionRequest->save();
            
            DB::commit();
            
            return redirect()->route('correction.index')->with('success', '修正申請を承認しました');
        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors(['request' => '承認処理に失敗しました'])->withInput();
        }
    }
}

