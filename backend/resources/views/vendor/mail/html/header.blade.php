@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- PNG (not SVG): Gmail and Outlook don't show SVG images. Served by the storefront. --}}
<img src="{{ rtrim(config('app.frontend_url'), '/') }}/brand/logo-email.png" width="220" height="95" alt="{{ trim(strip_tags($slot)) ?: 'Moto Gears' }}" style="height: 95px; width: 220px; max-width: 100%; border: 0;">
</a>
</td>
</tr>
