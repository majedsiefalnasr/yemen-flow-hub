@props(['url'])
<tr>
<td align="center" style="padding: 0 0 24px 0;">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none; color: {{ config('email-theme.primary_blue') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 22px; font-weight: 700; letter-spacing: 0.2px;">
{!! $slot !!}
</a>
</td>
</tr>
