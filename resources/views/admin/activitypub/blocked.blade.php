@extends('admin.layout')

@section('title', 'Blocked Instances')
@section('page-title', 'Blocked Instances')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Total Blocks</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Active</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Full Blocks</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['full_blocks'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Silenced</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['silenced'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add New Block Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-plus-circle mr-2"></i>Block Instance
                    </h2>
                </div>
                <form action="{{ route('admin.federation.blocked.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label for="domain" class="block text-sm font-medium text-gray-700 mb-1">Domain *</label>
                        <input type="text" name="domain" id="domain" required
                            placeholder="mastodon.social or https://mastodon.social"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm"
                            value="{{ old('domain') }}">
                        <p class="text-xs text-gray-500 mt-1">Enter domain name or full URL</p>
                        @error('domain')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <textarea name="reason" id="reason" rows="3"
                            placeholder="Why is this instance being blocked?"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Block Type *</label>
                        <div class="space-y-2">
                            <label class="flex items-start">
                                <input type="radio" name="block_type" value="full" class="mt-1 mr-2" checked>
                                <div>
                                    <span class="font-medium text-sm text-gray-900">Full Block</span>
                                    <p class="text-xs text-gray-500">Completely block all federation with this instance</p>
                                </div>
                            </label>
                            <label class="flex items-start">
                                <input type="radio" name="block_type" value="silence" class="mt-1 mr-2">
                                <div>
                                    <span class="font-medium text-sm text-gray-900">Silence</span>
                                    <p class="text-xs text-gray-500">Allow federation but hide content from public feeds</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition-colors font-medium">
                        <i class="fas fa-ban mr-2"></i>Block Instance
                    </button>
                </form>
            </div>
        </div>

        <!-- Blocked Instances List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-list mr-2"></i>Blocked Instances
                        </h2>

                        <!-- Filters -->
                        <form method="GET" action="{{ route('admin.federation.blocked') }}" class="flex items-center gap-2 flex-wrap">
                            <input type="text" name="search" placeholder="Search..."
                                value="{{ $filters['search'] }}"
                                class="border-gray-300 rounded-md shadow-sm text-sm w-32 focus:ring-purple-500 focus:border-purple-500">

                            <select name="status" onchange="this.form.submit()"
                                class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="">All Status</option>
                                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>

                            <select name="block_type" onchange="this.form.submit()"
                                class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="">All Types</option>
                                <option value="full" {{ $filters['block_type'] === 'full' ? 'selected' : '' }}>Full Block</option>
                                <option value="silence" {{ $filters['block_type'] === 'silence' ? 'selected' : '' }}>Silenced</option>
                            </select>

                            @if($filters['search'] || $filters['status'] || $filters['block_type'])
                                <a href="{{ route('admin.federation.blocked') }}" class="text-sm text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    @if($blockedInstances->isEmpty())
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                            <p>No blocked instances found.</p>
                        </div>
                    @else
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domain</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($blockedInstances as $block)
                                    <tr class="hover:bg-gray-50" id="block-row-{{ $block->id }}">
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-gray-900">{{ $block->domain }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($block->block_type === 'full')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700">
                                                    <i class="fas fa-ban mr-1"></i>Full Block
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    <i class="fas fa-volume-mute mr-1"></i>Silenced
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($block->is_active)
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    <i class="fas fa-check mr-1"></i>Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                                    <i class="fas fa-pause mr-1"></i>Inactive
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate" title="{{ $block->reason }}">
                                            {{ $block->reason ?: '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $block->created_at->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <!-- Toggle Active -->
                                                <form action="{{ route('admin.federation.blocked.update', $block) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="reason" value="{{ $block->reason }}">
                                                    <input type="hidden" name="block_type" value="{{ $block->block_type }}">
                                                    <input type="hidden" name="is_active" value="{{ $block->is_active ? '0' : '1' }}">
                                                    <button type="submit" class="text-sm px-2 py-1 rounded {{ $block->is_active ? 'text-yellow-600 hover:bg-yellow-50' : 'text-green-600 hover:bg-green-50' }}"
                                                        title="{{ $block->is_active ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fas fa-{{ $block->is_active ? 'pause' : 'play' }}"></i>
                                                    </button>
                                                </form>

                                                <!-- Edit Modal Trigger -->
                                                <button onclick="openEditModal({{ $block->id }}, '{{ $block->domain }}', '{{ $block->reason }}', '{{ $block->block_type }}')"
                                                    class="text-sm px-2 py-1 rounded text-blue-600 hover:bg-blue-50" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <!-- Delete -->
                                                <form action="{{ route('admin.federation.blocked.destroy', $block) }}" method="POST" class="inline"
                                                    onsubmit="return confirmSubmit(this, 'Are you sure you want to unblock {{ $block->domain }}?', {title: 'Unblock Instance', confirmText: 'Unblock'})">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm px-2 py-1 rounded text-red-600 hover:bg-red-50" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        @if($blockedInstances->hasPages())
                            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                                {{ $blockedInstances->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Edit Block</h3>
        </div>
        <form id="editForm" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                <p id="editDomain" class="text-gray-900 font-medium"></p>
            </div>

            <div>
                <label for="editReason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                <textarea name="reason" id="editReason" rows="3"
                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Block Type</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="block_type" value="full" id="editBlockTypeFull" class="mr-2">
                        <span class="text-sm text-gray-900">Full Block</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="block_type" value="silence" id="editBlockTypeSilence" class="mr-2">
                        <span class="text-sm text-gray-900">Silence</span>
                    </label>
                </div>
            </div>

            <input type="hidden" name="is_active" value="1">

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openEditModal(id, domain, reason, blockType) {
    const modal = document.getElementById('editModal');
    const form = document.getElementById('editForm');
    const domainEl = document.getElementById('editDomain');
    const reasonEl = document.getElementById('editReason');
    const fullEl = document.getElementById('editBlockTypeFull');
    const silenceEl = document.getElementById('editBlockTypeSilence');

    form.action = '{{ url("admin/federation/blocked") }}/' + id;
    domainEl.textContent = domain;
    reasonEl.value = reason || '';

    if (blockType === 'full') {
        fullEl.checked = true;
    } else {
        silenceEl.checked = true;
    }

    modal.classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Close modal on outside click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>
@endpush
@endsection
