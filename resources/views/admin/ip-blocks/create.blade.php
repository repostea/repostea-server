@extends('admin.layout')

@section('title', 'Block IP Address')
@section('page-title', 'Block New IP Address')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.ip-blocks.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- IP Address -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    IP Address <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="ip_address"
                       value="{{ old('ip_address', $suggestedIp ?? '') }}"
                       placeholder="192.168.1.1 or 192.168.*.* for patterns"
                       class="w-full px-4 py-2 border rounded @error('ip_address') border-red-500 @enderror"
                       required>
                @error('ip_address')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Block Type <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="type" value="single" checked class="mr-2"
                               onchange="toggleRangeFields()">
                        <span>Single IP Address</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="type" value="range" class="mr-2"
                               onchange="toggleRangeFields()">
                        <span>IP Range</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="type" value="pattern" class="mr-2"
                               onchange="toggleRangeFields()">
                        <span>Pattern (use * as wildcard, e.g., 192.168.*.* )</span>
                    </label>
                </div>
            </div>

            <!-- IP Range (hidden by default) -->
            <div id="rangeFields" class="hidden space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Range Start IP
                    </label>
                    <input type="text"
                           name="ip_range_start"
                           value="{{ old('ip_range_start') }}"
                           placeholder="192.168.1.1"
                           class="w-full px-4 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Range End IP
                    </label>
                    <input type="text"
                           name="ip_range_end"
                           value="{{ old('ip_range_end') }}"
                           placeholder="192.168.1.255"
                           class="w-full px-4 py-2 border rounded">
                </div>
            </div>

            <!-- Block Duration -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Duration <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="block_type" value="permanent" checked class="mr-2"
                               onchange="toggleExpirationField()">
                        <span>Permanent</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="block_type" value="temporary" class="mr-2"
                               onchange="toggleExpirationField()">
                        <span>Temporary</span>
                    </label>
                </div>
            </div>

            <!-- Expiration Date (hidden by default) -->
            <div id="expirationField" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Expires At
                </label>
                <input type="datetime-local"
                       name="expires_at"
                       value="{{ old('expires_at') }}"
                       class="w-full px-4 py-2 border rounded">
                <p class="text-sm text-gray-500 mt-1">Leave empty for permanent block when temporary is selected</p>
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Reason <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="reason"
                       value="{{ old('reason', $suggestedReason ?? '') }}"
                       placeholder="e.g., Repeated rate limit violations, spam attacks, etc."
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
                          class="w-full px-4 py-2 border rounded">{{ old('notes') }}</textarea>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.ip-blocks.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-ban mr-2"></i>Block IP Address
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Violations (if coming from abuse monitoring) -->
    @if(isset($recentViolations) && $recentViolations && $recentViolations->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Violations from this IP</h3>
        <div class="space-y-2">
            @foreach($recentViolations as $violation)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded text-sm">
                <div>
                    <span class="font-medium">{{ $violation->action }}</span>
                    <span class="text-gray-500 ml-2">{{ $violation->attempts }}/{{ $violation->max_attempts }} attempts</span>
                </div>
                <span class="text-gray-400">{{ $violation->created_at->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<script>
function toggleRangeFields() {
    const type = document.querySelector('input[name="type"]:checked').value;
    const rangeFields = document.getElementById('rangeFields');
    rangeFields.classList.toggle('hidden', type !== 'range');
}

function toggleExpirationField() {
    const blockType = document.querySelector('input[name="block_type"]:checked').value;
    const expirationField = document.getElementById('expirationField');
    expirationField.classList.toggle('hidden', blockType === 'permanent');
}
</script>
@endsection
