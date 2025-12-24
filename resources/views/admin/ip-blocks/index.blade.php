@extends('admin.layout')

@section('title', 'IP Blocks')
@section('page-title', 'IP Address Blocking')

@section('content')
<div class="space-y-6">
    <!-- Actions Bar -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div class="flex space-x-2">
                <a href="{{ route('admin.ip-blocks.create') }}" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-ban mr-2"></i>Block New IP
                </a>
                <a href="{{ route('admin.abuse') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fas fa-chart-line mr-2"></i>Abuse Monitoring
                </a>
            </div>

            <!-- Search Form -->
            <form method="GET" class="flex space-x-2">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search IP, reason..."
                       class="px-4 py-2 border rounded w-64">
                <button type="submit" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                    <i class="fas fa-search"></i>
                </button>
                @if(request('search'))
                <a href="{{ route('admin.ip-blocks.index') }}" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                    Clear
                </a>
                @endif
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center space-x-4">
            <div>
                <label class="text-sm font-medium text-gray-700">Status:</label>
                <select onchange="window.location.href='?status='+this.value+'&{{ http_build_query(request()->except('status')) }}'"
                        class="ml-2 px-3 py-1 border rounded">
                    <option value="">All</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Type:</label>
                <select onchange="window.location.href='?type='+this.value+'&{{ http_build_query(request()->except('type')) }}'"
                        class="ml-2 px-3 py-1 border rounded">
                    <option value="">All Types</option>
                    <option value="single" {{ request('type') == 'single' ? 'selected' : '' }}>Single IP</option>
                    <option value="range" {{ request('type') == 'range' ? 'selected' : '' }}>IP Range</option>
                    <option value="pattern" {{ request('type') == 'pattern' ? 'selected' : '' }}>Pattern</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Block Type:</label>
                <select onchange="window.location.href='?block_type='+this.value+'&{{ http_build_query(request()->except('block_type')) }}'"
                        class="ml-2 px-3 py-1 border rounded">
                    <option value="">All</option>
                    <option value="permanent" {{ request('block_type') == 'permanent' ? 'selected' : '' }}>Permanent</option>
                    <option value="temporary" {{ request('block_type') == 'temporary' ? 'selected' : '' }}>Temporary</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Blocks</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_blocks']) }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <i class="fas fa-ban text-red-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Active Blocks</p>
                    <p class="text-3xl font-bold text-green-600">{{ number_format($stats['active_blocks']) }}</p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Permanent</p>
                    <p class="text-3xl font-bold text-red-600">{{ number_format($stats['permanent_blocks']) }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <i class="fas fa-infinity text-red-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Hits Blocked</p>
                    <p class="text-3xl font-bold text-purple-600">{{ number_format($stats['total_hits_blocked']) }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <i class="fas fa-shield-alt text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Most Hit IPs -->
    @if($stats['most_hit_ips']->count() > 0)
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Most Blocked IPs (by hits)</h3>
        <div class="space-y-2">
            @foreach($stats['most_hit_ips'] as $ip)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div class="flex items-center space-x-4">
                    <code class="px-3 py-1 bg-gray-200 rounded text-sm">{{ $ip->ip_address }}</code>
                    <span class="text-sm text-gray-600">{{ $ip->reason }}</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm">
                        <i class="fas fa-times mr-1"></i>{{ number_format($ip->hit_count) }} blocked attempts
                    </span>
                    <a href="{{ route('admin.ip-blocks.show', $ip) }}"
                       class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- IP Blocks Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">IP Blocks ({{ $blocks->total() }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Block Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hits</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blocked By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expires</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($blocks as $block)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <code class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $block->ip_address }}</code>
                            @if($block->type === 'range')
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $block->ip_range_start }} - {{ $block->ip_range_end }}
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded
                                {{ $block->type === 'single' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $block->type === 'range' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $block->type === 'pattern' ? 'bg-orange-100 text-orange-800' : '' }}">
                                {{ ucfirst($block->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded
                                {{ $block->block_type === 'permanent' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ ucfirst($block->block_type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900 max-w-xs truncate" title="{{ $block->reason }}">
                                {{ $block->reason }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 bg-gray-100 rounded text-sm">
                                {{ number_format($block->hit_count) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($block->blockedBy)
                            <span class="text-sm text-gray-600">{{ $block->blockedBy->username }}</span>
                            @else
                            <span class="text-sm text-gray-400">System</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($block->isActive())
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">
                                <i class="fas fa-check-circle"></i> Active
                            </span>
                            @elseif($block->isExpired())
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded">
                                <i class="fas fa-clock"></i> Expired
                            </span>
                            @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded">
                                <i class="fas fa-times-circle"></i> Inactive
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($block->expires_at)
                            {{ $block->expires_at->format('Y-m-d H:i') }}
                            <div class="text-xs text-gray-400">
                                {{ $block->expires_at->diffForHumans() }}
                            </div>
                            @else
                            <span class="text-gray-400">Never</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.ip-blocks.show', $block) }}"
                                   class="text-blue-600 hover:text-blue-900"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.ip-blocks.edit', $block) }}"
                                   class="text-yellow-600 hover:text-yellow-900"
                                   title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="showRemoveBlockModal({{ $block->id }}, '{{ addslashes($block->ip_address) }}')"
                                        class="text-red-600 hover:text-red-900"
                                        title="Remove Block">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-shield-alt text-4xl text-gray-300 mb-4"></i>
                            <p class="text-lg">No IP blocks found</p>
                            <a href="{{ route('admin.ip-blocks.create') }}"
                               class="mt-4 inline-block px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                                Block Your First IP
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($blocks->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $blocks->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Remove IP Block Modal -->
<div id="removeBlockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full mx-4 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Remove IP Block</h3>
        <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to remove the IP block for <code id="removeBlockIp" class="px-2 py-1 bg-gray-100 rounded text-gray-900 font-mono"></code>?
        </p>
        <p class="text-sm text-gray-500 mb-6">
            This action cannot be undone. The IP address will be able to access the system again.
        </p>
        <div class="flex justify-end space-x-3">
            <button onclick="hideRemoveBlockModal()" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                Cancel
            </button>
            <form id="removeBlockForm" method="POST" class="inline">
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
function showRemoveBlockModal(blockId, ipAddress) {
    document.getElementById('removeBlockIp').textContent = ipAddress;
    document.getElementById('removeBlockForm').action = `/admin/ip-blocks/${blockId}`;
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
