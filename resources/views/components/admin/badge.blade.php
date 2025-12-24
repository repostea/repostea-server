@props(['type' => 'default', 'label'])

@php
$classes = match($type) {
    'success', 'published', 'active' => 'bg-green-100 text-green-800',
    'warning', 'draft', 'pending' => 'bg-yellow-100 text-yellow-800',
    'danger', 'hidden', 'banned' => 'bg-red-100 text-red-800',
    'info' => 'bg-blue-100 text-blue-800',
    'purple' => 'bg-purple-100 text-purple-800',
    default => 'bg-gray-100 text-gray-800',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium $classes"]) }}>
    {{ $label ?? $slot }}
</span>
