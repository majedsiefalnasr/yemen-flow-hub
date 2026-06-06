<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 16px 0;">
<tr>
<td dir="rtl" style="text-align: right; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-radius: 12px; padding: 16px 20px; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 14px; line-height: 1.7;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
</td>
</tr>
</table>
