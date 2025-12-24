@extends('admin.layout')

@section('title', 'Rate Limit Monitor')
@section('page-title', 'Rate Limit Monitoring')

@section('content')
<div class="space-y-6">
    <!-- Time Range Filter -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Time Range</h3>
            <div class="flex space-x-2">
                <a href="?hours=1" class="px-4 py-2 rounded {{ $timeRange == 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    1 Hour
                </a>
                <a href="?hours=24" class="px-4 py-2 rounded {{ $timeRange == 24 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    24 Hours
                </a>
                <a href="?hours=168" class="px-4 py-2 rounded {{ $timeRange == 168 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    7 Days
                </a>
                <a href="?hours=720" class="px-4 py-2 rounded {{ $timeRange == 720 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    30 Days
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Violations</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_violations']) }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Unique Violators</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['unique_violators']) }}</p>
                </div>
                <div class="bg-orange-100 rounded-full p-3">
                    <i class="fas fa-user-times text-orange-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Unique IPs</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['unique_ips']) }}</p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <i class="fas fa-network-wired text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Auto-Bans Issued</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['auto_bans_issued']) }}</p>
                </div>
                <div class="bg-red-100 rounded-full p-3">
                    <i class="fas fa-ban text-red-600 text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Violations by Action -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar mr-2"></i>Violations by Action Type
        </h3>
        @if($violationsByAction->isEmpty())
            <p class="text-gray-500 text-center py-8">No violations in the selected time range</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Violations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unique Users</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unique IPs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($violationsByAction as $violation)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.badge type="info" :label="$violation->action" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                                {{ number_format($violation->total_violations) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ number_format($violation->unique_users) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ number_format($violation->unique_ips) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $percentage = $stats['total_violations'] > 0 ? ($violation->total_violations / $stats['total_violations']) * 100 : 0;
                                @endphp
                                <div class="flex items-center">
                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ number_format($percentage, 1) }}%</span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Top Offenders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Users -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-user-shield mr-2"></i>Top Offending Users
            </h3>
            @if($topOffenders->isEmpty())
                <p class="text-gray-500 text-center py-8">No user violations found</p>
            @else
                <div class="space-y-3">
                    @foreach($topOffenders as $offender)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <x-admin.action-link :href="route('admin.abuse.user', $offender->user_id)" class="font-semibold italic">
                                    {{ $offender->user?->username ?? 'Unknown' }}
                                </x-admin.action-link>
                                @if($offender->user && $offender->user->bans_count > 0)
                                    <x-admin.badge type="danger" label="BANNED" class="ml-2" />
                                @endif
                            </div>
                            <p class="text-sm text-gray-600">
                                Karma: {{ $offender->user?->karma_points ?? 0 }} |
                                Actions: {{ $offender->unique_actions }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-red-600">{{ $offender->violation_count }}</p>
                            <p class="text-xs text-gray-500">violations</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- IPs -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-network-wired mr-2"></i>Suspicious IP Addresses
            </h3>
            @if($suspiciousIps->isEmpty())
                <p class="text-gray-500 text-center py-8">No suspicious IPs found</p>
            @else
                <div class="space-y-3">
                    @foreach($suspiciousIps as $ip)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <div class="flex-1">
                            <x-admin.action-link :href="route('admin.abuse.ip', $ip->ip_address)" class="font-mono font-semibold">
                                {{ $ip->ip_address }}
                            </x-admin.action-link>
                            <p class="text-sm text-gray-600">
                                Actions: {{ $ip->unique_actions }} |
                                Last: {{ \Carbon\Carbon::parse($ip->last_violation)->diffForHumans() }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-orange-600">{{ $ip->violation_count }}</p>
                            <p class="text-xs text-gray-500">violations</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Recent Violations -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-clock mr-2"></i>Recent Violations
            </h3>
            <a href="{{ route('admin.abuse.export', ['hours' => $timeRange]) }}" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                <i class="fas fa-download mr-2"></i>Export CSV
            </a>
        </div>
        @if($recentViolations->isEmpty())
            <p class="text-gray-500 text-center py-8">No recent violations</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recentViolations as $violation)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $violation->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($violation->user)
                                    <x-admin.action-link :href="route('admin.abuse.user', $violation->user_id)" class="text-sm font-medium italic">
                                        {{ $violation->user->username }}
                                    </x-admin.action-link>
                                @else
                                    <span class="text-sm text-gray-500 italic">Guest</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.badge type="danger" :label="$violation->action" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.action-link :href="route('admin.abuse.ip', $violation->ip_address)" class="text-sm font-mono">
                                    {{ $violation->ip_address }}
                                </x-admin.action-link>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="font-semibold text-red-600">{{ $violation->attempts }}</span>
                                <span class="text-gray-500">/ {{ $violation->max_attempts }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-tools mr-2"></i>Administrative Actions
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <form action="{{ route('admin.abuse.cleanup') }}" method="POST" onsubmit="return confirmSubmit(this, 'Are you sure you want to cleanup old logs?', {title: 'Cleanup Logs', type: 'danger', confirmText: 'Cleanup'})">
                    @csrf
                    <button type="submit" class="w-full px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-trash-alt mr-2"></i>
                        Cleanup Old Logs ({{ config('rate_limits.monitoring.log_retention_days', 30) }}+ days)
                    </button>
                </form>
                <p class="text-xs text-gray-500 mt-2">
                    Remove abuse attempt records older than 30 days to free up database space
                </p>
            </div>

            <div>
                <a href="{{ route('admin.abuse.realtime') }}" class="block">
                    <button type="button" class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-chart-line mr-2"></i>
                        Real-Time Statistics
                    </button>
                </a>
                <p class="text-xs text-gray-500 mt-2">
                    View spam and abuse attempt activity updating automatically every 30 seconds
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
