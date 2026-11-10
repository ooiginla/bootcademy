@props(['url'])
@php
    $logoUrl = rtrim((string) config('app.url'), '/').'/'.rawurlencode('Bootcademy logo design.png');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" aria-label="Visit {{ config('app.name') }}">
<img src="{{ $logoUrl }}" class="logo" alt="Bootcademy — Every Academy. One Platform.">
</a>
</td>
</tr>
