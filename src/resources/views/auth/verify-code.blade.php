@extends('layouts.app')

@section('title', '認証コード入力 - CT_勤怠管理')

@section('content')
<div class="auth-container">
    <h2 class="auth_title">認証コード入力</h2>

    <p class="auth_description">
        {{ Auth::user()->email }} に送信された6桁の認証コードを入力してください。
    </p>

    <form method="POST" action="{{ route('verification.verify') }}" class="auth_form">
        @csrf

        @if (session('success'))
            <div class="success_message">
                {{ session('success') }}
            </div>
        @endif

        <div class="auth_field">
            <label for="verification_code" class="auth_label">認証コード</label>
            <input 
                type="text" 
                id="verification_code" 
                name="verification_code" 
                value="{{ old('verification_code') }}" 
                class="auth_input auth_input--code" 
                placeholder="000000"
                maxlength="6"
                autofocus
            >
            @error('verification_code')
                <div class="error_message">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="auth_submit">認証する</button>
    </form>

    <div class="auth_link">
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="auth_link_button">認証メールを再送する</button>
        </form>
    </div>
</div>

@endsection
