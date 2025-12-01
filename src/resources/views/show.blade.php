@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}">
@endpush

@section('title', '勤怠詳細 - CT_勤怠管理')

@section('content')
<div class="container">
    <div class="title">
        <span class="title-bar">|</span> 勤怠詳細
    </div>

    <form method="POST" action="{{ route('attendance.update', $attendance->id) }}" class="show-form" id="show-form">
        @csrf

        <table class="show-table">
            <tbody>
                <tr>
                    <th class="show-label-cell">名前</th>
                    <td class="show-value-cell">
                        <div class="show-value">{{ $attendance->user->name }}</div>
                    </td>
                </tr>
                <tr>
                    <th class="show-label-cell">日付</th>
                    <td class="show-value-cell">
                        <div class="show-date-field">
                            <div class="show-date-value">{{ $attendance->date->format('Y年') }}</div>
                            <div class="show-date-value">{{ $attendance->date->format('n月j日') }}</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th class="show-label-cell">出勤・退勤</th>
                    <td class="show-value-cell">
                        <div class="show-time-field">
                            @if($canEdit)
                                <input type="text" name="corrected_clock_in" value="{{ $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '' }}" class="show-time-input" placeholder="09:00">
                            @else
                                <span class="show-time-value">{{ $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '' }}</span>
                            @endif
                            <span class="show-time-separator">~</span>
                            @if($canEdit)
                                <input type="text" name="corrected_clock_out" value="{{ $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '' }}" class="show-time-input" placeholder="18:00">
                            @else
                                <span class="show-time-value">{{ $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '' }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @php
                    $breakTimes = $attendance->breakTimes->sortBy('id');
                @endphp
                @foreach($breakTimes as $index => $breakTime)
                    <tr>
                        <th class="show-label-cell">休憩{{ $index + 1 }}</th>
                        <td class="show-value-cell">
                            <div class="show-time-field">
                                @if($canEdit)
                                    <input type="hidden" name="break_times[{{ $index }}][id]" value="{{ $breakTime->id }}">
                                    <input type="text" name="break_times[{{ $index }}][break_start]" value="{{ $breakTime->break_start ? date('H:i', strtotime($breakTime->break_start)) : '' }}" class="show-time-input" placeholder="12:00">
                                @else
                                    <span class="show-time-value">{{ $breakTime->break_start ? date('H:i', strtotime($breakTime->break_start)) : '' }}</span>
                                @endif
                                <span class="show-time-separator">~</span>
                                @if($canEdit)
                                    <input type="text" name="break_times[{{ $index }}][break_end]" value="{{ $breakTime->break_end ? date('H:i', strtotime($breakTime->break_end)) : '' }}" class="show-time-input" placeholder="13:00">
                                @else
                                    <span class="show-time-value">{{ $breakTime->break_end ? date('H:i', strtotime($breakTime->break_end)) : '' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach

                @if($canEdit)
                    @php
                        $newBreakIndex = count($breakTimes);
                    @endphp
                    <tr>
                        <th class="show-label-cell">休憩{{ $newBreakIndex + 1 }}</th>
                        <td class="show-value-cell">
                            <div class="show-time-field">
                                <input type="text" name="break_times[{{ $newBreakIndex }}][break_start]" value="" class="show-time-input" placeholder="12:00">
                                <span class="show-time-separator">~</span>
                                <input type="text" name="break_times[{{ $newBreakIndex }}][break_end]" value="" class="show-time-input" placeholder="13:00">
                            </div>
                        </td>
                    </tr>
                @endif
                <tr>
                    <th class="show-label-cell">備考</th>
                    <td class="show-value-cell">
                        @if($canEdit)
                            <input type="text" name="note" value="{{ $attendance->note ?? '' }}" class="show-note-input">
                        @else
                            <div class="show-value">{{ $attendance->note ?? '' }}</div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        @if($canEdit)
            <div class="show-actions">
                <button type="submit" class="show-submit-button">修正</button>
            </div>
        @else
            <div class="show-error-message">
                *承認待ちのため修正はできません。
            </div>
        @endif
    </form>

    @if($errors->any())
        <div class="show-errors">
            @foreach($errors->all() as $error)
                <div class="show-error">{{ $error }}</div>
            @endforeach
        </div>
    @endif
</div>
@endsection

