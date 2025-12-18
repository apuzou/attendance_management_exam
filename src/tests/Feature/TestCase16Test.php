<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * テストケースID 16: メール認証機能
 */
class TestCase16Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 16: メール認証機能
     * テスト内容: 会員登録後、認証メールが送信される
     */
    public function test_verification_email_sent_after_registration()
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/email/verify');

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->verification_code);
        $this->assertNotNull($user->verification_code_expires_at);

        Mail::assertSent(\App\Mail\VerificationCodeMail::class, function ($mail) use ($user) {
            return $mail->hasTo('test@example.com');
        });
    }

    /**
     * ID 16: メール認証機能
     * テスト内容: メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する
     */
    public function test_verification_guidance_button_navigates_to_verification_code_screen()
    {
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('認証はこちらから');

        $response = $this->actingAs($user)->get('/email/verify/code');

        $response->assertStatus(200);
        $response->assertViewIs('auth.verify-code');
    }

    /**
     * ID 16: メール認証機能
     * テスト内容: メール認証サイトのメール認証を完了すると、勤怠登録画面に遷移する
     */
    public function test_email_verification_completion_navigates_to_attendance_screen()
    {
        $verificationCode = '123456';
        $user = User::factory()->create([
            'role' => 'general',
            'email_verified_at' => null,
            'verification_code' => $verificationCode,
            'verification_code_expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($user)->post('/email/verify', [
            'verification_code' => $verificationCode,
        ]);

        $response->assertRedirect('/attendance');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->verification_code);
        $this->assertNull($user->verification_code_expires_at);
    }
}

