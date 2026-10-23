@props(['raw' => false])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 480px) {
.content-cell {
padding: 16px !important;
}

.dg-th,
.dg-td {
font-size: 13px !important;
padding: 8px 6px !important;
}

.dg-title {
font-size: 18px !important;
}

.button {
width: 100% !important;
}
}

@media (prefers-color-scheme: dark) {
body,
.wrapper,
.body {
background-color: #000000 !important;
color: #f5f5f7 !important;
}

.inner-body {
background-color: #1c1c1e !important;
border-color: #38383a !important;
}

.header a,
.dg-title,
.dg-heading,
.dg-total,
.dg-td {
color: #f5f5f7 !important;
}

.dg-summary,
.dg-hint,
.dg-note,
.footer p {
color: #98989d !important;
}

a,
.dg-td a {
color: #409cff !important;
}

.dg-table {
border-color: #38383a !important;
}

.dg-th {
background-color: #1c1c1e !important;
border-color: #38383a !important;
color: #98989d !important;
}

.dg-td {
border-color: #38383a !important;
}

.button,
.button-primary {
background-color: #0a72e0 !important;
border-color: #0a72e0 !important;
color: #ffffff !important;
}

.badge-red {
background-color: #4a2a29 !important;
color: #ffa39d !important;
}

.badge-amber {
background-color: #4a3a1e !important;
color: #ffc25c !important;
}

.badge-green {
background-color: #1f4a2b !important;
color: #6fdc8c !important;
}

.badge-gray {
background-color: #3a3a3c !important;
color: #c7c7cc !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! $raw ? $slot : Illuminate\Mail\Markdown::parse($slot) !!}

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
