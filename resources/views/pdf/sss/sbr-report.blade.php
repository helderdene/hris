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
            font-size: 9px;
            line-height: 1.4;
            color: #1f2937;
        }

        .report {
            padding: 20px;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 15px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .company-info {
            font-size: 9px;
            color: #6b7280;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
        }

        .report-period {
            font-size: 10px;
            color: #6b7280;
            margin-top: 5px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 8px;
        }

        .data-table th {
            background-color: #e2e8f0;
            padding: 6px 5px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table th.text-center {
            text-align: center;
        }

        .data-table td {
            padding: 5px;
            border: 1px solid #e2e8f0;
        }

        .data-table td.text-right {
            text-align: right;
        }

        .data-table td.text-center {
            text-align: center;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .total-row td {
            font-weight: bold;
            background-color: #e2e8f0 !important;
            border-top: 2px solid #94a3b8;
        }

        .summary-box {
            margin-top: 20px;
            background-color: #ecfdf5;
            border: 1px solid #86efac;
            padding: 15px;
        }

        .summary-title {
            font-weight: bold;
            font-size: 10px;
            color: #047857;
            margin-bottom: 12px;
            text-align: center;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-row {
            display: table-row;
        }

        .summary-label {
            display: table-cell;
            padding: 4px 10px;
            font-size: 9px;
            color: #059669;
            width: 70%;
        }

        .summary-value {
            display: table-cell;
            padding: 4px 10px;
            text-align: right;
            font-size: 10px;
            font-weight: bold;
            color: #047857;
        }

        .total-remittance {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #86efac;
            text-align: center;
        }

        .total-remittance-label {
            font-size: 10px;
            color: #059669;
            margin-bottom: 5px;
        }

        .total-remittance-value {
            font-size: 18px;
            font-weight: bold;
            color: #047857;
        }

        .certification {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #e5e7eb;
        }

        .certification-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 10px;
        }

        .certification-text {
            font-size: 8px;
            color: #6b7280;
            line-height: 1.5;
        }

        .signature-line {
            margin-top: 30px;
            display: table;
            width: 100%;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
            text-align: center;
        }

        .signature-line-inner {
            border-top: 1px solid #1f2937;
            margin: 0 20px;
            padding-top: 5px;
            font-size: 8px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
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
            @if($company['tin'])
                <div class="company-info">TIN: {{ $company['tin'] }}</div>
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
                    <th>Payroll Period</th>
                    <th class="text-center">Cutoff</th>
                    <th class="text-center">Pay Date</th>
                    <th class="text-center">Employees</th>
                    <th class="text-right">Total Gross</th>
                    <th class="text-right">SS (EE)</th>
                    <th class="text-right">SS (ER)</th>
                    <th class="text-right">Total SS</th>
                    <th class="text-right">EC</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        <td>{{ $row->period_name }}</td>
                        <td class="text-center">{{ $row->cutoff_start->format('M j') }} - {{ $row->cutoff_end->format('M j') }}</td>
                        <td class="text-center">{{ $row->pay_date->format('M j, Y') }}</td>
                        <td class="text-center">{{ $row->employee_count }}</td>
                        <td class="text-right">{{ number_format($row->gross_pay, 2) }}</td>
                        <td class="text-right">{{ number_format($row->sss_employee, 2) }}</td>
                        <td class="text-right">{{ number_format($row->sss_employer, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_ss, 2) }}</td>
                        <td class="text-right">{{ number_format($row->sss_ec, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">GRAND TOTAL</td>
                    <td class="text-center">{{ $totals['employee_count'] }}</td>
                    <td class="text-right">{{ number_format($totals['gross_pay'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['sss_employee'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['sss_employer'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['total_ss'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['sss_ec'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary-box">
            <div class="summary-title">REMITTANCE SUMMARY</div>
            <div class="summary-grid">
                <div class="summary-row">
                    <div class="summary-label">Employee Share (SS)</div>
                    <div class="summary-value">PHP {{ number_format($totals['sss_employee'], 2) }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Employer Share (SS)</div>
                    <div class="summary-value">PHP {{ number_format($totals['sss_employer'], 2) }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Employees' Compensation (EC)</div>
                    <div class="summary-value">PHP {{ number_format($totals['sss_ec'], 2) }}</div>
                </div>
            </div>
            <div class="total-remittance">
                <div class="total-remittance-label">TOTAL AMOUNT FOR REMITTANCE</div>
                <div class="total-remittance-value">PHP {{ number_format($totals['total_contribution'], 2) }}</div>
            </div>
        </div>

        <div class="certification">
            <div class="certification-title">CERTIFICATION</div>
            <div class="certification-text">
                I hereby certify that the above information is correct and complete to the best of my knowledge
                and that the amounts indicated have been/will be remitted to the Social Security System
                for the applicable month/period.
            </div>
            <div class="signature-line">
                <div class="signature-box">
                    <div class="signature-line-inner">Prepared By</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line-inner">Authorized Representative</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is a computer-generated document.</p>
            <p>Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
