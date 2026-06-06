@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $background = match ($color) {
        'success' => config('email-theme.success_text'),
        'error' => config('email-theme.error_text'),
        'warning' => config('email-theme.warning_text'),
        default => config('email-theme.primary_blue'),
    };
@endphp
<table align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 24px 0;">
<tr>
<td align="{{ $align }}">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: inline-block; text-decoration: none; background-color: {{ $background }}; color: {{ config('email-theme.background') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 15px; font-weight: 600; padding: 12px 28px; border-radius: 12px;">{!! $slot !!}</a>
</td>
</tr>
</table>
