@props([
    'url',
    'buttonText' => null,
    'variant' => 'primary',
    'align' => 'center',
])
@php
    $background = match ($variant) {
        'success' => config('email-theme.success_text'),
        'error' => config('email-theme.error_text'),
        'warning' => config('email-theme.warning_text'),
        default => config('email-theme.primary_blue'),
    };
@endphp
<table dir="rtl" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 20px 0;">
<tr>
<td style="text-align: right; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-radius: 12px; padding: 20px 22px; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 14px; line-height: 1.7;">
{{ $slot }}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin-top: 16px;">
<tr>
<td align="{{ $align }}">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none; background-color: {{ $background }}; color: {{ config('email-theme.background') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 15px; font-weight: 600; padding: 12px 28px; border-radius: 12px;">{{ $buttonText ?? $url }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
