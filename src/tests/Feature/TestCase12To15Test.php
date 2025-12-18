<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 12-15: 勤怠一覧情報取得機能、勤怠詳細情報取得・修正機能、ユーザー情報取得機能、勤怠情報修正機能（管理者）
 */
class TestCase12To15Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 12: 勤怠一覧情報取得機能（管理者）
     * テスト内容: その日になされた全ユーザーの勤怠情報が正確に確認できる
     */
    public function test_admin_attendance_list_displays_all_users_attendance_for_date()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user1 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $date = now()->toDateString();

        Attendance::create([
            'user_id' => $user1->id,
            'date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::create([
            'user_id' => $user2->id,
            'date' => $date,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $date);

        $response->assertStatus(200);
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
    }

    /**
     * ID 12: 勤怠一覧情報取得機能（管理者）
     * テスト内容: 遷移した際に現在の日付が表示される
     */
    public function test_admin_attendance_list_displays_current_date_by_default()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertViewHas('currentDate');
        $currentDate = $response->viewData('currentDate');
        $this->assertEquals(now()->toDateString(), $currentDate->toDateString());
    }

    /**
     * ID 12: 勤怠一覧情報取得機能（管理者）
     * テスト内容: 「前日」を押下した時に前の日の勤怠情報が表示される
     */
    public function test_admin_attendance_list_displays_previous_day_when_prev_button_clicked()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $prevDate = now()->subDay()->format('Y-m-d');
        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $prevDate);

        $response->assertStatus(200);
        $response->assertViewHas('currentDate');
        $currentDate = $response->viewData('currentDate');
        $this->assertEquals($prevDate, $currentDate->toDateString());
    }

    /**
     * ID 12: 勤怠一覧情報取得機能（管理者）
     * テスト内容: 「翌日」を押下した時に次の日の勤怠情報が表示される
     */
    public function test_admin_attendance_list_displays_next_day_when_next_button_clicked()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $nextDate = now()->addDay()->format('Y-m-d');
        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $nextDate);

        $response->assertStatus(200);
        $response->assertViewHas('currentDate');
        $currentDate = $response->viewData('currentDate');
        $this->assertEquals($nextDate, $currentDate->toDateString());
    }

    /**
     * ID 13: 勤怠詳細情報取得・修正機能（管理者）
     * テスト内容: 勤怠詳細画面に表示されるデータが選択したものになっている
     */
    public function test_admin_attendance_detail_displays_selected_attendance_data()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertViewIs('admin.dailyShow');
        $response->assertViewHas('attendance');
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($attendance->id, $viewAttendance->id);
    }

    /**
     * ID 13: 勤怠詳細情報取得・修正機能（管理者）
     * テスト内容: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_correction_validation_clock_in_after_clock_out_shows_error()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/admin/attendance/' . $attendance->id, [
            'corrected_clock_in' => '19:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [],
        ]);

        $response->assertSessionHasErrors(['corrected_clock_in']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('corrected_clock_in') === '出勤時間もしくは退勤時間が不適切な値です';
        });
    }

    /**
     * ID 13: 勤怠詳細情報取得・修正機能（管理者）
     * テスト内容: 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_correction_validation_break_start_after_clock_out_shows_error()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/admin/attendance/' . $attendance->id, [
            'corrected_clock_in' => '09:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [
                [
                    'break_start' => '19:00',
                    'break_end' => '20:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('break_times.0.break_start');
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('break_times.0.break_start') === '休憩時間が不適切な値です';
        });
    }

    /**
     * ID 13: 勤怠詳細情報取得・修正機能（管理者）
     * テスト内容: 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_admin_correction_validation_break_end_after_clock_out_shows_error()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/admin/attendance/' . $attendance->id, [
            'corrected_clock_in' => '09:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [
                [
                    'break_start' => '17:00',
                    'break_end' => '19:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('break_times.0.break_end');
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('break_times.0.break_end') === '休憩時間もしくは退勤時間が不適切な値です';
        });
    }

    /**
     * ID 13: 勤怠詳細情報取得・修正機能（管理者）
     * テスト内容: 備考欄が未入力の場合のエラーメッセージが表示される
     */
    public function test_admin_correction_validation_note_required_shows_error()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/admin/attendance/' . $attendance->id, [
            'corrected_clock_in' => '09:00',
            'corrected_clock_out' => '18:00',
            'note' => '',
            'break_times' => [],
        ]);

        $response->assertSessionHasErrors(['note']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('note') === '備考を記入してください';
        });
    }

    /**
     * ID 14: ユーザー情報取得機能（管理者）
     * テスト内容: 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる
     */
    public function test_admin_staff_list_displays_all_general_users_name_and_email()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user1 = User::factory()->create([
            'name' => 'テストユーザー1',
            'email' => 'user1@example.com',
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'name' => 'テストユーザー2',
            'email' => 'user2@example.com',
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/staff/list');

        $response->assertStatus(200);
        $response->assertSee($user1->name);
        $response->assertSee($user1->email);
        $response->assertSee($user2->name);
        $response->assertSee($user2->email);
    }

    /**
     * ID 14: ユーザー情報取得機能（管理者）
     * テスト内容: ユーザーの勤怠情報が正しく表示される
     */
    public function test_admin_staff_attendance_list_displays_correct_attendance_info()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id);

        $response->assertStatus(200);
        $response->assertViewHas('attendances');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * ID 14: ユーザー情報取得機能（管理者）
     * テスト内容: 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_admin_staff_attendance_list_displays_previous_month_when_prev_button_clicked()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $prevMonth = now()->subMonth()->format('Y-m');
        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?month=' . $prevMonth);

        $response->assertStatus(200);
        $response->assertViewHas('currentMonth');
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals($prevMonth, $currentMonth->format('Y-m'));
    }

    /**
     * ID 14: ユーザー情報取得機能（管理者）
     * テスト内容: 「翌月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_admin_staff_attendance_list_displays_next_month_when_next_button_clicked()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $nextMonth = now()->addMonth()->format('Y-m');
        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?month=' . $nextMonth);

        $response->assertStatus(200);
        $response->assertViewHas('currentMonth');
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals($nextMonth, $currentMonth->format('Y-m'));
    }

    /**
     * ID 14: ユーザー情報取得機能（管理者）
     * テスト内容: 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_admin_staff_attendance_list_detail_button_navigates_to_detail_screen()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertViewIs('admin.dailyShow');
        $response->assertViewHas('attendance');
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($attendance->id, $viewAttendance->id);
    }

    /**
     * ID 15: 勤怠情報修正機能（管理者）
     * テスト内容: 承認待ちの修正申請が全て表示されている
     */
    public function test_admin_correction_list_displays_all_pending_requests()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user1 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user1->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user2->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_id' => $attendance1->id,
            'user_id' => $user1->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考1',
            'approved_at' => null,
        ]);

        StampCorrectionRequest::create([
            'attendance_id' => $attendance2->id,
            'user_id' => $user2->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考2',
            'approved_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list?tab=pending');

        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
    }

    /**
     * ID 15: 勤怠情報修正機能（管理者）
     * テスト内容: 承認済みの修正申請が全て表示されている
     */
    public function test_admin_correction_list_displays_all_approved_requests()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user1 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $user2 = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user1->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user2->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        StampCorrectionRequest::create([
            'attendance_id' => $attendance1->id,
            'user_id' => $user1->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考1',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        StampCorrectionRequest::create([
            'attendance_id' => $attendance2->id,
            'user_id' => $user2->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考2',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list?tab=approved');

        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee($user1->name);
        $response->assertSee($user2->name);
    }

    /**
     * ID 15: 勤怠情報修正機能（管理者）
     * テスト内容: 修正申請の詳細内容が正しく表示されている
     */
    public function test_admin_correction_detail_displays_correct_request_content()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $correctionRequest = StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考',
            'approved_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/' . $correctionRequest->id);

        $response->assertStatus(200);
        $response->assertViewIs('admin.approval');
        $response->assertSee('09:30');
        $response->assertSee('18:30');
        $response->assertSee('テスト備考');
    }

    /**
     * ID 15: 勤怠情報修正機能（管理者）
     * テスト内容: 修正申請の承認処理が正しく行われる
     */
    public function test_admin_correction_approval_processing_succeeds()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'department_code' => 1,
            'email_verified_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $correctionRequest = StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'request_date' => now()->toDateString(),
            'original_clock_in' => '09:00:00',
            'original_clock_out' => '18:00:00',
            'corrected_clock_in' => '09:30:00',
            'corrected_clock_out' => '18:30:00',
            'note' => 'テスト備考',
            'approved_at' => null,
        ]);

        $response = $this->actingAs($admin)->post('/stamp_correction_request/approve/' . $correctionRequest->id);

        $response->assertRedirect('/stamp_correction_request/list');

        $correctionRequest->refresh();
        $this->assertNotNull($correctionRequest->approved_at);

        $attendance->refresh();
        $this->assertEquals('09:30:00', $attendance->clock_in);
        $this->assertEquals('18:30:00', $attendance->clock_out);
    }
}

