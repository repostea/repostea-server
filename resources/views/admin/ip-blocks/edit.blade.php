@extends('admin.layout')

@section('title', 'Edit IP Block')
@section('page-title', 'Edit IP Block')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Button -->
    <div>
        <a href="{{ route('admin.ip-blocks.show', $ipBlock) }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to IP Block
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-6">
            <h3 class="text-xl font-semibold text-gray-900">Editing IP Block</h3>
            <code class="px-3 py-1 bg-gray-100 rounded text-sm">{{ $ipBlock->ip_address }}</code>
        </div>

        <form action="{{ route('admin.ip-blocks.update', $ipBlock) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- IP Address (Read-only) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    IP Address (Cannot be changed)
                </label>
                <input type="text"
                       value="{{ $ipBlock->ip_address }}"
                       disabled
                       class="w-full px-4 py-2 border rounded bg-gray-100 text-gray-600 cursor-not-allowed">
                <p class="text-sm text-gray-500 mt-1">
                    To change the IP address, create a new block and delete this one.
                </p>
            </div>

            <!-- Block Duration -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Duration <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio"
                               name="block_type"
                               value="permanent"
                               {{ old('block_type', $ipBlock->block_type) === 'permanent' ? 'checked' : '' }}
                               class="mr-2"
                               onchange="toggleExpirationField()">
                        <span>Permanent</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio"
                               name="block_type"
                               value="temporary"
                               {{ old('block_type', $ipBlock->block_type) === 'temporary' ? 'checked' : '' }}
                               class="mr-2"
                               onchange="toggleExpirationField()">
                        <span>Temporary</span>
                    </label>
                </div>
            </div>

            <!-- Expiration Date -->
            <div id="expirationField" class="{{ old('block_type', $ipBlock->block_type) === 'temporary' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Expires At
                </label>
                <input type="datetime-local"
                       name="expires_at"
                       value="{{ old('expires_at', $ipBlock->expires_at ? $ipBlock->expires_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full px-4 py-2 border rounded @error('expires_at') border-red-500 @enderror">
                @error('expires_at')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Reason <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="reason"
                       value="{{ old('reason', $ipBlock->reason) }}"
                       placeholder="e.g., Repeated rate limit violations"
                       class="w-full px-4 py-2 border rounded @error('reason') border-red-500 @enderror"
                       required>
                @error('reason')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Internal Notes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Internal Notes (Optional)
                </label>
                <textarea name="notes"
                          rows="3"
                          placeholder="Additional information for moderators..."
                          class="w-full px-4 py-2 border rounded">{{ old('notes', $ipBlock->notes) }}</textarea>
            </div>

            <!-- Active Status -->
            <div>
                <label class="flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           {{ old('is_active', $ipBlock->is_active) ? 'checked' : '' }}
                           class="mr-2">
                    <span class="text-sm font-medium text-gray-700">Block is Active</span>
                </label>
                <p class="text-sm text-gray-500 mt-1">
                    Uncheck to temporarily disable this block without deleting it.
                </p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.ip-blocks.show', $ipBlock) }}"
                   class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>Update IP Block
                </button>
            </div>
        </form>
    </div>

    <!-- Block Info -->
    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="font-medium">Created:</span> {{ $ipBlock->created_at->format('Y-m-d H:i:s') }}
            </div>
            <div>
                <span class="font-medium">Blocked attempts:</span> {{ number_format($ipBlock->hit_count) }}
            </div>
            <div>
                <span class="font-medium">Blocked by:</span>
                @if($ipBlock->blockedBy)
                {{ $ipBlock->blockedBy->username }}
                @else
                System
                @endif
            </div>
            @if($ipBlock->last_hit_at)
            <div>
                <span class="font-medium">Last hit:</span> {{ $ipBlock->last_hit_at->diffForHumans() }}
            </div>
            @endif
        </div>
    </div>
</div>

<script>
function toggleExpirationField() {
    const blockType = document.querySelector('input[name="block_type"]:checked').value;
    const expirationField = document.getElementById('expirationField');
    expirationField.classList.toggle('hidden', blockType === 'permanent');
}
</script>
@endsection
