@extends('layouts.app')

@section('title', 'メール認証 - CT_勤怠管理')

@section('content')
<div class="auth-container">
    <div class="auth_message">
        <p>登録していただいたメールアドレスに認証メールを送付しました。メール認証を完了してください。</p>
    </div>

    @if (session('success'))
        <div class="success_message">
            {{ session('success') }}
        </div>
    @endif

    <div class="auth_actions">
        <a href="{{ route('verification.code') }}" class="auth_button">認証はこちらから</a>
    </div>

    <div class="auth_link">
        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="auth_link_button">認証メールを再送する</button>
        </form>
    </div>
</div>
@endsection
