@extends('layouts.app')

@section('title', 'ログイン - CT_勤怠管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="auth-container">
    <h2 class="auth-title">ログイン</h2>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">メールアドレス</label>
            <input type="text" id="email" name="email" value="{{ old('email') }}" class="auth-input">
            @error('email')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">パスワード</label>
            <input type="password" id="password" name="password" class="auth-input">
            @error('password')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="auth-submit">ログインする</button>
    </form>

    <div class="auth-link">
        <a href="{{ route('register') }}" class="auth-link-text">会員登録はこちら</a>
    </div>
</div>
@endsection
