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
background-color: #0e1520 !important;
color: #e6eaf0 !important;
}

.inner-body {
background-color: #151e2b !important;
border-color: #253142 !important;
}

.header a,
.dg-title,
.dg-heading,
.dg-total,
.dg-td {
color: #e6eaf0 !important;
}

.dg-summary,
.dg-hint,
.dg-note,
.footer p {
color: #9aa5b5 !important;
}

a,
.dg-td a {
color: #6f93d6 !important;
}

.dg-table {
border-color: #253142 !important;
}

.dg-th {
background-color: #19222f !important;
border-color: #253142 !important;
color: #9aa5b5 !important;
}

.dg-td {
border-color: #1e2836 !important;
}

.button,
.button-primary {
background-color: #6f93d6 !important;
border-color: #6f93d6 !important;
color: #0b1626 !important;
}

.badge-red {
background-color: #34202b !important;
color: #f5a3a3 !important;
}

.badge-amber {
background-color: #342f24 !important;
color: #f2c46b !important;
}

.badge-green {
background-color: #193232 !important;
color: #8fd3a6 !important;
}

.badge-gray {
background-color: #232d3b !important;
color: #b4bdc9 !important;
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
{!! $slot !!}

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
