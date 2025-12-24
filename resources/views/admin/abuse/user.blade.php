@extends('admin.layout')

@section('title', 'User Violations - ' . $user->username)
@section('page-title', 'Rate Limit Violations: ' . $user->username)

@section('content')
<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <x-admin.action-link :href="route('admin.abuse')">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Abuse Monitor
        </x-admin.action-link>
    </div>

    <!-- User Info Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900 italic">{{ $user->username }}</h3>
                <p class="text-gray-600 mt-1">{{ $user->email }}</p>
                <div class="flex items-center space-x-4 mt-3">
                    <x-admin.badge type="info" label="Karma: {{ $user->karma_points ?? 0 }}" />
                    <x-admin.badge :type="$user->isBanned() ? 'danger' : 'success'" :label="$user->isBanned() ? 'Banned' : 'Active'" />
                    <span class="text-sm text-gray-500">
                        Member since: {{ $user->created_at->format('Y-m-d') }}
                    </span>
                </div>
            </div>
            <x-admin.action-link :href="route('admin.users.show', $user)" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-user mr-2"></i>
                View Full Profile
            </x-admin.action-link>
        </div>
    </div>

    <!-- Time Range Filter -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Time Range</h3>
            <div class="flex space-x-2">
                <a href="?hours=24" class="px-4 py-2 rounded {{ $timeRange == 24 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    24 Hours
                </a>
                <a href="?hours=168" class="px-4 py-2 rounded {{ $timeRange == 168 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    7 Days
                </a>
                <a href="?hours=720" class="px-4 py-2 rounded {{ $timeRange == 720 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    30 Days
                </a>
                <a href="?hours=8760" class="px-4 py-2 rounded {{ $timeRange == 8760 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    All Time
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Total Violations</p>
            <p class="text-3xl font-bold text-red-600">{{ number_format($stats['total_violations']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Unique Actions</p>
            <p class="text-3xl font-bold text-orange-600">{{ number_format($stats['unique_actions']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">First Violation</p>
            <p class="text-lg font-bold text-gray-900">
                @if($stats['first_violation'])
                    {{ \Carbon\Carbon::parse($stats['first_violation'])->format('Y-m-d') }}
                @else
                    Never
                @endif
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-gray-500 text-sm">Last Violation</p>
            <p class="text-lg font-bold text-gray-900">
                @if($stats['last_violation'])
                    {{ \Carbon\Carbon::parse($stats['last_violation'])->diffForHumans() }}
                @else
                    Never
                @endif
            </p>
        </div>
    </div>

    <!-- Violations by Action Type -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-pie mr-2"></i>Violations by Action Type
        </h3>
        @if($violationsByAction->isEmpty())
            <p class="text-gray-500 text-center py-8">No violations found</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($violationsByAction as $item)
                <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">{{ $item->action }}</span>
                        <span class="text-2xl font-bold text-red-600">{{ $item->count }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        Last: {{ \Carbon\Carbon::parse($item->last_violation)->diffForHumans() }}
                    </p>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Detailed Violations Log -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-list mr-2"></i>Detailed Violations Log
        </h3>
        @if($violations->isEmpty())
            <p class="text-gray-500 text-center py-8">No violations found</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Endpoint</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($violations as $violation)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $violation->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.badge type="danger" :label="$violation->action" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-admin.action-link :href="route('admin.abuse.ip', $violation->ip_address)" class="text-sm font-mono">
                                    {{ $violation->ip_address }}
                                </x-admin.action-link>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $violation->endpoint ?? 'N/A' }}
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

            <!-- Pagination -->
            <div class="mt-6">
                {{ $violations->links() }}
            </div>
        @endif
    </div>

    <!-- Admin Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-gavel mr-2"></i>Administrative Actions
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @if(!$user->isBanned())
            <form action="{{ route('admin.users.ban', $user) }}" method="POST" onsubmit="return confirmSubmit(this, 'Are you sure you want to ban this user?', {title: 'Ban User', type: 'danger', confirmText: 'Ban'})">
                @csrf
                <input type="hidden" name="type" value="temporary">
                <input type="hidden" name="reason" value="Excessive rate limit violations">
                <input type="hidden" name="duration_days" value="7">
                <button type="submit" class="w-full px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-ban mr-2"></i>
                    Ban User (7 days)
                </button>
            </form>
            @else
            <form action="{{ route('admin.users.unban', $user) }}" method="POST">
                @csrf
                <button type="submit" class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-check mr-2"></i>
                    Unban User
                </button>
            </form>
            @endif

            <form action="{{ route('admin.users.strike', $user) }}" method="POST" onsubmit="return confirmSubmit(this, 'Are you sure you want to give this user a strike?', {title: 'Give Strike', type: 'danger', confirmText: 'Give Strike'})">
                @csrf
                <input type="hidden" name="type" value="major">
                <input type="hidden" name="reason" value="Rate limit abuse">
                <input type="hidden" name="duration_days" value="30">
                <button type="submit" class="w-full px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Give Strike
                </button>
            </form>

            <a href="{{ route('admin.users.show', $user) }}" class="block">
                <button type="button" class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-user mr-2"></i>
                    View Full Profile
                </button>
            </a>
        </div>
    </div>
</div>
@endsection
