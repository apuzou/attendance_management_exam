@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/correction.css') }}">
@endpush

@section('title', '申請一覧 - CT_勤怠管理')

@section('content')
<div class="container correction-container">
    <div class="title">
        <span class="title-bar">|</span>申請一覧
    </div>

    <div class="correction-tabs">
        <a href="{{ route('correction.index') }}" class="correction-tab {{ ($tab ?? 'pending') !== 'approved' ? 'correction-tab--active' : '' }}">承認待ち</a>
        <a href="{{ route('correction.index', ['tab' => 'approved']) }}" class="correction-tab {{ ($tab ?? 'pending') === 'approved' ? 'correction-tab--active' : '' }}">承認済み</a>
    </div>

    <div class="correction-tab-content {{ ($tab ?? 'pending') !== 'approved' ? 'correction-tab-content--active' : '' }}">
        <table class="correction-table table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @if($pendingRequests->isEmpty())
                    <tr>
                        <td colspan="6" class="correction-empty">承認待ちの申請がありません</td>
                    </tr>
                @else
                    @foreach($pendingRequests as $request)
                        <tr>
                            <td>承認待ち</td>
                            <td>{{ $request->user->name }}</td>
                            <td>{{ $request->attendance->date->format('Y/m/d') }}</td>
                            <td>{{ $request->note }}</td>
                            <td>{{ $request->request_date->format('Y/m/d') }}</td>
                            <td>
                                <a href="{{ route('attendance.show', $request->attendance_id) }}" class="detail-link">詳細</a>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>

    <div class="correction-tab-content {{ ($tab ?? 'pending') === 'approved' ? 'correction-tab-content--active' : '' }}">
        <table class="correction-table table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
                @if($approvedRequests->isEmpty())
                    <tr>
                        <td colspan="6" class="correction-empty">承認済みの申請がありません</td>
                    </tr>
                @else
                    @foreach($approvedRequests as $request)
                        <tr>
                            <td>承認済み</td>
                            <td>{{ $request->user->name }}</td>
                            <td>{{ $request->attendance->date->format('Y/m/d') }}</td>
                            <td>{{ $request->note }}</td>
                            <td>{{ $request->request_date->format('Y/m/d') }}</td>
                            <td>
                                <a href="{{ route('attendance.show', $request->attendance_id) }}" class="detail-link">詳細</a>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

@endsection

