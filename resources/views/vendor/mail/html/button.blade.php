@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
@php
    $buttonColor = match($color) {
        'green', 'success' => '#16a34a',
        'red', 'error' => '#dc2626',
        default => tenant()?->primary_color ?? '#111827',
    };
@endphp
<!--[if mso]>
<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $url }}" style="height: 48px; v-text-anchor: middle;" arcsize="17%" fillcolor="{{ $buttonColor }}">
    <w:anchorlock/>
    <center style="color: #ffffff; font-family: 'Segoe UI', sans-serif; font-size: 15px; font-weight: 600;">{!! $slot !!}</center>
</v:roundrect>
<![endif]-->
<!--[if !mso]><!-->
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color: {{ $buttonColor }}; border-bottom: 14px solid {{ $buttonColor }}; border-left: 36px solid {{ $buttonColor }}; border-right: 36px solid {{ $buttonColor }}; border-top: 14px solid {{ $buttonColor }}; border-radius: 8px;">{!! $slot !!}</a>
<!--<![endif]-->
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
