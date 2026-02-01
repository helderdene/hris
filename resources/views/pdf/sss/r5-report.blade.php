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
            border-bottom: 2px solid #1e40af;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
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
            background-color: #e2e8f0;
            padding: 4px 3px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border: 1px solid #cbd5e1;
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
            background-color: #f8fafc;
        }

        .total-row td {
            font-weight: bold;
            background-color: #e2e8f0 !important;
            border-top: 2px solid #94a3b8;
        }

        .loan-type-badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6px;
            font-weight: 600;
        }

        .loan-type-salary {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .loan-type-calamity {
            background-color: #fef3c7;
            color: #92400e;
        }

        .loan-type-educational {
            background-color: #d1fae5;
            color: #065f46;
        }

        .loan-type-emergency {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .loan-type-stock {
            background-color: #e9d5ff;
            color: #6b21a8;
        }

        .summary-section {
            margin-top: 15px;
        }

        .summary-title {
            font-weight: bold;
            font-size: 9px;
            color: #1f2937;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .summary-table {
            width: 50%;
            border-collapse: collapse;
            font-size: 8px;
        }

        .summary-table td {
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
        }

        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .grand-total {
            background-color: #ecfdf5;
            border: 1px solid #86efac;
            padding: 10px;
            margin-top: 10px;
            text-align: right;
        }

        .grand-total-label {
            font-size: 9px;
            color: #059669;
        }

        .grand-total-value {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
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
                    <th>Loan Type</th>
                    <th>Reference No.</th>
                    <th class="text-right">Principal</th>
                    <th class="text-right">Quarterly Payment</th>
                    <th>Payment Months</th>
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
                        <td>
                            @php
                                $badgeClass = match($row->loan_type) {
                                    'sss_salary' => 'loan-type-salary',
                                    'sss_calamity' => 'loan-type-calamity',
                                    'sss_educational' => 'loan-type-educational',
                                    'sss_emergency' => 'loan-type-emergency',
                                    'sss_stock_investment' => 'loan-type-stock',
                                    default => 'loan-type-salary'
                                };
                            @endphp
                            <span class="loan-type-badge {{ $badgeClass }}">{{ $row->loan_type_label }}</span>
                        </td>
                        <td>{{ $row->reference_number ?? '-' }}</td>
                        <td class="text-right">{{ number_format($row->principal_amount, 2) }}</td>
                        <td class="text-right">{{ number_format($row->total_payments, 2) }}</td>
                        <td>{{ $row->payment_months }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="6">TOTALS ({{ $totals['employee_count'] }} employees, {{ $totals['loan_count'] }} loans)</td>
                    <td class="text-right"></td>
                    <td class="text-right">{{ number_format($totals['total_payments'], 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        @if(isset($totals['by_loan_type']) && count($totals['by_loan_type']) > 0)
            <div class="summary-section">
                <div class="summary-title">SUMMARY BY LOAN TYPE</div>
                <table class="summary-table">
                    @foreach($totals['by_loan_type'] as $loanType => $summary)
                        <tr>
                            <td>{{ \App\Enums\LoanType::tryFrom($loanType)?->label() ?? $loanType }}</td>
                            <td>{{ $summary['count'] }} loans</td>
                            <td>{{ number_format($summary['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="grand-total">
            <div class="grand-total-label">TOTAL QUARTERLY LOAN AMORTIZATION</div>
            <div class="grand-total-value">PHP {{ number_format($totals['total_payments'], 2) }}</div>
        </div>

        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on {{ $generated_at }}</p>
        </div>
    </div>
</body>
</html>
