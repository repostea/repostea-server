@extends('admin.layout')

@section('title', 'Spam Detection Configuration')
@section('page-title', 'Spam Detection Configuration')

@section('content')
<div class="space-y-6">
    <!-- Info Alert -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    Configure spam detection settings. Changes take effect immediately and are cached for performance.
                    <strong>Note:</strong> After changing these settings, you may want to clear the cache.
                </p>
            </div>
        </div>
    </div>

    <form id="configForm" class="space-y-6">
        @csrf

        @foreach($settings as $category => $categorySettings)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-cog mr-2 text-blue-500"></i>
                        {{ $category }}
                    </h3>
                </div>

                <div class="px-6 py-4 space-y-6">
                    @foreach($categorySettings as $setting)
                        <div class="flex items-start justify-between py-3 border-b border-gray-100 last:border-b-0">
                            <div class="flex-1 mr-4">
                                <label for="setting_{{ $setting->key }}" class="block text-sm font-medium text-gray-900 mb-1">
                                    {{ ucwords(str_replace('_', ' ', $setting->key)) }}
                                </label>
                                @if($setting->description)
                                    <p class="text-sm text-gray-500">{{ $setting->description }}</p>
                                @endif
                            </div>

                            <div class="flex-shrink-0" style="min-width: 200px;">
                                @if($setting->type === 'boolean')
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox"
                                               id="setting_{{ $setting->key }}"
                                               name="settings[{{ $setting->key }}]"
                                               value="1"
                                               {{ (bool)$setting->value ? 'checked' : '' }}
                                               class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-900">
                                            {{ (bool)$setting->value ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </label>
                                @elseif($setting->type === 'integer')
                                    <input type="number"
                                           id="setting_{{ $setting->key }}"
                                           name="settings[{{ $setting->key }}]"
                                           value="{{ $setting->value }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           step="1">
                                @elseif($setting->type === 'float')
                                    <input type="number"
                                           id="setting_{{ $setting->key }}"
                                           name="settings[{{ $setting->key }}]"
                                           value="{{ $setting->value }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           step="0.01"
                                           min="0"
                                           max="1">
                                @else
                                    <input type="text"
                                           id="setting_{{ $setting->key }}"
                                           name="settings[{{ $setting->key }}]"
                                           value="{{ $setting->value }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- Action Buttons -->
        <div class="flex justify-end gap-4">
            <button type="button"
                    onclick="window.location.reload()"
                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors font-medium">
                <i class="fas fa-undo mr-2"></i>Reset
            </button>
            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium">
                <i class="fas fa-save mr-2"></i>Save Configuration
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('configForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const settings = [];

    // Collect all settings
    @foreach($settings as $categorySettings)
        @foreach($categorySettings as $setting)
            @if($setting->type === 'boolean')
                const checkbox_{{ $loop->parent->index }}_{{ $loop->index }} = document.getElementById('setting_{{ $setting->key }}');
                settings.push({
                    key: '{{ $setting->key }}',
                    value: checkbox_{{ $loop->parent->index }}_{{ $loop->index }}.checked ? '1' : '0'
                });
            @else
                const input_{{ $loop->parent->index }}_{{ $loop->index }} = document.getElementById('setting_{{ $setting->key }}');
                settings.push({
                    key: '{{ $setting->key }}',
                    value: input_{{ $loop->parent->index }}_{{ $loop->index }}.value
                });
            @endif
        @endforeach
    @endforeach

    try {
        const response = await fetch('{{ route('admin.spam-configuration.update') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ settings })
        });

        const data = await response.json();

        if (data.success) {
            // Show success message
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            alertDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        } else {
            alert('Error: ' + (data.message || 'Unknown error occurred'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while saving the configuration');
    }
});

// Update toggle labels when clicked
document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const label = this.parentElement.querySelector('span');
        if (label) {
            label.textContent = this.checked ? 'Enabled' : 'Disabled';
        }
    });
});
</script>
@endsection
