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
            <input type="checkbox" id="admin-calendar-toggle" class="admin-calendar-toggle" {{ request()->has('calendar_month') ? 'checked' : '' }}>
            <label for="admin-calendar-toggle" class="admin-calendar-trigger">
                <span class="admin-date-icon">📅</span>
                {{ $currentDate->format('Y/m/d') }}
            </label>
            <div class="admin-calendar-overlay">
                <label for="admin-calendar-toggle" class="admin-calendar-overlay-close"></label>
                <div class="admin-calendar">
                    <div class="admin-calendar-header">
                        <a href="{{ route('admin.index', array_merge(request()->only(['date']), ['calendar_month' => $prevCalendarMonth])) }}" class="admin-calendar-nav">←</a>
                        <div class="admin-calendar-month">{{ $calendarMonth->format('Y年n月') }}</div>
                        <a href="{{ route('admin.index', array_merge(request()->only(['date']), ['calendar_month' => $nextCalendarMonth])) }}" class="admin-calendar-nav">→</a>
                        <label for="admin-calendar-toggle" class="admin-calendar-close">×</label>
                    </div>
                    <div class="admin-calendar-weekdays">
                        <div class="admin-calendar-weekday">日</div>
                        <div class="admin-calendar-weekday">月</div>
                        <div class="admin-calendar-weekday">火</div>
                        <div class="admin-calendar-weekday">水</div>
                        <div class="admin-calendar-weekday">木</div>
                        <div class="admin-calendar-weekday">金</div>
                        <div class="admin-calendar-weekday">土</div>
                    </div>
                    <div class="admin-calendar-days">
                        @php
                            $firstDay = $calendarMonth->copy()->startOfMonth()->startOfWeek();
                            $lastDay = $calendarMonth->copy()->endOfMonth()->endOfWeek();
                            $calendarDate = $firstDay->copy();
                        @endphp
                        @while($calendarDate <= $lastDay)
                            @php
                                $isCurrentMonth = $calendarDate->format('Y-m') === $calendarMonth->format('Y-m');
                                $isCurrentDate = $calendarDate->format('Y-m-d') === $currentDate->format('Y-m-d');
                                $dateString = $calendarDate->format('Y-m-d');
                            @endphp
                            <a href="{{ route('admin.index', ['date' => $dateString]) }}"
                                class="admin-calendar-day {{ !$isCurrentMonth ? 'admin-calendar-day--other-month' : '' }} {{ $isCurrentDate ? 'admin-calendar-day--current' : '' }}">
                                {{ $calendarDate->format('j') }}
                            </a>
                            @php
                                $calendarDate->addDay();
                            @endphp
                        @endwhile
                    </div>
                </div>
            </div>
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

