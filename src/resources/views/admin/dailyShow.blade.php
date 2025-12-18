@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}">
@endpush

@section('title', '勤怠詳細 (管理者) - CT_勤怠管理')

@section('content')
<div class="container">
    <h2 class="title">
        <span class="title-bar">|</span>勤怠詳細
    </h2>

    <form method="POST" action="{{ route('admin.update', $attendance->id) }}" class="show-form" id="show-form">
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
                                <input type="text" name="corrected_clock_in" value="{{ old('corrected_clock_in', $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '') }}" class="show-time-input @error('corrected_clock_in') show-input-error @enderror" placeholder="09:00">
                            @else
                                <span class="show-time-value">{{ $attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '' }}</span>
                            @endif
                            <span class="show-time-separator">~</span>
                            @if($canEdit)
                                <input type="text" name="corrected_clock_out" value="{{ old('corrected_clock_out', $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '') }}" class="show-time-input @error('corrected_clock_out') show-input-error @enderror" placeholder="18:00">
                            @else
                                <span class="show-time-value">{{ $attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '' }}</span>
                            @endif
                        </div>
                        @error('corrected_clock_in')
                            <div class="show-field-error">{{ $message }}</div>
                        @enderror
                        @error('corrected_clock_out')
                            <div class="show-field-error">{{ $message }}</div>
                        @enderror
                    </td>
                </tr>
                @php
                    $displayBreakTimes = $attendance->breakTimes->sortBy('id')->map(function($breakTime) {
                        return [
                            'id' => $breakTime->id,
                            'break_start' => $breakTime->break_start,
                            'break_end' => $breakTime->break_end,
                        ];
                    })->toArray();
                @endphp
                @foreach($displayBreakTimes as $index => $breakTimeData)
                    <tr>
                        <th class="show-label-cell">休憩{{ $index + 1 }}</th>
                        <td class="show-value-cell">
                            <div class="show-time-field">
                                @if($canEdit)
                                    <input type="hidden" name="break_times[{{ $index }}][id]" value="{{ $breakTimeData['id'] }}">
                                    <input type="text" name="break_times[{{ $index }}][break_start]" value="{{ old("break_times.{$index}.break_start", $breakTimeData['break_start'] ? date('H:i', strtotime($breakTimeData['break_start'])) : '') }}" class="show-time-input @error("break_times.{$index}.break_start") show-input-error @enderror" placeholder="12:00">
                                @else
                                    <span class="show-time-value">{{ $breakTimeData['break_start'] ? date('H:i', strtotime($breakTimeData['break_start'])) : '' }}</span>
                                @endif
                                <span class="show-time-separator">~</span>
                                @if($canEdit)
                                    <input type="text" name="break_times[{{ $index }}][break_end]" value="{{ old("break_times.{$index}.break_end", $breakTimeData['break_end'] ? date('H:i', strtotime($breakTimeData['break_end'])) : '') }}" class="show-time-input @error("break_times.{$index}.break_end") show-input-error @enderror" placeholder="13:00">
                                @else
                                    <span class="show-time-value">{{ $breakTimeData['break_end'] ? date('H:i', strtotime($breakTimeData['break_end'])) : '' }}</span>
                                @endif
                            </div>
                            @error("break_times.{$index}.break_start")
                                <div class="show-field-error">{{ $message }}</div>
                            @enderror
                            @error("break_times.{$index}.break_end")
                                <div class="show-field-error">{{ $message }}</div>
                            @enderror
                        </td>
                    </tr>
                @endforeach

                @if($canEdit)
                    @php
                        $newBreakIndex = count($displayBreakTimes);
                    @endphp
                    <tr>
                        <th class="show-label-cell">休憩{{ $newBreakIndex + 1 }}</th>
                        <td class="show-value-cell">
                            <div class="show-time-field">
                                <input type="text" name="break_times[{{ $newBreakIndex }}][break_start]" value="{{ old("break_times.{$newBreakIndex}.break_start", '') }}" class="show-time-input @error("break_times.{$newBreakIndex}.break_start") show-input-error @enderror" placeholder="12:00">
                                <span class="show-time-separator">~</span>
                                <input type="text" name="break_times[{{ $newBreakIndex }}][break_end]" value="{{ old("break_times.{$newBreakIndex}.break_end", '') }}" class="show-time-input @error("break_times.{$newBreakIndex}.break_end") show-input-error @enderror" placeholder="13:00">
                            </div>
                            @error("break_times.{$newBreakIndex}.break_start")
                                <div class="show-field-error">{{ $message }}</div>
                            @enderror
                            @error("break_times.{$newBreakIndex}.break_end")
                                <div class="show-field-error">{{ $message }}</div>
                            @enderror
                        </td>
                    </tr>
                @endif
                <tr>
                    <th class="show-label-cell">備考</th>
                    <td class="show-value-cell">
                        @if($canEdit)
                            <input type="text" name="note" value="{{ old('note', $attendance->note ?? '') }}" class="show-note-input @error('note') show-input-error @enderror">
                            @error('note')
                                <div class="show-field-error">{{ $message }}</div>
                            @enderror
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

    @if($errors->has('attendance'))
        <div class="show-errors">
            <div class="show-error">{{ $errors->first('attendance') }}</div>
        </div>
    @endif
</div>
@endsection

