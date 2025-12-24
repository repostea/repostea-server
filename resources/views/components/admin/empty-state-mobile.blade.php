@props(['icon' => 'file-alt', 'message' => 'No items found'])

<div class="p-12 text-center text-gray-500">
    <i class="fas fa-{{ $icon }} text-4xl text-gray-300 mb-2"></i>
    <p>{{ $message }}</p>
</div>
