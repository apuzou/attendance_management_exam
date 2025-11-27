@extends('layouts.app')

@section('title', 'メール認証 - CT_勤怠管理')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
<div class="auth-container">
    <div class="auth-message">
        <p>登録していただいたメールアドレスに認証メールを送付しました。メール認証を完了してください。</p>
    </div>

    <div class="auth-actions">
        <a href="{{ route('verification.code') }}" class="auth-button">認証はこちらから</a>
    </div>

    <div class="auth-link">
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="auth-link-button">認証メールを再送する</button>
        </form>
    </div>
</div>
@endsection
