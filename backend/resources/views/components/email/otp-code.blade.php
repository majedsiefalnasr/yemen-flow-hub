@props([
    'code' => null,
])
{{-- Renders whatever code string it is given; it does not generate or fetch codes. --}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 20px 0;">
<tr>
<td align="center" style="text-align: center; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-radius: 12px; padding: 20px;">
<span dir="ltr" style="display: inline-block; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 30px; font-weight: 700; letter-spacing: 8px; line-height: 1.2;">{{ $code ?? $slot }}</span>
</td>
</tr>
</table>
