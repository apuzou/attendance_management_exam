@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endpush

@section('title', '勤怠登録 - CT_勤怠管理')

@section('content')
<div class="attendance-container">
    <div class="attendance-status">
        <span class="status-badge">
            {{ $status }}
        </span>
    </div>

    <div class="attendance-date">
        {{ $date->format('Y年n月j日') }}({{ ['日', '月', '火', '水', '木', '金', '土'][$date->dayOfWeek] }})
    </div>

    <div class="attendance-time">
        {{ $currentTime->format('H:i') }}
    </div>

    @if($status === '退勤済')
        <div class="attendance-message">
            お疲れ様でした。
        </div>
    @else
        <div class="attendance-actions">
            @if($status === '勤務外')
                <form method="POST" action="{{ route('attendance.store') }}" class="attendance-form">
                    @csrf
                    <input type="hidden" name="stamp_type" value="clock_in">
                    <button type="submit" class="attendance-button attendance-button--primary">出勤</button>
                </form>
            @elseif($status === '出勤中')
                <form method="POST" action="{{ route('attendance.store') }}" class="attendance-form">
                    @csrf
                    <input type="hidden" name="stamp_type" value="clock_out">
                    <button type="submit" class="attendance-button attendance-button--primary">退勤</button>
                </form>
                <form method="POST" action="{{ route('attendance.store') }}" class="attendance-form">
                    @csrf
                    <input type="hidden" name="stamp_type" value="break_start">
                    <button type="submit" class="attendance-button attendance-button--secondary">休憩入</button>
                </form>
            @elseif($status === '休憩中')
                <form method="POST" action="{{ route('attendance.store') }}" class="attendance-form">
                    @csrf
                    <input type="hidden" name="stamp_type" value="break_end">
                    <button type="submit" class="attendance-button attendance-button--secondary">休憩戻</button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection

