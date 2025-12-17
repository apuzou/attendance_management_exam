@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title', '修正申請承認 (管理者) - CT_勤怠管理')

@section('content')
<div class="container admin-container">
    <div class="title">
        <span class="title-bar">|</span> 勤怠詳細
    </div>

    <form method="POST" action="{{ route('correction.approve', $request->id) }}" class="show-form" id="approval-form">
        @csrf

        <table class="show-table">
            <tbody>
                <tr>
                    <th class="show-label-cell">名前</th>
                    <td class="show-value-cell">
                        <div class="show-value">{{ $user->name }}</div>
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
                            <span class="show-time-value">{{ $request->corrected_clock_in ? date('H:i', strtotime($request->corrected_clock_in)) : ($attendance->clock_in ? date('H:i', strtotime($attendance->clock_in)) : '') }}</span>
                            <span class="show-time-separator">~</span>
                            <span class="show-time-value">{{ $request->corrected_clock_out ? date('H:i', strtotime($request->corrected_clock_out)) : ($attendance->clock_out ? date('H:i', strtotime($attendance->clock_out)) : '') }}</span>
                        </div>
                    </td>
                </tr>
                @php
                    $displayBreakTimes = [];
                    $breakCorrectionRequests = $request->breakCorrectionRequests->sortBy('id');

                    $existingBreakTimes = $attendance->breakTimes->sortBy('id');
                    foreach ($existingBreakTimes as $breakTime) {
                        $correctionRequest = $breakCorrectionRequests->where('break_time_id', $breakTime->id)->first();
                        if ($correctionRequest) {
                            $displayBreakTimes[] = [
                                'id' => $breakTime->id,
                                'break_start' => $correctionRequest->corrected_break_start,
                                'break_end' => $correctionRequest->corrected_break_end,
                            ];
                        } else {
                            $displayBreakTimes[] = [
                                'id' => $breakTime->id,
                                'break_start' => $breakTime->break_start,
                                'break_end' => $breakTime->break_end,
                            ];
                        }
                    }

                    foreach ($breakCorrectionRequests->whereNull('break_time_id') as $correctionRequest) {
                        $displayBreakTimes[] = [
                            'id' => null,
                            'break_start' => $correctionRequest->corrected_break_start,
                            'break_end' => $correctionRequest->corrected_break_end,
                        ];
                    }

                    usort($displayBreakTimes, function ($first, $second) {
                        $firstStart = strtotime($first['break_start']);
                        $secondStart = strtotime($second['break_start']);
                        return $firstStart <=> $secondStart;
                    });
                @endphp
                @foreach($displayBreakTimes as $index => $breakTimeData)
                    <tr>
                        <th class="show-label-cell">休憩{{ $index + 1 }}</th>
                        <td class="show-value-cell">
                            <div class="show-time-field">
                                <span class="show-time-value">{{ $breakTimeData['break_start'] ? date('H:i', strtotime($breakTimeData['break_start'])) : '' }}</span>
                                <span class="show-time-separator">~</span>
                                <span class="show-time-value">{{ $breakTimeData['break_end'] ? date('H:i', strtotime($breakTimeData['break_end'])) : '' }}</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <th class="show-label-cell">備考</th>
                    <td class="show-value-cell">
                        <div class="show-value">{{ $request->note ?? '' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>

        @if($isApproved)
            <div class="show-actions">
                <button type="button" class="show-submit-button" disabled>承認済み</button>
            </div>
        @elseif($canApprove)
            <div class="show-actions">
                <button type="submit" class="show-submit-button">承認</button>
            </div>
        @else
            <div class="show-error-message">
                *承認待ちのため修正はできません。
            </div>
        @endif
    </form>

    @if($errors->has('request'))
        <div class="show-errors">
            <div class="show-error">{{ $errors->first('request') }}</div>
        </div>
    @endif
</div>
@endsection

