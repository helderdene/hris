@props(['url'])
<tr>
<td class="header">
@php $tenantName = tenant()?->name; @endphp
@if($tenantName)
<span style="display: inline-block; font-size: 20px; font-weight: 700; color: #111827; text-decoration: none;">
{{ $tenantName }}
</span>
@else
<a href="{{ $url }}" style="display: inline-block;">
{!! $slot !!}
</a>
@endif
</td>
</tr>
