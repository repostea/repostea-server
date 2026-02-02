@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<!-- System Health Section -->
@can('admin-only')
<div class="mb-4 md:mb-6">
    <button
        onclick="toggleSystemHealth()"
        class="w-full flex items-center justify-between text-left mb-3 bg-white md:bg-transparent rounded-lg md:rounded-none p-3 md:p-0 shadow-sm md:shadow-none hover:bg-gray-50 md:hover:bg-transparent transition-colors"
    >
        <h3 class="text-base md:text-lg font-semibold text-gray-700">System Health</h3>
        <i id="systemHealthIcon" class="fas fa-chevron-down text-gray-500 text-sm md:hidden transition-transform"></i>
    </button>
    <div id="systemHealthContent" class="hidden md:block">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 pr-3 md:pr-0">
            <!-- Server Load -->
            <div class="bg-white rounded-lg shadow p-3 md:p-4">
                <div class="flex flex-col items-center text-center gap-2">
                    <div class="bg-blue-100 rounded-full p-2 md:p-3 flex-shrink-0">
                        <i class="fas fa-server text-blue-600 text-base md:text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0 w-full">
                        <p class="text-xs text-gray-500 mb-1">Server Load</p>
                        <p class="text-xl md:text-2xl font-bold {{ $systemHealth['server_load'] > 2 ? 'text-red-600' : ($systemHealth['server_load'] > 1 ? 'text-yellow-600' : 'text-green-600') }}">
                            {{ $systemHealth['server_load'] }}
                        </p>
                        <p class="text-xs text-gray-400">1 min avg</p>
                    </div>
                </div>
            </div>

            <!-- Memory Usage -->
            <div class="bg-white rounded-lg shadow p-3 md:p-4">
                <div class="flex flex-col items-center text-center gap-2">
                    <div class="bg-purple-100 rounded-full p-2 md:p-3 flex-shrink-0">
                        <i class="fas fa-memory text-purple-600 text-base md:text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0 w-full">
                        <p class="text-xs text-gray-500 mb-1">Memory Usage</p>
                        <p class="text-xl md:text-2xl font-bold {{ $systemHealth['memory_used_percent'] > 80 ? 'text-red-600' : ($systemHealth['memory_used_percent'] > 60 ? 'text-yellow-600' : 'text-green-600') }}">
                            {{ $systemHealth['memory_used_percent'] }}%
                        </p>
                        <p class="text-xs text-gray-400">RAM</p>
                    </div>
                </div>
            </div>

            <!-- Services Status -->
            <div class="bg-white rounded-lg shadow p-3 md:p-4">
                <div class="flex flex-col items-center text-center gap-2">
                    <div class="bg-{{ $systemHealth['all_services_up'] ? 'green' : 'red' }}-100 rounded-full p-2 md:p-3 flex-shrink-0">
                        <i class="fas fa-{{ $systemHealth['all_services_up'] ? 'check-circle' : 'exclamation-circle' }} text-{{ $systemHealth['all_services_up'] ? 'green' : 'red' }}-600 text-base md:text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0 w-full">
                        <p class="text-xs text-gray-500 mb-1">Services</p>
                        <p class="text-xl md:text-2xl font-bold {{ $systemHealth['all_services_up'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $systemHealth['services_up'] }}/{{ $systemHealth['services_total'] }}
                        </p>
                        <p class="text-xs text-gray-400">{{ $systemHealth['all_services_up'] ? 'All OK' : 'Issues' }}</p>
                    </div>
                </div>
            </div>

            <!-- Link to Full Status -->
            <a href="{{ route('admin.system-status') }}" class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow p-3 md:p-4 hover:from-indigo-600 hover:to-purple-700 transition-colors">
                <div class="flex flex-col items-center text-center gap-2 h-full justify-center">
                    <div class="bg-white bg-opacity-20 rounded-full p-2 md:p-3 flex-shrink-0">
                        <i class="fas fa-chart-line text-white text-base md:text-xl"></i>
                    </div>
                    <div class="text-white flex-1 min-w-0 w-full">
                        <p class="text-xs opacity-90">View Details</p>
                        <p class="text-base md:text-xl font-bold">System Status</p>
                        <p class="text-xs opacity-75 mt-1">Full diagnostics →</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endcan

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 lg:gap-6 mb-4 md:mb-8 pr-3 md:pr-0">
    <!-- Stats Cards -->
    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-blue-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-users text-blue-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Total Users</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['total_users']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-green-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-file-alt text-green-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Total Posts</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['total_posts']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-purple-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-comments text-purple-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Total Comments</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['total_comments']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-yellow-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-flag text-yellow-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Pending Reports</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['pending_reports']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-red-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-ban text-red-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Active Bans</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['active_bans']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-orange-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-orange-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Recent Strikes (7d)</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['recent_strikes']) }}</p>
            </div>
        </div>
    </div>

    @can('admin-only')
    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col items-center text-center gap-2">
            <div class="bg-indigo-100 rounded-full p-2 md:p-3 flex-shrink-0">
                <i class="fas fa-microscope text-indigo-600 text-lg md:text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0 w-full">
                <p class="text-gray-500 text-xs md:text-sm mb-1">Telescope Entries</p>
                <p class="text-xl md:text-3xl font-bold text-gray-900">{{ number_format($stats['telescope_entries']) }}</p>
                <a href="{{ url('/telescope') }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 mt-1 md:mt-2 inline-block">
                    Open Telescope <i class="fas fa-external-link-alt ml-1"></i>
                </a>
            </div>
        </div>
    </div>
    @endcan
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 md:gap-4 lg:gap-8 pr-3 md:pr-0">
    <!-- Recent Reports -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-3 md:px-6 py-3 md:py-4 border-b border-gray-200">
            <h3 class="text-base md:text-lg font-semibold text-gray-900">Pending Reports</h3>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($recentReports as $report)
                <div class="px-3 md:px-6 py-3 md:py-4 hover:bg-gray-50">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs md:text-sm font-medium text-gray-900 truncate">
                                <i class="fas fa-flag text-yellow-500 mr-1"></i>
                                {{ ucfirst($report->reason) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="hidden sm:inline">Reported by </span><strong>{{ $report->reportedBy?->username ?? 'Deleted' }}</strong>
                                <span class="hidden sm:inline"> • </span>
                                <span class="block sm:inline text-xs">{{ $report->created_at->diffForHumans() }}</span>
                            </p>
                            @if($report->description)
                                <p class="text-xs text-gray-600 mt-1 md:mt-2 line-clamp-2">{{ Str::limit($report->description, 100) }}</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.reports') }}" class="ml-2 text-blue-600 hover:text-blue-800 text-xs md:text-sm whitespace-nowrap flex-shrink-0">
                            Review →
                        </a>
                    </div>
                </div>
            @empty
                <div class="px-3 md:px-6 py-6 md:py-8 text-center text-gray-500">
                    <i class="fas fa-check-circle text-3xl md:text-4xl text-green-500 mb-2"></i>
                    <p class="text-sm md:text-base">No pending reports</p>
                </div>
            @endforelse
        </div>
        @if($recentReports->count() > 0)
            <div class="px-3 md:px-6 py-2 md:py-3 border-t border-gray-200 text-center">
                <a href="{{ route('admin.reports') }}" class="text-xs md:text-sm text-blue-600 hover:text-blue-800">
                    View all reports →
                </a>
            </div>
        @endif
    </div>

    <!-- Recent Moderation Actions -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-3 md:px-6 py-3 md:py-4 border-b border-gray-200">
            <h3 class="text-base md:text-lg font-semibold text-gray-900">Recent Moderation Actions</h3>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($recentModerationActions as $log)
                <div class="px-3 md:px-6 py-3 md:py-4 hover:bg-gray-50">
                    <div class="flex items-start">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs md:text-sm font-medium text-gray-900">
                                @if($log->action === 'ban_user')
                                    <i class="fas fa-ban text-red-500 mr-1"></i> <span class="hidden sm:inline">Banned user</span><span class="sm:hidden">Ban</span>
                                @elseif($log->action === 'unban_user')
                                    <i class="fas fa-check text-green-500 mr-1"></i> <span class="hidden sm:inline">Unbanned user</span><span class="sm:hidden">Unban</span>
                                @elseif($log->action === 'give_strike')
                                    <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i> <span class="hidden sm:inline">Gave strike</span><span class="sm:hidden">Strike</span>
                                @elseif($log->action === 'hide_post')
                                    <i class="fas fa-eye-slash text-gray-500 mr-1"></i> <span class="hidden sm:inline">Hidden post</span><span class="sm:hidden">Hide</span>
                                @elseif($log->action === 'delete_post')
                                    <i class="fas fa-trash text-red-500 mr-1"></i> <span class="hidden sm:inline">Deleted post</span><span class="sm:hidden">Delete</span>
                                @else
                                    <i class="fas fa-circle text-blue-500 mr-1"></i> {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <strong>{{ $log->moderator?->username ?? 'System' }}</strong>
                                @if($log->targetUser)
                                    → <strong>{{ $log->targetUser?->username ?? 'Deleted' }}</strong>
                                @endif
                                <span class="hidden sm:inline"> • </span>
                                <span class="block sm:inline">{{ $log->created_at->diffForHumans() }}</span>
                            </p>
                            @if($log->reason)
                                <p class="text-xs text-gray-600 mt-1 italic line-clamp-2">"{{ Str::limit($log->reason, 80) }}"</p>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-3 md:px-6 py-6 md:py-8 text-center text-gray-500">
                    <i class="fas fa-history text-3xl md:text-4xl text-gray-300 mb-2"></i>
                    <p class="text-sm md:text-base">No recent actions</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleSystemHealth() {
        const content = document.getElementById('systemHealthContent');
        const icon = document.getElementById('systemHealthIcon');

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }
</script>
@endpush
