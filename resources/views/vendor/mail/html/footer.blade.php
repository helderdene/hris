<tr>
<td>
<table class="footer" align="center" width="560" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center" style="padding: 32px 0 0;">
@php $tenantName = tenant()?->name; @endphp
@if($tenantName)
<p style="margin: 0; font-size: 12px; color: #b0b5be; line-height: 1.5;">
Sent by {{ config('app.name') }} on behalf of {{ $tenantName }}
</p>
@else
{{ Illuminate\Mail\Markdown::parse($slot) }}
@endif
</td>
</tr>
</table>
</td>
</tr>
