<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
@php
$locale = app()->getLocale();
$aboutUrl = config('app.url') . '/' . $locale . '/about';
$privacyUrl = config('app.url') . '/' . $locale . '/privacy';
@endphp
© {{ date('Y') }} {{ config('app.name') }}. {{ __('notifications.all_rights_reserved') }}

[{{ __('notifications.footer_legal_info') }}]({{ $aboutUrl }}) | [{{ __('notifications.footer_contact_us') }}](mailto:{{ config('mail.from.address') }})

{{ __('notifications.footer_legal_notice') }} [{{ __('notifications.footer_privacy_policy') }}]({{ $privacyUrl }}).
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
