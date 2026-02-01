<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Letter - {{ $candidate->full_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #1f2937;
        }

        .offer-letter {
            padding: 40px;
            max-width: 100%;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 18px;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10px;
            color: #6b7280;
        }

        .meta {
            margin-bottom: 20px;
        }

        .meta p {
            margin-bottom: 3px;
        }

        .content {
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .content p {
            margin-bottom: 10px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .details-table th,
        .details-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .details-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            width: 35%;
            color: #374151;
        }

        .signatures {
            margin-top: 40px;
            display: table;
            width: 100%;
        }

        .signature-block {
            display: table-cell;
            width: 45%;
            padding: 10px;
        }

        .signature-block .label {
            font-size: 9px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .signature-block .name {
            font-weight: bold;
            border-top: 1px solid #374151;
            padding-top: 5px;
            margin-top: 40px;
        }

        .signature-block img {
            max-height: 60px;
            margin-bottom: 5px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="offer-letter">
        <div class="header">
            <h1>OFFER LETTER</h1>
            <p>Confidential</p>
        </div>

        <div class="meta">
            <p><strong>Date:</strong> {{ $offer->created_at?->format('F j, Y') }}</p>
            <p><strong>To:</strong> {{ $candidate->full_name }}</p>
            <p><strong>Position:</strong> {{ $offer->position_title }}</p>
        </div>

        <div class="content">
            {!! $offer->content !!}
        </div>

        <table class="details-table">
            <tr>
                <th>Position</th>
                <td>{{ $offer->position_title }}</td>
            </tr>
            @if($offer->department)
            <tr>
                <th>Department</th>
                <td>{{ $offer->department }}</td>
            </tr>
            @endif
            @if($offer->work_location)
            <tr>
                <th>Work Location</th>
                <td>{{ $offer->work_location }}</td>
            </tr>
            @endif
            @if($offer->employment_type)
            <tr>
                <th>Employment Type</th>
                <td>{{ ucfirst(str_replace('_', ' ', $offer->employment_type)) }}</td>
            </tr>
            @endif
            <tr>
                <th>Salary</th>
                <td>{{ $offer->salary_currency }} {{ number_format($offer->salary, 2) }} ({{ ucfirst($offer->salary_frequency) }})</td>
            </tr>
            @if($offer->benefits)
            <tr>
                <th>Benefits</th>
                <td>{{ is_array($offer->benefits) ? implode(', ', $offer->benefits) : $offer->benefits }}</td>
            </tr>
            @endif
            <tr>
                <th>Start Date</th>
                <td>{{ $offer->start_date?->format('F j, Y') }}</td>
            </tr>
        </table>

        @if($signatures->isNotEmpty())
        <div class="signatures">
            @foreach($signatures as $signature)
            <div class="signature-block">
                <div class="label">{{ ucfirst(str_replace('_', ' ', $signature->signer_type)) }}</div>
                @if($signature->signature_data)
                <img src="{{ $signature->signature_data }}" alt="Signature">
                @endif
                <div class="name">{{ $signature->signer_name }}</div>
                <div class="label">Signed: {{ $signature->signed_at?->format('F j, Y g:i A') }}</div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="footer">
            <p>This document is confidential and intended solely for the named recipient.</p>
            <p>Generated on {{ now()->format('F j, Y g:i A') }}</p>
        </div>
    </div>
</body>
</html>
