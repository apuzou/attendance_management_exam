@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/list.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title', 'スタッフ別勤怠一覧 (管理者) - CT_勤怠管理')

@section('content')
<div class="container admin-container">
    <div class="title">
        <span class="title-bar">|</span> {{ $user->name }}さんの勤怠
    </div>

    <div class="navigation list-month-navigation">
        <a href="{{ route('admin.list', ['id' => $user->id, 'month' => $prevMonth]) }}" class="navigation-link">←前月</a>
        <span class="navigation-current list-month-current">
            📅 {{ $currentMonth->format('Y/m') }}
        </span>
        <a href="{{ route('admin.list', ['id' => $user->id, 'month' => $nextMonth]) }}" class="navigation-link">翌月→</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @php
                $daysInMonth = $currentMonth->daysInMonth;
                $firstDay = $currentMonth->copy()->startOfMonth();
            @endphp
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $currentDate = $firstDay->copy()->addDays($day - 1);
                    $attendance = $attendances->first(function ($att) use ($currentDate) {
                        return $att->date->format('Y-m-d') === $currentDate->format('Y-m-d');
                    });
                @endphp
                <tr>
                    <td class="list-date">{{ $currentDate->format('m/d') }}({{ ['日', '月', '火', '水', '木', '金', '土'][$currentDate->dayOfWeek] }})</td>
                    <td>{{ $attendance && $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '' }}</td>
                    <td>{{ $attendance && $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '' }}</td>
                    <td>{{ $attendance ? $attendance->getTotalBreakTime() : '' }}</td>
                    <td>{{ $attendance ? $attendance->getWorkTime() : '' }}</td>
                    <td>
                        @if($attendance)
                            <a href="{{ route('admin.show', $attendance->id) }}" class="detail-link">詳細</a>
                        @endif
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="list-csv-actions">
        <a href="{{ route('admin.list', ['id' => $user->id, 'month' => $currentMonth->format('Y-m'), 'download' => 'csv']) }}" class="list-csv-button">CSV出力</a>
    </div>
</div>
@endsection

