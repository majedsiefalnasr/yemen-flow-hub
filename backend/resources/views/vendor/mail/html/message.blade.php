<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer (standard shared path: every themed email inherits the confidentiality notice) --}}
<x-slot:footer>
<x-mail::footer>
<x-email.confidentiality-notice />
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
