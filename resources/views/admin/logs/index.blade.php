@extends('admin.layout')

@section('title', 'Moderation Logs')
@section('page-title', 'Moderation Logs')

@section('content')
<div class="bg-white rounded-lg shadow">
    <!-- Filters -->
    <div class="px-6 py-4 border-b border-gray-200">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Action Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                    <select
                        name="action"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $action)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Moderator Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Moderator</label>
                    <select
                        name="moderator_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Moderators</option>
                        @foreach($moderators as $moderator)
                            <option value="{{ $moderator->id }}" {{ request('moderator_id') == $moderator->id ? 'selected' : '' }}>
                                {{ $moderator->username }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input
                        type="date"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input
                        type="date"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex-1">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search by reason..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <a href="{{ route('admin.logs') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moderator</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $log->created_at->format('d M Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $badgeType = 'default';
                                if (str_contains($log->action, 'unban') || str_contains($log->action, 'show')) {
                                    $badgeType = 'success';
                                } elseif (str_contains($log->action, 'ban')) {
                                    $badgeType = 'danger';
                                } elseif (str_contains($log->action, 'strike')) {
                                    $badgeType = 'warning';
                                } elseif (str_contains($log->action, 'hide') || str_contains($log->action, 'delete')) {
                                    $badgeType = 'default';
                                } elseif (str_contains($log->action, 'report')) {
                                    $badgeType = 'info';
                                } else {
                                    $badgeType = 'purple';
                                }
                            @endphp
                            <x-admin.badge :type="$badgeType" :label="ucfirst(str_replace('_', ' ', $log->action))" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($log->moderator)
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-semibold">
                                        {{ strtoupper(substr($log->moderator->username, 0, 1)) }}
                                    </div>
                                    <div class="ml-2">
                                        <x-admin.action-link :href="route('admin.users.show', $log->moderator)" class="text-sm font-medium italic">
                                            {{ $log->moderator->username }}
                                        </x-admin.action-link>
                                    </div>
                                </div>
                            @else
                                <span class="text-sm text-gray-500">System</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($log->targetUser)
                                <x-admin.action-link :href="route('admin.users.show', $log->targetUser)" class="text-sm italic">
                                    {{ $log->targetUser->username }}
                                </x-admin.action-link>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($log->target_type && $log->target_id)
                                <span class="text-xs text-gray-600">
                                    {{ class_basename($log->target_type) }} #{{ $log->target_id }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($log->reason)
                                <p class="text-sm text-gray-700 max-w-md truncate" title="{{ $log->reason }}">
                                    {{ $log->reason }}
                                </p>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                            @if($log->metadata)
                                <button
                                    onclick="showMetadata({{ json_encode($log->metadata) }})"
                                    class="text-xs text-blue-600 hover:text-blue-800 mt-1"
                                >
                                    <i class="fas fa-info-circle mr-1"></i>View metadata
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="history" message="No moderation logs found" colspan="6" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Logs Cards - Mobile -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($logs as $log)
            <div class="p-3">
                @php
                    $badgeType = 'default';
                    if (str_contains($log->action, 'unban') || str_contains($log->action, 'show')) {
                        $badgeType = 'success';
                    } elseif (str_contains($log->action, 'ban')) {
                        $badgeType = 'danger';
                    } elseif (str_contains($log->action, 'strike')) {
                        $badgeType = 'warning';
                    } elseif (str_contains($log->action, 'hide') || str_contains($log->action, 'delete')) {
                        $badgeType = 'default';
                    } elseif (str_contains($log->action, 'report')) {
                        $badgeType = 'info';
                    } else {
                        $badgeType = 'purple';
                    }
                @endphp
                <x-admin.badge :type="$badgeType" :label="ucfirst(str_replace('_', ' ', $log->action))" class="mb-2" />
                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    <div>
                        <x-admin.mobile-label label="Moderator" />
                        @if($log->moderator)
                            <x-admin.action-link :href="route('admin.users.show', $log->moderator)" class="italic">
                                {{ $log->moderator->username }}
                            </x-admin.action-link>
                        @else
                            <span class="text-gray-500">System</span>
                        @endif
                    </div>
                    @if($log->targetUser)
                        <div>
                            <x-admin.mobile-label label="Target User" />
                            <x-admin.action-link :href="route('admin.users.show', $log->targetUser)" class="italic">
                                {{ $log->targetUser->username }}
                            </x-admin.action-link>
                        </div>
                    @endif
                    @if($log->target_type && $log->target_id)
                        <div>
                            <x-admin.mobile-label label="Target" />
                            {{ class_basename($log->target_type) }} #{{ $log->target_id }}
                        </div>
                    @endif
                    <div>
                        <x-admin.mobile-label label="Date" />
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </div>
                    @if($log->reason)
                        <div>
                            <x-admin.mobile-label label="Reason" />
                            <span class="text-gray-700">{{ Str::limit($log->reason, 60) }}</span>
                        </div>
                    @endif
                </div>
                @if($log->metadata)
                    <div class="flex gap-3 text-sm">
                        <button
                            onclick="showMetadata({{ json_encode($log->metadata) }})"
                            class="text-blue-600 hover:text-blue-800 hover:underline"
                        >
                            View metadata
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <x-admin.empty-state-mobile icon="history" message="No moderation logs found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$logs" />

    <!-- Total Results -->
    <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-sm text-gray-600">
        Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} total logs
    </div>
</div>

<!-- Metadata Modal -->
<div id="metadataModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-info-circle mr-2"></i>Log Metadata
        </h3>
        <pre id="metadataContent" class="bg-gray-50 border border-gray-200 rounded p-4 text-sm text-gray-700 overflow-auto max-h-96"></pre>
        <div class="mt-6">
            <button type="button" onclick="hideMetadata()" class="w-full px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                Close
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function showMetadata(metadata) {
        document.getElementById('metadataContent').textContent = JSON.stringify(metadata, null, 2);
        document.getElementById('metadataModal').classList.remove('hidden');
    }
    function hideMetadata() {
        document.getElementById('metadataModal').classList.add('hidden');
    }
</script>
@endpush
