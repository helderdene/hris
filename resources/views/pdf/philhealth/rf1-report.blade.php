<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8px;
            line-height: 1.3;
            color: #1f2937;
        }

        .report {
            padding: 15px;
        }

        .header {
            margin-bottom: 15px;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #059669;
            margin-bottom: 3px;
        }

        .company-info {
            font-size: 8px;
            color: #6b7280;
        }

        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 10px;
        }

        .report-period {
            font-size: 9px;
            color: #6b7280;
            margin-top: 3px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 7px;
        }

        .data-table th {
            background-color: #d1fae5;
            padding: 4px 3px;
            text-align: left;
            font-weight: 600;
            color: #065f46;
            border: 1px solid #a7f3d0;
            white-space: nowrap;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table td {
            padding: 3px;
            border: 1px solid #d1fae5;
        }

        .data-table td.text-right {
            text-align: right;
        }

        .data-table tr:nth-child(even) {
            background-color: #ecfdf5;
        }

        .total-row td {
            font-weight: bold;
            background-color: #d1fae5 !important;
            border-top: 2px solid #6ee7b7;
        }

        .summary-box {
            margin-top: 15px;
            background-color: #ecfdf5;
            border: 1px solid #6ee7b7;
            padding: 10px;
        }

        .summary-title {
            font-weight: bold;
            font-size: 9px;
            color: #047857;
            margin-bottom: 8px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 5px;
        }

        .summary-label {
            font-size: 7px;
            color: #059669;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #047857;
            margin-top: 2px;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="report">
        <div class="header">
            <div class="company-name">{{ $company['name'] }}</div>
            @if($company['address'])
                <div class="company-info">{{ $company['address'] }}</div>
            @endif
            @if($company['philhealth_number'])
                <div class="company-info">PhilHealth Employer No: {{ $company['philhealth_number'] }}</div>
            @endif
            <div class="report-title">{{ $report['title'] }}</div>
            <div class="report-period">{{ $report['period'] }}</div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>PIN</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>M.N.</th>
                    <th>DOB</th>
                    <th>Sex</th>
                    <th class="text-right">Monthly Salary</th>
                    <th class="text-right">EE Share</th>
                    <th class="text-right">ER Share</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNum = 0; @endphp
                @foreach($data as $row)
                    @php $rowNum++; @endphp
                    <tr>
                        <td>{{ $rowNum }}</td>
                        <td>{{ $row->philhealth_number }}</td>
                        <td>{{ $row->last_name }}</td>
                        <td>{{ $row->first_name }}</td>
                        <td>{{ $row->middle_name ? substr($row->middle_name, 0, 1) . '.' : '' }}</td>
                        <td>{{ $row->date_of_birth?->format('m/d/Y') ?? '' }}</td>
                        <td>{{ $row->gender === 'male' ? 'M' : ($row->gender === 'female' ? 'F' : '') }}</td>
                        <td class="text-right">{{ number_format($row->gross_pay, 2) }}</td>
                        <td class="text-right">{{ number_format($row->philhealth_employee, 2) }}</td>
                        <td class="text-right">{{ number_format($row->philhealth_employer, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_contribution, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="7">TOTALS ({{ $totals['employee_count'] }} employees)</td>
                    <td class="text-right">{{ number_format($totals['gross_pay'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['philhealth_employee'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['philhealth_employer'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['total_contribution'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-title">CONTRIBUTION SUMMARY</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Employee Share</div>
                    <div class="summary-value">{{ number_format($totals['philhealth_employee'], 2) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Employer Share</div>
                    <div class="summary-value">{{ number_format($totals['philhealth_employer'], 2) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total</div>
                    <div class="summary-value">{{ number_format($totals['total_contribution'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
