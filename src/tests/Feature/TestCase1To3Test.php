<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * テストケースID 1-3: 認証機能、ログイン認証機能
 */
class TestCase1To3Test extends TestCase
{
    use RefreshDatabase;

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: 名前が未入力の場合、バリデーションメッセージが表示される
     */
    public function test_register_validation_name_required()
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('name') === 'お名前を入力してください';
        });
    }

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_register_validation_email_required()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('email') === 'メールアドレスを入力してください';
        });
    }

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: パスワードが8文字未満の場合、バリデーションメッセージが表示される
     */
    public function test_register_validation_password_min_length()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('password') === 'パスワードは8文字以上で入力してください';
        });
    }

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: パスワードと確認パスワードが一致しない場合、バリデーションメッセージが表示される
     */
    public function test_register_validation_password_confirmation_mismatch()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('password') === 'パスワードと一致しません';
        });
    }

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_register_validation_password_required()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('password') === 'パスワードを入力してください';
        });
    }

    /**
     * ID 1: 認証機能（一般ユーザー）
     * テスト内容: フォームが正しく入力された場合、データベースに登録したユーザー情報が保存される
     */
    public function test_register_success_saves_user_to_database()
    {
        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'role' => 'general',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * ID 2: ログイン認証機能（一般ユーザー）
     * テスト内容: メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_login_validation_email_required()
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('email') === 'メールアドレスを入力してください';
        });
    }

    /**
     * ID 2: ログイン認証機能（一般ユーザー）
     * テスト内容: パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_login_validation_password_required()
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('password') === 'パスワードを入力してください';
        });
    }

    /**
     * ID 2: ログイン認証機能（一般ユーザー）
     * テスト内容: ログイン情報が登録されていない場合、バリデーションメッセージが表示される
     */
    public function test_login_fails_with_unregistered_credentials()
    {
        $response = $this->post('/login', [
            'email' => 'unregistered@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('email') === 'メールアドレスまたはパスワードが正しくありません。';
        });
    }

    /**
     * ID 3: ログイン認証機能（管理者）
     * テスト内容: メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_admin_login_validation_email_required()
    {
        $response = $this->post('/login', [
            'is_admin_login' => '1',
            'email' => '',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('email') === 'メールアドレスを入力してください';
        });
    }

    /**
     * ID 3: ログイン認証機能（管理者）
     * テスト内容: パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function test_admin_login_validation_password_required()
    {
        $response = $this->post('/login', [
            'is_admin_login' => '1',
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('password') === 'パスワードを入力してください';
        });
    }

    /**
     * ID 3: ログイン認証機能（管理者）
     * テスト内容: ログイン情報が登録されていない場合、バリデーションメッセージが表示される
     */
    public function test_admin_login_fails_with_unregistered_credentials()
    {
        $response = $this->post('/login', [
            'is_admin_login' => '1',
            'email' => 'unregistered@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('email') === 'メールアドレスまたはパスワードが正しくありません。';
        });
    }
}

