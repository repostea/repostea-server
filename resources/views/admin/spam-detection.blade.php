@extends('admin.layout')

@section('title', 'User Detection')
@section('page-title', 'User Spam Detection')

@section('content')
<div class="space-y-6">
    <!-- Overview Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total Processed -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Total Scanned</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total_processed'] }}</p>
                </div>
            </div>
        </div>

        <!-- Suspicious Count -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Suspicious</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $stats['suspicious_count'] }}</p>
                </div>
            </div>
        </div>

        <!-- High Risk -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-100 rounded-full p-3">
                    <i class="fas fa-skull-crossbones text-red-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">High Risk</p>
                    <p class="text-2xl font-bold text-red-600">{{ $stats['high_risk_count'] }}</p>
                </div>
            </div>
        </div>

        <!-- Last Scan -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Last Scan</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $stats['last_scan']->diffForHumans() }}</p>
                    <p class="text-xs text-gray-400">{{ $stats['last_scan']->format('H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="{{ route('admin.spam-detection') }}" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label for="min_score" class="block text-sm font-medium text-gray-700 mb-1">Minimum Spam Score</label>
                <select name="min_score" id="min_score" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="30" {{ $minScore == 30 ? 'selected' : '' }}>30+ (Low)</option>
                    <option value="50" {{ $minScore == 50 ? 'selected' : '' }}>50+ (Medium)</option>
                    <option value="70" {{ $minScore == 70 ? 'selected' : '' }}>70+ (High)</option>
                    <option value="85" {{ $minScore == 85 ? 'selected' : '' }}>85+ (Critical)</option>
                </select>
            </div>

            <div class="flex-1">
                <label for="hours" class="block text-sm font-medium text-gray-700 mb-1">Activity Window</label>
                <select name="hours" id="hours" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="6" {{ $hours == 6 ? 'selected' : '' }}>Last 6 hours</option>
                    <option value="12" {{ $hours == 12 ? 'selected' : '' }}>Last 12 hours</option>
                    <option value="24" {{ $hours == 24 ? 'selected' : '' }}>Last 24 hours</option>
                    <option value="48" {{ $hours == 48 ? 'selected' : '' }}>Last 48 hours</option>
                    <option value="168" {{ $hours == 168 ? 'selected' : '' }}>Last 7 days</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full md:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors font-medium">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Info Banner -->
    @if(count($suspiciousUsers) === 0)
        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-green-900">No Suspicious Activity Detected</h3>
                    <p class="text-sm text-green-700 mt-1">
                        All scanned users have spam scores below {{ $minScore }}. The automatic spam detection system runs every 15 minutes.
                    </p>
                </div>
            </div>
        </div>
    @else
        <!-- Suspicious Users Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-user-secret mr-2 text-red-500"></i>
                    Suspicious Users ({{ count($suspiciousUsers) }})
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spam Score</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reasons</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($suspiciousUsers as $item)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <!-- User -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            @if($item['user']->avatar)
                                                <img class="h-10 w-10 rounded-full" src="{{ $item['user']->avatar }}" alt="{{ $item['user']->username }}">
                                            @else
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-400"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $item['user']->username }}</div>
                                            <div class="text-xs text-gray-500">ID: {{ $item['user']->id }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Spam Score -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-2xl font-bold
                                        @if($item['spam_score'] >= 85) text-red-600
                                        @elseif($item['spam_score'] >= 70) text-orange-600
                                        @elseif($item['spam_score'] >= 50) text-yellow-600
                                        @else text-green-600
                                        @endif">
                                        {{ $item['spam_score'] }}
                                    </div>
                                </td>

                                <!-- Risk Level -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if($item['risk_level'] === 'critical') bg-red-100 text-red-800
                                        @elseif($item['risk_level'] === 'high') bg-orange-100 text-orange-800
                                        @elseif($item['risk_level'] === 'medium') bg-yellow-100 text-yellow-800
                                        @else bg-green-100 text-green-800
                                        @endif">
                                        <i class="fas fa-{{ $item['risk_level'] === 'critical' ? 'skull' : ($item['risk_level'] === 'high' ? 'exclamation-circle' : 'exclamation-triangle') }} mr-1"></i>
                                        {{ ucfirst($item['risk_level']) }}
                                    </span>
                                </td>

                                <!-- Activity -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="flex flex-col gap-1">
                                        <span><i class="fas fa-file-alt text-blue-500 w-4"></i> {{ $item['recent_posts'] }} posts</span>
                                        <span><i class="fas fa-comment text-green-500 w-4"></i> {{ $item['recent_comments'] }} comments</span>
                                    </div>
                                </td>

                                <!-- Reasons -->
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-700 space-y-1">
                                        @foreach($item['reasons'] as $reason)
                                            <div class="flex items-start">
                                                <i class="fas fa-angle-right text-gray-400 mt-0.5 mr-1 flex-shrink-0"></i>
                                                <span>{{ $reason }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex flex-col gap-2">
                                        <a href="{{ route('admin.users.show', $item['user']->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                            <i class="fas fa-eye mr-1"></i>View Profile
                                        </a>
                                        @if(!$item['user']->is_banned)
                                            <form action="{{ route('admin.users.ban', $item['user']->id) }}" method="POST" onsubmit="return confirmSubmit(this, 'Are you sure you want to ban this user?', {title: 'Ban User', type: 'danger', confirmText: 'Ban'})">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-left">
                                                    <i class="fas fa-ban mr-1"></i>Ban User
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Info Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-2">
            <i class="fas fa-info-circle mr-2"></i>How Spam Detection Works
        </h3>
        <div class="text-sm text-blue-800 space-y-2">
            <p><strong>Automatic Scanning:</strong> The system runs every 15 minutes, scanning users with recent activity.</p>
            <p><strong>Detection Factors:</strong></p>
            <ul class="list-disc list-inside ml-4 space-y-1">
                <li>Rapid-fire posting (multiple posts in short time)</li>
                <li>Duplicate content detection (similar titles/content)</li>
                <li>Account age and karma score</li>
                <li>Post/comment patterns</li>
            </ul>
            <p><strong>Asynchronous Processing:</strong> Detection happens in background, doesn't slow down user actions.</p>
        </div>
    </div>
</div>
@endsection
