@props([
    'variant' => 'neutral',
    'label' => null,
])
@php
    $color = match ($variant) {
        'success' => config('email-theme.success_text'),
        'error' => config('email-theme.error_text'),
        'warning' => config('email-theme.warning_text'),
        'info' => config('email-theme.primary_blue'),
        'voting' => config('email-theme.voting_indigo'),
        'swift' => config('email-theme.swift_cyan'),
        'locked' => config('email-theme.locked_gray'),
        default => config('email-theme.primary_text'),
    };
@endphp
<span style="display: inline-block; padding: 3px 12px; border: 1px solid {{ $color }}; border-radius: 9999px; background-color: {{ config('email-theme.surface') }}; color: {{ $color }}; font-family: {{ config('email-theme.font_family') }}; font-size: 12px; font-weight: 600; line-height: 1.4; white-space: nowrap;">{{ $label ?? $slot }}</span>
