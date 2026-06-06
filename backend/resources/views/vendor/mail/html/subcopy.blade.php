<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin-top: 24px;">
<tr>
<td dir="rtl" style="text-align: right; border-top: 1px solid {{ config('email-theme.border') }}; padding-top: 16px; color: {{ config('email-theme.locked_gray') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 13px; line-height: 1.6;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
</td>
</tr>
</table>
