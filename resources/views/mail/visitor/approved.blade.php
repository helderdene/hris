<x-mail::message>
# Hello {{ $visit->visitor->first_name }}!

Your visit has been approved.

**Location:** {{ $visit->workLocation?->name }}

**Expected:** {{ $visit->expected_at?->format('M d, Y g:i A') ?? 'Not specified' }}

Please present this QR code at the kiosk upon arrival for quick check-in.

<div style="text-align:center;margin:24px 0">
<img src="https://quickchart.io/qr?text={{ urlencode($visit->qr_token) }}&size=200&margin=1" alt="QR Code" width="200" height="200" style="border:1px solid #e2e8f0;border-radius:8px">
</div>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
