@extends('pdf.layout')

@push('styles')
<style>
    .filter-notice {
        margin: 0 0 12px 0;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-left: 4px solid #800000;
        background: #fbfbfb;
    }

    .filter-notice-title {
        margin-bottom: 7px;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #374151;
    }

    .filter-list {
        width: 100%;
        border-collapse: collapse;
    }

    .filter-list td {
        width: 50%;
        padding: 2px 12px 2px 0;
        border: none;
        background: transparent;
        vertical-align: top;
        font-size: 9px;
        line-height: 1.35;
    }

    .filter-label {
        font-weight: 700;
        color: #111827;
        white-space: nowrap;
    }

    .filter-value {
        color: #111827;
    }

    table.simple-records-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .simple-records-table thead {
        display: table-header-group;
    }

    .simple-records-table tr {
        page-break-inside: avoid;
    }

    .simple-records-table th {
        padding: 6px 6px;
        border: 1px solid #9ca3af;
        background: #f3f4f6;
        color: #111827;
        font-size: 8px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        text-align: left;
        vertical-align: top;
    }

    .simple-records-table td {
        padding: 6px 6px;
        border: 1px solid #d1d5db;
        background: #ffffff;
        vertical-align: top;
        word-wrap: break-word;
    }

    .cell-title {
        font-weight: 700;
        color: #111827;
    }

    .cell-subtext {
        margin-top: 1px;
        font-size: 8px;
        color: #4b5563;
    }

    .nowrap {
        white-space: nowrap;
    }
</style>
@endpush

@section('content')
    <div class="report-title-block">
        <h1 class="report-title">{{ $title ?? 'Room Usage Logs Report' }}</h1>
        <p class="report-subtitle">Valid classroom borrowing records only. Cancelled, unclaimed, blocked, maintenance, unavailable, and reserved-only records are excluded.</p>
    </div>

    <div class="filter-notice">
        <div class="filter-notice-title">Report scope and filters applied</div>
        <table class="filter-list">
            <tr>
                <td><span class="filter-label">Date scope:</span> <span class="filter-value">{{ $filters['date_scope_label'] ?? 'All dates' }}</span></td>
                <td><span class="filter-label">Room:</span> <span class="filter-value">{{ $filters['room_label'] ?? 'All rooms' }}</span></td>
            </tr>
            <tr>
                <td><span class="filter-label">Borrowing type:</span> <span class="filter-value">{{ $filters['borrow_type_label'] ?? 'All valid types' }}</span></td>
                <td><span class="filter-label">Search:</span> <span class="filter-value">{{ $filters['search_label'] ?? 'None' }}</span></td>
            </tr>
            <tr>
                <td><span class="filter-label">Total records:</span> <span class="filter-value">{{ count($logs) }}</span></td>
                <td><span class="filter-label">Generated:</span> <span class="filter-value">{{ $generatedAt ?? now()->format('M d, Y h:i A') }}</span></td>
            </tr>
        </table>
    </div>

    @if(count($logs) === 0)
        <div class="empty-state">No room usage records matched the selected filters.</div>
    @else
        <table class="simple-records-table">
            <thead>
                <tr>
                    <th style="width: 11%;">Date</th>
                    <th style="width: 9%;">Room</th>
                    <th style="width: 40%;">Class Details</th>
                    <th style="width: 23%;">Time</th>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 7%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="nowrap">{{ $log['usage_date_label'] ?? ($log['usage_date'] ?? '-') }}</td>
                        <td class="cell-title">{{ $log['room_code'] ?? ('Room '.$log['room_id']) }}</td>
                        <td>
                            <div class="cell-title">{{ $log['subject_code'] ?? 'Class' }} - {{ $log['subject_title'] ?? '' }}</div>
                            <div class="cell-subtext">{{ $log['section'] ?? '-' }} - {{ $log['instructor_name'] ?? 'No instructor listed' }}</div>
                        </td>
                        <td>
                            <div><span class="cell-title">Expected:</span> {{ $log['expected_start_label'] ?? ($log['expected_start'] ?? '-') }} - {{ $log['expected_end_label'] ?? ($log['expected_end'] ?? '-') }}</div>
                            <div class="cell-subtext"><span class="cell-title">Actual:</span> {{ $log['actual_start_label'] ?? ($log['actual_start'] ?? '-') }} - {{ $log['actual_end_label'] ?? ($log['actual_end'] ?? 'In progress') }}</div>
                        </td>
                        <td>{{ $log['borrow_type_label'] ?? ucfirst(str_replace('_', ' ', $log['borrow_type'] ?? 'Borrowed')) }}</td>
                        <td>{{ $log['status_label'] ?? ucfirst($log['status'] ?? 'Status') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
