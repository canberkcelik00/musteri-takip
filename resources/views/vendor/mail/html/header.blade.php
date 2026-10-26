@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
<img src="{{ asset('images/cube.png') }}" class="logo" width="32" height="32" alt="" style="display: block; margin: 0 auto 8px;">
{!! $slot !!}
@endif
</a>
</td>
</tr>
