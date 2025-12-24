@props(['href', 'external' => false])

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'text-blue-600 hover:text-blue-800 hover:underline']) }}
    @if($external) target="_blank" @endif
>
    {{ $slot }}
    @if($external)
        <i class="fas fa-external-link-alt ml-1 text-xs"></i>
    @endif
</a>
