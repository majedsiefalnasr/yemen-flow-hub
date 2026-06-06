@props([
    'label' => '',
    'value' => null,
])
<table dir="rtl" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; border-collapse: collapse;">
<tr>
<td style="padding: 8px 0; border-bottom: 1px solid {{ config('email-theme.border') }}; text-align: right; vertical-align: top; color: {{ config('email-theme.locked_gray') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 13px; line-height: 1.6; white-space: nowrap;">{{ $label }}</td>
<td style="padding: 8px 0; border-bottom: 1px solid {{ config('email-theme.border') }}; text-align: left; vertical-align: top; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 14px; font-weight: 600; line-height: 1.6;">{{ $value ?? $slot }}</td>
</tr>
</table>
