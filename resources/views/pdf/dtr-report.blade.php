<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR - {{ $employee['name'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #1f2937;
        }

        .report {
            padding: 15px;
            max-width: 100%;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .header-left {
            display: table-cell;
            width: 70%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 30%;
            vertical-align: top;
            text-align: right;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Info Sections */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }

        .info-box {
            display: table-cell;
            width: 50%;
            padding: 8px;
            background-color: #f8fafc;
            vertical-align: top;
        }

        .info-box:first-child {
            border-right: 1px solid #e5e7eb;
        }

        .info-box-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 2px;
        }

        .info-label {
            display: table-cell;
            width: 35%;
            color: #6b7280;
            font-size: 8px;
        }

        .info-value {
            display: table-cell;
            width: 65%;
            font-weight: 500;
            font-size: 8px;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8px;
        }

        .data-table th {
            background-color: #1e40af;
            color: #ffffff;
            padding: 5px 6px;
            text-align: center;
            font-weight: 600;
            font-size: 8px;
            border: 1px solid #1e40af;
        }

        .data-table th:first-child,
        .data-table th:nth-child(2),
        .data-table th:nth-child(3) {
            text-align: left;
        }

        .data-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .data-table td:first-child,
        .data-table td:nth-child(2),
        .data-table td:nth-child(3) {
            text-align: left;
        }

        .data-table td:last-child {
            text-align: left;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .data-table tbody tr.status-absent td {
            color: #dc2626;
        }

        .data-table tbody tr.status-rest_day td,
        .data-table tbody tr.status-holiday td {
            color: #6b7280;
            font-style: italic;
        }

        .text-red {
            color: #dc2626;
        }

        .text-green {
            color: #059669;
        }

        /* Summary Section */
        .summary-section {
            margin-top: 10px;
        }

        .summary-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 10px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            width: 16.66%;
            text-align: center;
            padding: 0 5px;
        }

        .summary-item:not(:last-child) {
            border-right: 1px solid #cbd5e1;
        }

        .summary-label {
            font-size: 7px;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #1e293b;
        }

        /* Footer */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
        }

        .footer p {
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="report">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $company['name'] }}</div>
            </div>
            <div class="header-right">
                <div class="report-title">Daily Time Record</div>
                <div style="font-size: 8px; color: #6b7280; margin-top: 3px;">
                    {{ $period['date_from'] }} - {{ $period['date_to'] }}
                </div>
            </div>
        </div>

        <!-- Employee Info -->
        <div class="info-grid">
            <div class="info-box">
                <div class="info-box-title">Employee Information</div>
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">{{ $employee['name'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Employee No.</span>
                    <span class="info-value">{{ $employee['number'] }}</span>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Assignment</div>
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value">{{ $employee['department'] ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Position</span>
                    <span class="info-value">{{ $employee['position'] ?? '-' }}</span>
                </div>
            </div>
        </div>

        <!-- DTR Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Status</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Work Hours</th>
                    <th>Late</th>
                    <th>Undertime</th>
                    <th>Overtime</th>
                    <th>Night Diff</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr class="status-{{ strtolower(str_replace(' ', '_', $record['status'])) }}">
                        <td>{{ $record['date'] }}</td>
                        <td>{{ $record['day'] }}</td>
                        <td>{{ $record['status'] }}</td>
                        <td>{{ $record['time_in'] ?: '--:--' }}</td>
                        <td>{{ $record['time_out'] ?: '--:--' }}</td>
                        <td>{{ $record['work_hours'] ?: '-' }}</td>
                        <td>@if($record['late'])<span class="text-red">{{ $record['late'] }}</span>@else - @endif</td>
                        <td>@if($record['undertime'])<span class="text-red">{{ $record['undertime'] }}</span>@else - @endif</td>
                        <td>@if($record['overtime'])<span class="text-green">{{ $record['overtime'] }}</span>@else - @endif</td>
                        <td>{{ $record['night_diff'] ?: '-' }}</td>
                        <td>{{ $record['remarks'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; color: #9ca3af;">No records found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-section">
            <div class="summary-box">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Present</div>
                        <div class="summary-value">{{ $summary['attendance']['present_days'] ?? 0 }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Absent</div>
                        <div class="summary-value">{{ $summary['attendance']['absent_days'] ?? 0 }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Work Hours</div>
                        <div class="summary-value">{{ number_format($summary['time_summary']['total_work_hours'] ?? 0, 1) }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Late</div>
                        <div class="summary-value">{{ number_format($summary['late_undertime']['total_late_hours'] ?? 0, 1) }}h</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Undertime</div>
                        <div class="summary-value">{{ number_format($summary['late_undertime']['total_undertime_hours'] ?? 0, 1) }}h</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Overtime</div>
                        <div class="summary-value">{{ number_format($summary['overtime']['total_overtime_hours'] ?? 0, 1) }}h</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
