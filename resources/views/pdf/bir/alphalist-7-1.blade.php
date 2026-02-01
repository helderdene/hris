<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alphalist Schedule 7.1 - Employees with Tax Withheld</title>
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
            border-bottom: 2px solid #8B0000;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #8B0000;
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

        .report-subtitle {
            font-size: 9px;
            color: #374151;
            margin-top: 3px;
        }

        .report-period {
            font-size: 9px;
            color: #6b7280;
            margin-top: 3px;
        }

        .bir-logo {
            font-size: 10px;
            font-weight: bold;
            color: #8B0000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .schedule-badge {
            display: inline-block;
            background-color: #dc2626;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 3px;
            margin-left: 8px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 7px;
        }

        .data-table th {
            background-color: #8B0000;
            color: #ffffff;
            padding: 4px 3px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #6B0000;
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
            background-color: #fff5f5;
        }

        .total-row td {
            font-weight: bold;
            background-color: #fecaca !important;
            border-top: 2px solid #8B0000;
        }

        .summary-box {
            margin-top: 15px;
            background-color: #fff5f5;
            border: 1px solid #fecaca;
            padding: 10px;
        }

        .summary-title {
            font-weight: bold;
            font-size: 9px;
            color: #8B0000;
            margin-bottom: 8px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 5px;
        }

        .summary-label {
            font-size: 7px;
            color: #991b1b;
            text-transform: uppercase;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #8B0000;
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

        .schedule-description {
            margin-top: 10px;
            padding: 8px;
            background-color: #fef2f2;
            border-left: 3px solid #8B0000;
            font-size: 8px;
            color: #7f1d1d;
        }
    </style>
</head>
<body>
    <div class="report">
        <div class="header">
            <div class="bir-logo">Bureau of Internal Revenue</div>
            <div class="company-name">{{ $company['name'] }}</div>
            @if($company['address'])
                <div class="company-info">{{ $company['address'] }}</div>
            @endif
            @if($company['tin'])
                <div class="company-info">TIN: {{ $company['tin'] }}</div>
            @endif
            <div class="report-title">
                {{ $report['title'] }}
                <span class="schedule-badge">Schedule 7.1</span>
            </div>
            <div class="report-subtitle">Employees Receiving Compensation Income Subject to Withholding Tax</div>
            <div class="report-period">Tax Year: {{ $report['period'] }}</div>
        </div>

        <div class="schedule-description">
            <strong>Schedule 7.1</strong> - List of employees with compensation income above the statutory minimum wage,
            subject to income tax withholding. These employees have tax withheld from their compensation during the year.
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>TIN</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>M.N.</th>
                    <th>Date of Birth</th>
                    <th class="text-right">Gross</th>
                    <th class="text-right">Non-Taxable</th>
                    <th class="text-right">Taxable</th>
                    <th class="text-right">Tax Withheld</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNum = 0; @endphp
                @foreach($data as $row)
                    @php $rowNum++; @endphp
                    <tr>
                        <td>{{ $rowNum }}</td>
                        <td>{{ $row->tin }}</td>
                        <td>{{ $row->last_name }}</td>
                        <td>{{ $row->first_name }}</td>
                        <td>{{ $row->middle_name ? substr($row->middle_name, 0, 1) . '.' : '' }}</td>
                        <td>{{ $row->date_of_birth ?? '' }}</td>
                        <td class="text-right">{{ number_format($row->gross_compensation, 2) }}</td>
                        <td class="text-right">{{ number_format($row->non_taxable_compensation, 2) }}</td>
                        <td class="text-right">{{ number_format($row->taxable_compensation, 2) }}</td>
                        <td class="text-right">{{ number_format($row->withholding_tax, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="6">TOTALS ({{ $totals['employee_count'] }} employees)</td>
                    <td class="text-right">{{ number_format($totals['gross_compensation'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['non_taxable_compensation'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['taxable_compensation'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['withholding_tax'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-title">SCHEDULE 7.1 SUMMARY</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Total Employees</div>
                    <div class="summary-value">{{ number_format($totals['employee_count']) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Gross Compensation</div>
                    <div class="summary-value">{{ number_format($totals['gross_compensation'], 2) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Taxable Compensation</div>
                    <div class="summary-value">{{ number_format($totals['taxable_compensation'], 2) }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Tax Withheld</div>
                    <div class="summary-value">{{ number_format($totals['withholding_tax'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Alphalist Schedule 7.1 - Employees with Tax Withheld</p>
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
