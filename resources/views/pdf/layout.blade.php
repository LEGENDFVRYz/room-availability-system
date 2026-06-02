<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Records Report' }}</title>

    <style>
        @page {
            margin: 24px 28px 34px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        .report-wrapper {
            width: 100%;
        }

        .report-title-block {
            margin-top: 16px;
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            /* border-left: 6px solid #800000; */
            background: #fff8e1;
        }

        .report-title {
            font-size: 20px;
            font-weight: 700;
            color: #4b0000;
            line-height: 1.2;
        }

        .report-subtitle {
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
        }

        .meta-grid {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }

        .meta-grid td {
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            vertical-align: top;
        }

        .meta-label {
            display: block;
            margin-bottom: 2px;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .meta-value {
            font-size: 10px;
            font-weight: 700;
            color: #111827;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .report-table th {
            padding: 7px 7px;
            border: 1px solid #650000;
            background: #800000;
            color: #ffffff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: left;
            vertical-align: top;
        }

        .report-table td {
            padding: 7px 7px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
            word-wrap: break-word;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .text-muted {
            color: #6b7280;
        }

        .text-strong {
            font-weight: 700;
            color: #111827;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }

        .badge-maroon {
            background: #f7e8e8;
            color: #800000;
        }

        .badge-gold {
            background: #fff8e1;
            color: #7a4f00;
        }

        .badge-green {
            background: #dcfce7;
            color: #166534;
        }

        .badge-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-gray {
            background: #f3f4f6;
            color: #374151;
        }

        .empty-state {
            padding: 28px 16px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            text-align: center;
            color: #6b7280;
        }

        .footer {
            margin-top: 14px;
            padding-top: 5px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
        }

        .footer-left {
            float: left;
        }

        .footer-right {
            float: right;
        }

        .page-break {
            page-break-after: always;
        }
    </style>

    @stack('styles')
</head>
<body>
    <div class="report-wrapper">
        @include('pdf.header')
        @yield('content')

        <div class="footer">
            <span class="footer-left">CPE Room Availability System</span>
            <span class="footer-right">Generated {{ $generatedAt ?? now()->format('M d, Y h:i A') }}</span>
        </div>
    </div>
</body>
</html>
