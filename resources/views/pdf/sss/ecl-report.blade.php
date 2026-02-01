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
            border-bottom: 2px solid #ea580c;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #ea580c;
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
            background-color: #fed7aa;
            padding: 4px 3px;
            text-align: left;
            font-weight: 600;
            color: #9a3412;
            border: 1px solid #fdba74;
            white-space: nowrap;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table td {
            padding: 3px;
            border: 1px solid #e2e8f0;
        }

        .data-table td.text-right {
            text-align: right;
        }

        .data-table tr:nth-child(even) {
            background-color: #fff7ed;
        }

        .total-row td {
            font-weight: bold;
            background-color: #fed7aa !important;
            border-top: 2px solid #fb923c;
        }

        .summary-box {
            margin-top: 15px;
            background-color: #fff7ed;
            border: 1px solid #fdba74;
            padding: 10px;
        }

        .summary-title {
            font-weight: bold;
            font-size: 9px;
            color: #9a3412;
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
            color: #c2410c;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #9a3412;
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
            @if($company['sss_number'])
                <div class="company-info">SSS Employer No: {{ $company['sss_number'] }}</div>
            @endif
            <div class="report-title">{{ $report['title'] }}</div>
            <div class="report-period">{{ $report['period'] }}</div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>SSS Number</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>M.N.</th>
                    <th>Suffix</th>
                    <th>Date of Birth</th>
                    <th class="text-right">SS Contribution</th>
                    <th class="text-right">EC Contribution</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNum = 0; @endphp
                @foreach($data as $row)
                    @php $rowNum++; @endphp
                    <tr>
                        <td>{{ $rowNum }}</td>
                        <td>{{ $row->sss_number }}</td>
                        <td>{{ $row->last_name }}</td>
                        <td>{{ $row->first_name }}</td>
                        <td>{{ $row->middle_name ? substr($row->middle_name, 0, 1) . '.' : '' }}</td>
                        <td>{{ $row->suffix ?? '' }}</td>
                        <td>{{ $row->date_of_birth ? $row->date_of_birth->format('m/d/Y') : '' }}</td>
                        <td class="text-right">{{ number_format($row->ss_contribution, 2) }}</td>
                        <td class="text-right">{{ number_format($row->ec_contribution, 2) }}</td>
                        <td class="text-right">{{ number_format($row->ss_contribution + $row->ec_contribution, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="7">TOTALS ({{ $totals['employee_count'] }} employees)</td>
                    <td class="text-right">{{ number_format($totals['ss_contribution'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['ec_contribution'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['total_contribution'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-title">ECL SUMMARY</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">SS Contribution</div>
                    <div class="summary-value">{{ number_format($totals['ss_contribution'], 2) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">EC Contribution</div>
                    <div class="summary-value">{{ number_format($totals['ec_contribution'], 2) }}</div>
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
