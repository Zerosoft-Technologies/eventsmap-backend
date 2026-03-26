@props(['url'])

@php
    $logoSrc = config('mail.logo_url') ?: (rtrim((string) config('app.url'), '/') . '/images/marker.png');
@endphp

<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            <img
                src="{{ $logoSrc }}"
                class="logo"
                alt="The Events Map Logo"
            >
        </a>
    </td>
</tr>

