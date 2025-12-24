@extends('admin.layout')

@section('title', 'IP Violations - ' . $ip)
@section('page-title', 'Rate Limit Violations: ' . $ip)

@section('content')
<div class="space-y-6">
    <!-- Back Button -->
    <div>
        <x-admin.action-link :href="route('admin.abuse')">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Abuse Monitor
        </x-admin.action-link>
    </div>

    <!-- IP Info Card -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900 font-mono">{{ $ip }}</h3>
                <p class="text-gray-600 mt-1">IP Address Violation Report</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="document.getElementById('blacklistModal').classList.remove('hidden')" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-ban mr-2"></i>
                    Blacklist IP
                </button>
            </div>
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

    <!-- Violations by Action Type -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar mr-2"></i>Violations by Action Type
        </h3>
        @if($violationsByAction->isEmpty())
            <p class="text-gray-500 text-center py-8">No violations found</p>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($violationsByAction as $item)
                <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">{{ $item->action }}</span>
                        <span class="text-2xl font-bold text-red-600">{{ $item->count }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Users from this IP -->
    @if($usersFromIp->isNotEmpty())
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-users mr-2"></i>Users from this IP
        </h3>
        <div class="space-y-3">
            @foreach($usersFromIp as $userViolation)
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                <div>
                    <x-admin.action-link :href="route('admin.abuse.user', $userViolation->user_id)" class="font-semibold italic">
                        {{ $userViolation->user?->username ?? 'Unknown' }}
                    </x-admin.action-link>
                    <p class="text-sm text-gray-600">
                        {{ $userViolation->user?->email ?? 'N/A' }} |
                        Karma: {{ $userViolation->user?->karma_points ?? 0 }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold text-red-600">{{ $userViolation->violation_count }}</p>
                    <p class="text-xs text-gray-500">violations</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
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
                                @if($violation->user)
                                    <a href="{{ route('admin.abuse.user', $violation->user_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                        {{ $violation->user->username }}
                                    </a>
                                @else
                                    <span class="text-sm text-gray-500 italic">Guest</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded bg-red-100 text-red-800">
                                    {{ $violation->action }}
                                </span>
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
</div>

<!-- Blacklist Modal -->
<div id="blacklistModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-ban text-red-600 mr-2"></i>
                Blacklist IP Address
            </h3>
            <form action="{{ route('admin.abuse.blacklist') }}" method="POST">
                @csrf
                <input type="hidden" name="ip_address" value="{{ $ip }}">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Duration (hours)</label>
                    <select name="duration_hours" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="1">1 Hour</option>
                        <option value="24">24 Hours</option>
                        <option value="168">7 Days</option>
                        <option value="720" selected>30 Days</option>
                        <option value="8760">1 Year</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>Repeated rate limit violations</textarea>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Blacklist
                    </button>
                    <button type="button" onclick="document.getElementById('blacklistModal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
