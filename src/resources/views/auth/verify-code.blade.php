@extends('layouts.app')

@section('title', '認証コード入力 - CT_勤怠管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="container auth-container">
    <h2 class="auth-title">認証コード入力</h2>

    <p class="auth-description">
        {{ Auth::user()->email }} に送信された6桁の認証コードを入力してください。
    </p>

    <form method="POST" action="{{ route('verification.verify') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="verification_code" class="auth-label">認証コード</label>
            <input
                type="text"
                id="verification_code"
                name="verification_code"
                value="{{ old('verification_code') }}"
                class="auth-input auth-input--code"
                placeholder="000000"
                maxlength="6"
                autofocus
            >
            @error('verification_code')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="auth-submit">認証する</button>
    </form>

    <div class="auth-link">
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="auth-link-button">認証メールを再送する</button>
        </form>
    </div>
</div>
@endsection
