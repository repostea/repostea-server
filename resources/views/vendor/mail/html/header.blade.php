@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
<img src="{{ config('app.url') }}/logo-email.jpg" class="logo" alt="Renegados Logo" style="max-width: 200px; height: auto;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
