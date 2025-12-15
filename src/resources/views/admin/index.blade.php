@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title', '勤怠一覧 (管理者) - CT_勤怠管理')

@section('content')
<div class="container admin-container">
    <div class="title">
        <span class="title-bar">|</span>{{ $currentDate->format('Y年n月j日') }}の勤怠
    </div>

    <div class="navigation admin-date-navigation">
        <a href="{{ route('admin.index', ['date' => $prevDate]) }}" class="navigation-link">← 前日</a>
        <div class="navigation-current admin-date-current">
            <span class="admin-date-icon">📅</span>
            {{ $currentDate->format('Y/m/d') }}
        </div>
        <a href="{{ route('admin.index', ['date' => $nextDate]) }}" class="navigation-link">翌日 →</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>名前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $attendance)
                <tr>
                    <td>{{ $attendance->user->name }}</td>
                    <td>{{ $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '' }}</td>
                    <td>{{ $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '' }}</td>
                    <td>{{ $attendance->getTotalBreakTime() }}</td>
                    <td>{{ $attendance->getWorkTime() }}</td>
                    <td>
                        <a href="{{ route('admin.show', $attendance->id) }}" class="detail-link">詳細</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

