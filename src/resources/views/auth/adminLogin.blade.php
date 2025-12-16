@extends('layouts.app')

@section('title', '管理者ログイン - CT_勤怠管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="container auth-container">
    <h2 class="auth-title">管理者ログイン</h2>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <input type="hidden" name="is_admin_login" value="1">

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

        <button type="submit" class="auth-submit">管理者ログインする</button>
    </form>
</div>
@endsection

