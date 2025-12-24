@props(['icon' => 'file-alt', 'message' => 'No items found', 'colspan' => '6'])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center text-gray-500">
        <i class="fas fa-{{ $icon }} text-4xl text-gray-300 mb-2"></i>
        <p>{{ $message }}</p>
    </td>
</tr>
