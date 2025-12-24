@extends('admin.layout')

@section('title', 'Image Settings')
@section('page-title', 'Image Settings')

@section('content')
<div class="p-8">
    <!-- Info Banner -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
            <div>
                <h3 class="font-semibold text-blue-900 mb-1">Image Size Configuration</h3>
                <p class="text-sm text-blue-700">
                    Configure the width (in pixels) for each image size variant. All images maintain their aspect ratio and are automatically converted to WebP format.
                </p>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200">
        <div class="p-6">
            <form id="imageSettingsForm" class="space-y-8">
                @csrf

                <!-- Avatar Sizes -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user-circle mr-2 text-blue-500"></i>
                        Avatar Sizes
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Profile picture sizes used throughout the platform</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if(isset($settings['avatar']))
                            @foreach(['small', 'medium', 'large'] as $sizeName)
                                @if(isset($settings['avatar'][$sizeName]))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ ucfirst($sizeName) }}
                                        </label>
                                        <div class="relative">
                                            <input
                                                type="number"
                                                name="avatar_{{ $sizeName }}"
                                                data-id="{{ $settings['avatar'][$sizeName]['id'] }}"
                                                data-type="avatar"
                                                data-size="{{ $sizeName }}"
                                                value="{{ $settings['avatar'][$sizeName]['width'] }}"
                                                min="50"
                                                max="4000"
                                                class="w-32 px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                            <span class="ml-2 text-sm text-gray-500">px</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Thumbnail Sizes -->
                <div class="border-b border-gray-200 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-image mr-2 text-green-500"></i>
                        Thumbnail Sizes
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Post thumbnail images shown in listings and previews</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if(isset($settings['thumbnail']))
                            @foreach(['small', 'medium', 'large'] as $sizeName)
                                @if(isset($settings['thumbnail'][$sizeName]))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ ucfirst($sizeName) }}
                                        </label>
                                        <div class="relative">
                                            <input
                                                type="number"
                                                name="thumbnail_{{ $sizeName }}"
                                                data-id="{{ $settings['thumbnail'][$sizeName]['id'] }}"
                                                data-type="thumbnail"
                                                data-size="{{ $sizeName }}"
                                                value="{{ $settings['thumbnail'][$sizeName]['width'] }}"
                                                min="50"
                                                max="4000"
                                                class="w-32 px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                            <span class="ml-2 text-sm text-gray-500">px</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Inline Sizes -->
                <div class="pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-file-image mr-2 text-purple-500"></i>
                        Inline Sizes
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">Images uploaded within posts and comments</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @if(isset($settings['inline']))
                            @foreach(['small', 'medium', 'large'] as $sizeName)
                                @if(isset($settings['inline'][$sizeName]))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ ucfirst($sizeName) }}
                                        </label>
                                        <div class="relative">
                                            <input
                                                type="number"
                                                name="inline_{{ $sizeName }}"
                                                data-id="{{ $settings['inline'][$sizeName]['id'] }}"
                                                data-type="inline"
                                                data-size="{{ $sizeName }}"
                                                value="{{ $settings['inline'][$sizeName]['width'] }}"
                                                min="50"
                                                max="4000"
                                                class="w-32 px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                            <span class="ml-2 text-sm text-gray-500">px</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <button type="button"
                            id="resetButton"
                            class="px-4 py-2 text-orange-700 bg-orange-100 rounded-lg hover:bg-orange-200 transition-colors">
                        <i class="fas fa-undo mr-2"></i>
                        Reset to Defaults
                    </button>

                    <div class="flex items-center space-x-4">
                        <a href="{{ route('admin.dashboard') }}"
                           class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>

            <!-- Status Messages -->
            <div id="statusMessage" class="mt-4 hidden"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('imageSettingsForm');
    const resetButton = document.getElementById('resetButton');
    const statusMessage = document.getElementById('statusMessage');

    // Handle form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const inputs = form.querySelectorAll('input[type="number"]');
        const settings = [];

        inputs.forEach(input => {
            settings.push({
                id: parseInt(input.dataset.id),
                width: parseInt(input.value)
            });
        });

        try {
            const response = await fetch('/api/v1/admin/image-settings/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                credentials: 'same-origin',
                body: JSON.stringify({ settings })
            });

            const data = await response.json();

            if (response.ok) {
                showMessage('success', data.message || 'Settings updated successfully');
            } else {
                showMessage('error', data.message || 'Failed to update settings');
            }
        } catch (error) {
            showMessage('error', 'An error occurred while updating settings');
            console.error('Error:', error);
        }
    });

    // Handle reset button
    resetButton.addEventListener('click', function() {
        showConfirmModal('Are you sure you want to reset all image sizes to their default values?', {
            title: 'Reset Settings',
            type: 'danger',
            confirmText: 'Reset',
            onConfirm: async function() {
                try {
                    const response = await fetch('/api/v1/admin/image-settings/reset', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                        },
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (response.ok) {
                        showMessage('success', data.message || 'Settings reset to defaults');
                        // Reload the page to show updated values
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showMessage('error', data.message || 'Failed to reset settings');
                    }
                } catch (error) {
                    showMessage('error', 'An error occurred while resetting settings');
                    console.error('Error:', error);
                }
            }
        });
    });

    function showMessage(type, message) {
        const bgColor = type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        statusMessage.className = `mt-4 border px-4 py-3 rounded ${bgColor}`;
        statusMessage.innerHTML = `<i class="fas ${icon} mr-2"></i>${message}`;
        statusMessage.classList.remove('hidden');

        setTimeout(() => {
            statusMessage.classList.add('hidden');
        }, 5000);
    }
});
</script>
@endpush
@endsection
