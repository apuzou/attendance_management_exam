@extends('layouts.app')

@section('title', '会員登録 - CT_勤怠管理')

@section('content')
<div class="auth-container">
    <h2 class="auth_title">会員登録</h2>

    <form method="POST" action="{{ route('register') }}" class="auth_form">
        @csrf

        @if (session('success'))
            <div class="success_message">
                {{ session('success') }}
            </div>
        @endif

        <div class="auth_field">
            <label for="name" class="auth_label">名前</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" class="auth_input">
            @error('name')
                <div class="error_message">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth_field">
            <label for="email" class="auth_label">メールアドレス</label>
            <input type="text" id="email" name="email" value="{{ old('email') }}" class="auth_input">
            @error('email')
                <div class="error_message">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth_field">
            <label for="password" class="auth_label">パスワード</label>
            <input type="password" id="password" name="password" class="auth_input">
            @error('password')
                <div class="error_message">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth_field">
            <label for="password_confirmation" class="auth_label">パスワード確認</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="auth_input">
        </div>

        <button type="submit" class="auth_submit">登録する</button>
    </form>

    <div class="auth_link">
        <a href="{{ route('login') }}" class="auth_link_text">ログインはこちら</a>
    </div>
</div>
@endsection
