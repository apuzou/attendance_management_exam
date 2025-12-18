<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * テストケースID 4-8: 日時取得機能、ステータス確認機能、出勤機能、休憩機能、退勤機能
 */
class TestCase4To8Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 4: 日時取得機能
     * テスト内容: 現在の日時情報がUIと同じ形式で出力されている
     */
    public function test_attendance_screen_displays_current_datetime()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewIs('home');
        $response->assertViewHas('currentTime');

        $viewData = $response->viewData('currentTime');
        $this->assertInstanceOf(\Carbon\Carbon::class, $viewData);

        // 現在時刻と1分以内の差であることを確認
        $now = \Carbon\Carbon::now();
        $this->assertLessThanOrEqual(60, abs($now->diffInSeconds($viewData)));
    }

    /**
     * ID 5: ステータス確認機能
     * テスト内容: ステータスが勤務外のユーザーでログインした場合、ステータスが「勤務外」と表示される
     */
    public function test_status_displays_off_duty_when_no_attendance()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewHas('status', '勤務外');
        $response->assertSee('勤務外');
    }

    /**
     * ID 5: ステータス確認機能
     * テスト内容: ステータスが出勤中のユーザーでログインした場合、ステータスが「出勤中」と表示される
     */
    public function test_status_displays_on_duty_when_clocked_in()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewHas('status', '出勤中');
        $response->assertSee('出勤中');
    }

    /**
     * ID 5: ステータス確認機能
     * テスト内容: ステータスが休憩中のユーザーでログインした場合、ステータスが「休憩中」と表示される
     */
    public function test_status_displays_on_break_when_on_break()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_start' => now()->subMinutes(30)->format('H:i:s'),
            'break_end' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewHas('status', '休憩中');
        $response->assertSee('休憩中');
    }

    /**
     * ID 5: ステータス確認機能
     * テスト内容: ステータスが退勤済のユーザーでログインした場合、ステータスが「退勤済」と表示される
     */
    public function test_status_displays_left_work_when_clocked_out()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(9)->format('H:i:s'),
            'clock_out' => now()->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewHas('status', '退勤済');
        $response->assertSee('退勤済');
    }

    /**
     * ID 6: 出勤機能
     * テスト内容: 出勤ボタンが正しく機能する
     */
    public function test_clock_in_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance->clock_in);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '出勤中');
        $response->assertSee('出勤中');
    }

    /**
     * ID 6: 出勤機能
     * テスト内容: 出勤は一日一回のみできる
     */
    public function test_clock_in_can_only_be_done_once_per_day()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(9)->format('H:i:s'),
            'clock_out' => now()->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertDontSee('出勤', false);
    }

    /**
     * ID 6: 出勤機能
     * テスト内容: 出勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_in_time_displayed_on_attendance_list()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $clockInTime = $attendance->clock_in;

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee(date('H:i', strtotime($clockInTime)));
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩ボタンが正しく機能する
     */
    public function test_break_start_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');

        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $response->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $breakTime = BreakTime::where('attendance_id', $attendance->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();

        $this->assertNotNull($breakTime);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '休憩中');
        $response->assertSee('休憩中');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩は一日に何回でもできる
     */
    public function test_break_can_be_taken_multiple_times_per_day()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(2)->format('H:i:s'),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩戻ボタンが正しく機能する
     */
    public function test_break_end_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');

        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $response->assertRedirect('/attendance');

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '出勤中');
        $response->assertSee('出勤中');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩戻は一日に何回でもできる
     */
    public function test_break_end_can_be_done_multiple_times_per_day()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(2)->format('H:i:s'),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩時刻が勤怠一覧画面で確認できる
     */
    public function test_break_time_displayed_on_attendance_list()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(2)->format('H:i:s'),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $breakTime = BreakTime::where('attendance_id', $attendance->id)->first();

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($attendance->getTotalBreakTime());
    }

    /**
     * ID 8: 退勤機能
     * テスト内容: 退勤ボタンが正しく機能する
     */
    public function test_clock_out_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');

        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_out',
        ]);

        $response->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance->clock_out);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '退勤済');
        $response->assertSee('退勤済');
    }

    /**
     * ID 8: 退勤機能
     * テスト内容: 退勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_out_time_displayed_on_attendance_list()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_out',
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $clockOutTime = $attendance->clock_out;

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee(date('H:i', strtotime($clockOutTime)));
    }
}

