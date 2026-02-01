<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {{ $employee['name'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1f2937;
        }

        .payslip {
            padding: 20px;
            max-width: 100%;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 15px;
            margin-bottom: 15px;
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
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 9px;
            color: #6b7280;
        }

        .payslip-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Info Sections */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .info-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
            background-color: #f8fafc;
            vertical-align: top;
        }

        .info-box:first-child {
            border-right: 1px solid #e5e7eb;
        }

        .info-box-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }

        .info-label {
            display: table-cell;
            width: 40%;
            color: #6b7280;
            font-size: 9px;
        }

        .info-value {
            display: table-cell;
            width: 60%;
            font-weight: 500;
            font-size: 9px;
        }

        /* Tables */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9px;
        }

        .data-table th {
            background-color: #f1f5f9;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table td.text-right {
            text-align: right;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table .total-row td {
            font-weight: bold;
            border-top: 2px solid #e2e8f0;
            padding-top: 8px;
        }

        /* Summary Box */
        .summary-section {
            margin-top: 20px;
        }

        .summary-box {
            background-color: #ecfdf5;
            border: 1px solid #86efac;
            padding: 15px;
            border-radius: 4px;
        }

        .summary-grid {
            display: table;
            width: 100%;
        }

        .summary-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 0 10px;
        }

        .summary-item:not(:last-child) {
            border-right: 1px solid #86efac;
        }

        .summary-label {
            font-size: 9px;
            color: #059669;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
        }

        .summary-value.deduction {
            color: #dc2626;
        }

        .summary-value.net-pay {
            font-size: 18px;
            color: #047857;
        }

        /* Footer */
        .footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }

        .footer p {
            margin-bottom: 3px;
        }

        /* Page break for bulk printing */
        .page-break {
            page-break-after: always;
        }

        /* Two column layout for earnings/deductions */
        .two-columns {
            display: table;
            width: 100%;
        }

        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }

        .column:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
    </style>
</head>
<body>
    <div class="payslip">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ $company['name'] }}</div>
                @if($company['address'])
                    <div class="company-info">{{ $company['address'] }}</div>
                @endif
                @if($company['tin'])
                    <div class="company-info">TIN: {{ $company['tin'] }}</div>
                @endif
            </div>
            <div class="header-right">
                <div class="payslip-title">Payslip</div>
                <div style="font-size: 9px; color: #6b7280; margin-top: 5px;">
                    {{ $period['name'] }}
                </div>
            </div>
        </div>

        <!-- Employee & Period Info -->
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
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value">{{ $employee['department'] ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Position</span>
                    <span class="info-value">{{ $employee['position'] ?? '-' }}</span>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Pay Period Details</div>
                <div class="info-row">
                    <span class="info-label">Pay Period</span>
                    <span class="info-value">{{ $period['name'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cutoff Start</span>
                    <span class="info-value">{{ $period['cutoff_start'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cutoff End</span>
                    <span class="info-value">{{ $period['cutoff_end'] }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pay Date</span>
                    <span class="info-value">{{ $period['pay_date'] }}</span>
                </div>
            </div>
        </div>

        <!-- Earnings & Deductions Tables -->
        <div class="two-columns">
            <!-- Earnings -->
            <div class="column">
                <div class="section-title">Earnings</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($earnings as $earning)
                            <tr>
                                <td>{{ $earning['description'] }}</td>
                                <td class="text-right">{{ $earning['formatted_amount'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td>Total Earnings</td>
                            <td class="text-right">{{ $summary['formatted_gross'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Deductions -->
            <div class="column">
                <div class="section-title">Deductions</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deductions as $deduction)
                            <tr>
                                <td>{{ $deduction['description'] }}</td>
                                <td class="text-right">{{ $deduction['formatted_amount'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="text-align: center; color: #9ca3af;">No deductions</td>
                            </tr>
                        @endforelse
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="text-right">{{ $summary['formatted_deductions'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Net Pay Summary -->
        <div class="summary-section">
            <div class="summary-box">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Gross Pay</div>
                        <div class="summary-value">{{ $summary['formatted_gross'] }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Deductions</div>
                        <div class="summary-value deduction">-{{ $summary['formatted_deductions'] }}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Net Pay</div>
                        <div class="summary-value net-pay">{{ $summary['formatted_net'] }}</div>
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
