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
     * テスト手順: 1. 勤怠打刻画面を開く 2. 画面に表示されている日時情報を確認する
     * 期待挙動: 画面上に表示されている日時が現在の日時と一致する
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
     * テスト手順: 1. ステータスが勤務外のユーザーにログインする 2. 画面に「出勤」ボタンが表示されていることを確認する 3. 出勤の処理を行う
     * 期待挙動: 画面上に「出勤」ボタンが表示され、処理後に画面上に表示されるステータスが「出勤中」になる
     */
    public function test_clock_in_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 出勤ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        // 出勤処理を実行
        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        // データベースに出勤記録が保存されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance->clock_in);

        // 処理後のステータスが「出勤中」になることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '出勤中');
        $response->assertSee('出勤中');
    }

    /**
     * ID 6: 出勤機能
     * テスト内容: 出勤は一日一回のみできる
     * テスト手順: 1. ステータスが退勤済であるユーザーにログインする 2. 勤務ボタンが表示されないことを確認する
     * 期待挙動: 画面上に「出勤」ボタンが表示されない
     */
    public function test_clock_in_can_only_be_done_once_per_day()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 退勤済みの勤怠レコードを作成
        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(9)->format('H:i:s'),
            'clock_out' => now()->format('H:i:s'),
        ]);

        // 出勤ボタンが表示されないことを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertDontSee('出勤', false);
    }

    /**
     * ID 6: 出勤機能
     * テスト内容: 出勤時刻が勤怠一覧画面で確認できる
     * テスト手順: 1. ステータスが勤務外のユーザーにログインする 2. 出勤の処理を行う 3. 勤怠一覧画面から出勤の日付を確認する
     * 期待挙動: 勤怠一覧画面に出勤時刻が正確に記録されている
     */
    public function test_clock_in_time_displayed_on_attendance_list()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 出勤処理を実行
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $clockInTime = $attendance->clock_in;

        // 勤怠一覧画面を確認
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee(date('H:i', strtotime($clockInTime)));
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩ボタンが正しく機能する
     * テスト手順: 1. ステータスが出勤中のユーザーにログインする 2. 画面に「休憩入」ボタンが表示されていることを確認する 3. 休憩の処理を行う
     * 期待挙動: 画面上に「休憩入」ボタンが表示され、処理後に画面上に表示されるステータスが「休憩中」になる
     */
    public function test_break_start_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 出勤済みの状態を作成
        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        // 休憩入ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');

        // 休憩入処理を実行
        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $response->assertRedirect('/attendance');

        // データベースに休憩記録が保存されていることを確認
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $breakTime = BreakTime::where('attendance_id', $attendance->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->first();

        $this->assertNotNull($breakTime);

        // 処理後のステータスが「休憩中」になることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '休憩中');
        $response->assertSee('休憩中');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩は一日に何回でもできる
     * テスト手順: 1. ステータスが出勤中であるユーザーにログインする 2. 休憩入と休憩戻の処理を行う 3. 「休憩入」ボタンが表示されることを確認する
     * 期待挙動: 画面上に「休憩入」ボタンが表示される
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

        // 1回目の休憩入と休憩戻
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        // 再度休憩入ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩戻ボタンが正しく機能する
     * テスト手順: 1. ステータスが出勤中であるユーザーにログインする 2. 休憩入の処理を行う 3. 休憩戻の処理を行う
     * 期待挙動: 休憩戻ボタンが表示され、処理後にステータスが「出勤中」に変更される
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

        // 休憩入処理
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        // 休憩戻ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');

        // 休憩戻処理を実行
        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $response->assertRedirect('/attendance');

        // 処理後のステータスが「出勤中」になることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '出勤中');
        $response->assertSee('出勤中');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩戻は一日に何回でもできる
     * テスト手順: 1. ステータスが出勤中であるユーザーにログインする 2. 休憩入と休憩戻の処理を行い、再度休憩入の処理を行う 3. 「休憩戻」ボタンが表示されることを確認する
     * 期待挙動: 画面上に「休憩戻」ボタンが表示される
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

        // 1回目の休憩入と休憩戻
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        // 2回目の休憩入
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        // 再度休憩戻ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩戻');
    }

    /**
     * ID 7: 休憩機能
     * テスト内容: 休憩時刻が勤怠一覧画面で確認できる
     * テスト手順: 1. ステータスが勤務中のユーザーにログインする 2. 休憩入と休憩戻の処理を行う 3. 勤怠一覧画面から休憩の日付を確認する
     * 期待挙動: 勤怠一覧画面に休憩時刻が正確に記録されている
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

        // 休憩入と休憩戻処理を実行
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_start',
        ]);

        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'break_end',
        ]);

        $breakTime = BreakTime::where('attendance_id', $attendance->id)->first();

        // 勤怠一覧画面を確認
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee($attendance->getTotalBreakTime());
    }

    /**
     * ID 8: 退勤機能
     * テスト内容: 退勤ボタンが正しく機能する
     * テスト手順: 1. ステータスが勤務中のユーザーにログインする 2. 画面に「退勤」ボタンが表示されていることを確認する 3. 退勤の処理を行う
     * 期待挙動: 画面上に「退勤」ボタンが表示され、処理後に画面上に表示されるステータスが「退勤済」になる
     */
    public function test_clock_out_button_functions_correctly()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 出勤済みの状態を作成
        Attendance::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour()->format('H:i:s'),
        ]);

        // 退勤ボタンが表示されることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');

        // 退勤処理を実行
        $response = $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_out',
        ]);

        $response->assertRedirect('/attendance');

        // データベースに退勤記録が保存されていることを確認
        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance->clock_out);

        // 処理後のステータスが「退勤済」になることを確認
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertViewHas('status', '退勤済');
        $response->assertSee('退勤済');
    }

    /**
     * ID 8: 退勤機能
     * テスト内容: 退勤時刻が勤怠一覧画面で確認できる
     * テスト手順: 1. ステータスが勤務外のユーザーにログインする 2. 出勤と退勤の処理を行う 3. 勤怠一覧画面から退勤の日付を確認する
     * 期待挙動: 勤怠一覧画面に退勤時刻が正確に記録されている
     */
    public function test_clock_out_time_displayed_on_attendance_list()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => now(),
        ]);

        // 出勤処理
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_in',
        ]);

        // 退勤処理
        $this->actingAs($user)->post('/attendance', [
            'stamp_type' => 'clock_out',
        ]);

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        $clockOutTime = $attendance->clock_out;

        // 勤怠一覧画面を確認
        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee(date('H:i', strtotime($clockOutTime)));
    }
}

