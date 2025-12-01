@extends('layouts.app')

@section('title', '会員登録 - CT_勤怠管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="container auth-container">
    <h2 class="auth-title">会員登録</h2>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="name" class="auth-label">名前</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" class="auth-input">
            @error('name')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

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

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">パスワード確認</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="auth-input">
        </div>

        <button type="submit" class="auth-submit">登録する</button>
    </form>

    <div class="auth-link">
        <a href="{{ route('login') }}" class="auth-link-text">ログインはこちら</a>
    </div>
</div>
@endsection
