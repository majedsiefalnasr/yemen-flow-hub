<!DOCTYPE html>
<html dir="rtl" lang="ar" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ config('app.name') }}</title>
<style>
@media only screen and (max-width: 600px) {
.email-inner { width: 100% !important; }
.email-footer { width: 100% !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body style="margin: 0; padding: 0; width: 100%; box-sizing: border-box; background-color: {{ config('email-theme.background') }}; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; line-height: 1.7;">
<table dir="rtl" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0; padding: 0; width: 100%; background-color: {{ config('email-theme.background') }};">
<tr>
<td align="center" style="padding: 24px 0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%;">
{!! $header ?? '' !!}

<tr>
<td align="center" style="padding: 0;">
<table class="email-inner" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation" style="width: 600px; max-width: 600px; margin: 0 auto; background-color: {{ config('email-theme.surface') }}; border: 1px solid {{ config('email-theme.border') }}; border-radius: 12px;">
<tr>
<td dir="rtl" style="padding: 32px; text-align: right; color: {{ config('email-theme.primary_text') }}; font-family: {{ config('email-theme.font_family') }}; font-size: 15px; line-height: 1.7;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
