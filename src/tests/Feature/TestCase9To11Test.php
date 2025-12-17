<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 9-11: 勤怠一覧情報取得機能、勤怠詳細情報取得機能、勤怠詳細情報修正機能（一般ユーザー）
 */
class TestCase9To11Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 9: 勤怠一覧情報取得機能（一般ユーザー）
     * テスト内容: 自分が行った勤怠情報が全て表示されている
     * テスト手順: 1. 勤怠情報が登録されたユーザーにログインする 2. 勤怠一覧ページを開く 3. 自分の勤怠情報がすべて表示されていることを確認する
     * 期待挙動: 自分の勤怠情報が全て表示されている
     */
    public function test_attendance_list_displays_all_user_attendances()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 複数の勤怠レコードを作成
        $attendance1 = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->subDays(2)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertViewHas('attendances');
        $response->assertSee($attendance1->date->format('Y年n月j日'));
        $response->assertSee($attendance2->date->format('Y年n月j日'));
    }

    /**
     * ID 9: 勤怠一覧情報取得機能（一般ユーザー）
     * テスト内容: 勤怠一覧画面に遷移した際に現在の月が表示される
     * テスト手順: 1. ユーザーにログインをする 2. 勤怠一覧ページを開く
     * 期待挙動: 現在の月が表示されている
     */
    public function test_attendance_list_displays_current_month_by_default()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertViewHas('currentMonth');
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals(now()->format('Y-m'), $currentMonth->format('Y-m'));
    }

    /**
     * ID 9: 勤怠一覧情報取得機能（一般ユーザー）
     * テスト内容: 「前月」を押下した時に表示月の前月の情報が表示される
     */
    public function test_attendance_list_displays_previous_month_when_prev_button_clicked()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $prevMonth = now()->subMonth()->format('Y-m');
        $response = $this->actingAs($user)->get('/attendance/list?month=' . $prevMonth);

        $response->assertStatus(200);
        $response->assertViewHas('currentMonth');
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals($prevMonth, $currentMonth->format('Y-m'));
    }

    /**
     * ID 9: 勤怠一覧情報取得機能（一般ユーザー）
     * テスト内容: 「翌月」を押下した時に表示月の翌月の情報が表示される
     */
    public function test_attendance_list_displays_next_month_when_next_button_clicked()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $nextMonth = now()->addMonth()->format('Y-m');
        $response = $this->actingAs($user)->get('/attendance/list?month=' . $nextMonth);

        $response->assertStatus(200);
        $response->assertViewHas('currentMonth');
        $currentMonth = $response->viewData('currentMonth');
        $this->assertEquals($nextMonth, $currentMonth->format('Y-m'));
    }

    /**
     * ID 9: 勤怠一覧情報取得機能（一般ユーザー）
     * テスト内容: 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_attendance_list_detail_button_navigates_to_detail_screen()
    {
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

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertViewIs('show');
        $response->assertViewHas('attendance');
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($attendance->id, $viewAttendance->id);
    }

    /**
     * ID 10: 勤怠詳細情報取得機能（一般ユーザー）
     * テスト内容: 勤怠詳細画面の「名前」がログインユーザーの氏名になっている
     */
    public function test_attendance_detail_displays_logged_in_user_name()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    /**
     * ID 10: 勤怠詳細情報取得機能（一般ユーザー）
     * テスト内容: 勤怠詳細画面の「日付」が選択した日付になっている
     */
    public function test_attendance_detail_displays_selected_date()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $date = now()->subDay()->toDateString();
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($date, $viewAttendance->date->toDateString());
    }

    /**
     * ID 10: 勤怠詳細情報取得機能（一般ユーザー）
     * テスト内容: 勤怠詳細画面の「出勤・退勤」がログインユーザーの記録と一致している
     */
    public function test_attendance_detail_displays_clock_in_out_times()
    {
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

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * ID 10: 勤怠詳細情報取得機能（一般ユーザー）
     * テスト内容: 勤怠詳細画面の「休憩」がログインユーザーの記録と一致している
     */
    public function test_attendance_detail_displays_break_times()
    {
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

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される
     */
    public function test_correction_validation_clock_in_after_clock_out_shows_error()
    {
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

        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'corrected_clock_in' => '19:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [],
        ]);

        $response->assertSessionHasErrors();
        // 出勤時間が退勤時間より後の場合、バリデーションエラーが発生することを確認
        $errors = session('errors');
        $this->assertNotNull($errors);
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 休憩開始時間が不適切な値の場合、エラーメッセージが表示される
     */
    public function test_correction_validation_break_start_invalid_shows_error()
    {
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

        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'corrected_clock_in' => '09:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [
                [
                    'break_start' => '08:00',
                    'break_end' => '13:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 休憩終了時間が不適切な値の場合、エラーメッセージが表示される
     */
    public function test_correction_validation_break_end_invalid_shows_error()
    {
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

        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'corrected_clock_in' => '09:00',
            'corrected_clock_out' => '18:00',
            'note' => 'テスト備考',
            'break_times' => [
                [
                    'break_start' => '12:00',
                    'break_end' => '19:00',
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 備考欄が未入力の場合、エラーメッセージが表示される
     */
    public function test_correction_validation_note_required_shows_error()
    {
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

        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
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
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 修正申請処理が正しく行われる
     */
    public function test_correction_request_processing_succeeds()
    {
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

        $response = $this->actingAs($user)->post('/attendance/detail/' . $attendance->id, [
            'corrected_clock_in' => '09:30',
            'corrected_clock_out' => '18:30',
            'note' => 'テスト備考',
            'break_times' => [],
        ]);

        $response->assertRedirect('/stamp_correction_request/list');

        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'note' => 'テスト備考',
        ]);
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 申請一覧に承認待ちの申請が表示される
     */
    public function test_correction_list_displays_pending_requests()
    {
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

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認待ち');
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 申請一覧に承認済みの申請が表示される
     */
    public function test_correction_list_displays_approved_requests()
    {
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
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済み');
    }

    /**
     * ID 11: 勤怠詳細情報修正機能（一般ユーザー）
     * テスト内容: 申請一覧の各申請の「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_correction_list_detail_button_navigates_to_attendance_detail_screen()
    {
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

        // 一般ユーザーの場合は詳細リンクは勤怠詳細画面に遷移する
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertViewIs('show');
        $viewAttendance = $response->viewData('attendance');
        $this->assertEquals($attendance->id, $viewAttendance->id);
    }
}

