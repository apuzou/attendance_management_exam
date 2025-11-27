@extends('layouts.app')

@section('title', 'ログイン - CT_勤怠管理')

@section('content')
<div class="auth-container">
    <h2 class="auth_title">ログイン</h2>

    <form method="POST" action="{{ route('login') }}" class="auth_form">
        @csrf

        @if (session('success'))
            <div class="success_message">
                {{ session('success') }}
            </div>
        @endif

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

        <button type="submit" class="auth_submit">ログインする</button>
    </form>

    <div class="auth_link">
        <a href="{{ route('register') }}" class="auth_link_text">会員登録はこちら</a>
    </div>
</div>
@endsection
