@props([
    'variant' => 'info',
])
@php
    $accent = match ($variant) {
        'success' => config('email-theme.success_text'),
        'warning' => config('email-theme.warning_text'),
        'error' => config('email-theme.error_text'),
        default => config('email-theme.primary_blue'),
    };
@endphp
<table dir="rtl" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 16px 0;">
<tr>
<td style="text-align: right; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-right: 4px solid {{ $accent }}; border-radius: 12px; padding: 14px 18px; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 14px; line-height: 1.7;">
{{ $slot }}
</td>
</tr>
</table>
