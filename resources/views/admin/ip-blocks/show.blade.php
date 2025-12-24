@extends('admin.layout')

@section('title', 'IP Block Details')
@section('page-title', 'IP Block Details')

@section('content')
<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <a href="{{ route('admin.ip-blocks.index') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to IP Blocks
        </a>
    </div>

    <!-- IP Block Info Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-gray-900">
                <code class="px-3 py-1 bg-gray-100 rounded">{{ $ipBlock->ip_address }}</code>
            </h3>
            <div class="flex space-x-2">
                <a href="{{ route('admin.ip-blocks.edit', $ipBlock) }}"
                   class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <button onclick="showRemoveBlockModal()" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i>Remove Block
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Type</label>
                    <div class="mt-1">
                        <span class="px-3 py-1 text-sm rounded
                            {{ $ipBlock->type === 'single' ? 'bg-blue-100 text-blue-800' : '' }}
                            {{ $ipBlock->type === 'range' ? 'bg-purple-100 text-purple-800' : '' }}
                            {{ $ipBlock->type === 'pattern' ? 'bg-orange-100 text-orange-800' : '' }}">
                            {{ ucfirst($ipBlock->type) }}
                        </span>
                    </div>
                </div>

                @if($ipBlock->type === 'range')
                <div>
                    <label class="text-sm font-medium text-gray-500">IP Range</label>
                    <div class="mt-1 text-gray-900">
                        <code class="px-2 py-1 bg-gray-100 rounded text-sm">
                            {{ $ipBlock->ip_range_start }} - {{ $ipBlock->ip_range_end }}
                        </code>
                    </div>
                </div>
                @endif

                <div>
                    <label class="text-sm font-medium text-gray-500">Block Type</label>
                    <div class="mt-1">
                        <span class="px-3 py-1 text-sm rounded
                            {{ $ipBlock->block_type === 'permanent' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($ipBlock->block_type) }}
                        </span>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Status</label>
                    <div class="mt-1">
                        @if($ipBlock->isActive())
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded">
                            <i class="fas fa-check-circle"></i> Active
                        </span>
                        @elseif($ipBlock->isExpired())
                        <span class="px-3 py-1 bg-orange-100 text-orange-800 text-sm rounded">
                            <i class="fas fa-clock"></i> Expired
                        </span>
                        @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded">
                            <i class="fas fa-times-circle"></i> Inactive
                        </span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Blocked Attempts</label>
                    <div class="mt-1 text-2xl font-bold text-red-600">
                        {{ number_format($ipBlock->hit_count) }}
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Reason</label>
                    <div class="mt-1 text-gray-900">{{ $ipBlock->reason }}</div>
                </div>

                @if($ipBlock->notes)
                <div>
                    <label class="text-sm font-medium text-gray-500">Internal Notes</label>
                    <div class="mt-1 text-gray-700 text-sm bg-gray-50 p-3 rounded">
                        {{ $ipBlock->notes }}
                    </div>
                </div>
                @endif

                <div>
                    <label class="text-sm font-medium text-gray-500">Blocked By</label>
                    <div class="mt-1">
                        @if($ipBlock->blockedBy)
                        <a href="{{ route('admin.users.show', $ipBlock->blockedBy) }}"
                           class="text-blue-600 hover:text-blue-800">
                            {{ $ipBlock->blockedBy->username }}
                        </a>
                        @else
                        <span class="text-gray-500">System</span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-500">Created At</label>
                    <div class="mt-1 text-gray-900">
                        {{ $ipBlock->created_at->format('Y-m-d H:i:s') }}
                        <span class="text-sm text-gray-500">({{ $ipBlock->created_at->diffForHumans() }})</span>
                    </div>
                </div>

                @if($ipBlock->expires_at)
                <div>
                    <label class="text-sm font-medium text-gray-500">Expires At</label>
                    <div class="mt-1 text-gray-900">
                        {{ $ipBlock->expires_at->format('Y-m-d H:i:s') }}
                        <span class="text-sm text-gray-500">({{ $ipBlock->expires_at->diffForHumans() }})</span>
                    </div>
                </div>
                @endif

                @if($ipBlock->last_hit_at)
                <div>
                    <label class="text-sm font-medium text-gray-500">Last Blocked Attempt</label>
                    <div class="mt-1 text-gray-900">
                        {{ $ipBlock->last_hit_at->format('Y-m-d H:i:s') }}
                        <span class="text-sm text-gray-500">({{ $ipBlock->last_hit_at->diffForHumans() }})</span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($ipBlock->metadata)
        <div class="mt-6 pt-6 border-t border-gray-200">
            <label class="text-sm font-medium text-gray-500 mb-2 block">Metadata</label>
            <div class="bg-gray-50 p-4 rounded">
                <pre class="text-sm text-gray-700">{{ json_encode($ipBlock->metadata, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Violations from this IP -->
    @if($recentViolations->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Recent Violations ({{ $recentViolations->count() }})
        </h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Agent</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($recentViolations as $violation)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded">
                                {{ $violation->action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="font-medium text-red-600">{{ $violation->attempts }}</span>
                            <span class="text-gray-500">/ {{ $violation->max_attempts }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($violation->user_id)
                            <a href="{{ route('admin.users.show', $violation->user_id) }}"
                               class="text-blue-600 hover:text-blue-800">
                                User #{{ $violation->user_id }}
                            </a>
                            @else
                            <span class="text-gray-400">Anonymous</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate" title="{{ $violation->user_agent }}">
                            {{ $violation->user_agent ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $violation->created_at->format('Y-m-d H:i') }}
                            <div class="text-xs text-gray-400">{{ $violation->created_at->diffForHumans() }}</div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Moderation Logs -->
    @if($moderationLogs->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Related Moderation Actions ({{ $moderationLogs->count() }})
        </h3>
        <div class="space-y-2">
            @foreach($moderationLogs as $log)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div class="flex items-center space-x-4">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                        {{ $log->action }}
                    </span>
                    <span class="text-sm text-gray-700">{{ $log->reason }}</span>
                </div>
                <div class="text-sm text-gray-500">
                    {{ $log->created_at->format('Y-m-d H:i') }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

<!-- Remove IP Block Modal -->
<div id="removeBlockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Remove IP Block</h3>
        <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to remove the IP block for <code class="px-2 py-1 bg-gray-100 rounded text-gray-900 font-mono">{{ $ipBlock->ip_address }}</code>?
        </p>
        <p class="text-sm text-gray-500 mb-6">
            This action cannot be undone. The IP address will be able to access the system again.
        </p>
        <div class="flex justify-end space-x-3">
            <button onclick="hideRemoveBlockModal()" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                Cancel
            </button>
            <form action="{{ route('admin.ip-blocks.destroy', $ipBlock) }}" method="POST" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i>Remove Block
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function showRemoveBlockModal() {
    document.getElementById('removeBlockModal').classList.remove('hidden');
}

function hideRemoveBlockModal() {
    document.getElementById('removeBlockModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('removeBlockModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideRemoveBlockModal();
    }
});
</script>
@endsection
