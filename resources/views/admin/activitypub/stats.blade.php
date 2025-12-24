@extends('admin.layout')

@section('title', 'Federation Statistics')
@section('page-title', 'Federation Statistics')

@section('content')
<div class="space-y-6">
    <!-- Overview Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <!-- Actors -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Total Actors</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $actorStats['total'] }}</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-full">
                    <i class="fab fa-mastodon text-purple-600"></i>
                </div>
            </div>
        </div>

        <!-- Followers -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Followers</p>
                    <p class="text-2xl font-bold text-blue-600">{{ $followerStats['total'] }}</p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
            </div>
        </div>

        <!-- Remote Users -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Remote Users</p>
                    <p class="text-2xl font-bold text-green-600">{{ $contentStats['remote_users'] }}</p>
                </div>
                <div class="p-3 bg-green-100 rounded-full">
                    <i class="fas fa-user-friends text-green-600"></i>
                </div>
            </div>
        </div>

        <!-- Remote Comments -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Remote Comments</p>
                    <p class="text-2xl font-bold text-orange-600">{{ $contentStats['remote_comments'] }}</p>
                </div>
                <div class="p-3 bg-orange-100 rounded-full">
                    <i class="fas fa-comments text-orange-600"></i>
                </div>
            </div>
        </div>

        <!-- Blocked Instances -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Blocked</p>
                    <p class="text-2xl font-bold text-red-600">{{ $blockedStats['active'] }}</p>
                </div>
                <div class="p-3 bg-red-100 rounded-full">
                    <i class="fas fa-ban text-red-600"></i>
                </div>
            </div>
        </div>

        <!-- Delivery Success Rate -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Success Rate (24h)</p>
                    <p class="text-2xl font-bold {{ $deliveryStats['success_rate'] >= 90 ? 'text-green-600' : ($deliveryStats['success_rate'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ number_format($deliveryStats['success_rate'], 1) }}%
                    </p>
                </div>
                <div class="p-3 {{ $deliveryStats['success_rate'] >= 90 ? 'bg-green-100' : ($deliveryStats['success_rate'] >= 70 ? 'bg-yellow-100' : 'bg-red-100') }} rounded-full">
                    <i class="fas fa-chart-line {{ $deliveryStats['success_rate'] >= 90 ? 'text-green-600' : ($deliveryStats['success_rate'] >= 70 ? 'text-yellow-600' : 'text-red-600') }}"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Actor Breakdown -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fab fa-mastodon mr-2"></i>Actors by Type
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-server text-blue-600"></i>
                            </div>
                            <span class="font-medium">Instance</span>
                        </div>
                        <span class="text-lg font-bold text-gray-900">{{ $actorStats['instance'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user text-green-600"></i>
                            </div>
                            <span class="font-medium">Users</span>
                        </div>
                        <span class="text-lg font-bold text-gray-900">{{ $actorStats['users'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                            <span class="font-medium">Groups</span>
                        </div>
                        <span class="text-lg font-bold text-gray-900">{{ $actorStats['groups'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Federation Engagement -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-heart mr-2"></i>Federation Engagement
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="text-center p-3 bg-pink-50 rounded-lg">
                        <p class="text-2xl font-bold text-pink-600">{{ number_format($engagementStats['likes']) }}</p>
                        <p class="text-xs text-gray-600">Likes</p>
                    </div>
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($engagementStats['shares']) }}</p>
                        <p class="text-xs text-gray-600">Shares</p>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($engagementStats['replies']) }}</p>
                        <p class="text-xs text-gray-600">Replies</p>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($engagementStats['posts_with_engagement']) }}</p>
                        <p class="text-xs text-gray-600">Engaged Posts</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delivery Stats -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-paper-plane mr-2"></i>Deliveries (24h)
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($deliveryStats['total']) }}</p>
                        <p class="text-xs text-gray-600">Total</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ number_format($deliveryStats['success']) }}</p>
                        <p class="text-xs text-gray-600">Success</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-red-600">{{ number_format($deliveryStats['failed']) }}</p>
                        <p class="text-xs text-gray-600">Failed</p>
                    </div>
                </div>

                @if($deliveryStats['total'] > 0)
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $deliveryStats['success_rate'] }}%"></div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2 text-center">
                        {{ number_format($deliveryStats['success_rate'], 1) }}% success rate
                    </p>
                @endif
            </div>
        </div>

        <!-- Top Instances -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-globe mr-2"></i>Top Instances (by Followers)
                </h2>
            </div>
            <div class="divide-y divide-gray-200 max-h-80 overflow-y-auto">
                @forelse($topInstances as $instance)
                    <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fab fa-mastodon text-purple-600 text-sm"></i>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ $instance->instance }}</span>
                        </div>
                        <span class="text-sm font-bold text-purple-600">{{ $instance->count }}</span>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-globe text-3xl mb-2"></i>
                        <p>No followers yet</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Failures -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exclamation-triangle mr-2 text-red-500"></i>Recent Delivery Failures
                </h2>
                <a href="{{ route('admin.activitypub', ['status' => 'failed']) }}" class="text-sm text-purple-600 hover:text-purple-800">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            @if(count($recentFailures) === 0)
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                    <p>No recent delivery failures!</p>
                </div>
            @else
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Instance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">HTTP Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Error</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($recentFailures as $failure)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $failure['instance'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $failure['activity_type'] }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($failure['http_status'])
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700">
                                            {{ $failure['http_status'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-red-600 max-w-xs truncate" title="{{ $failure['error_message'] }}">
                                    {{ Str::limit($failure['error_message'], 50) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $failure['attempt_count'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Blocked Instance Stats -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-ban mr-2 text-red-500"></i>Blocked Instances
                </h2>
                <a href="{{ route('admin.federation.blocked') }}" class="text-sm text-purple-600 hover:text-purple-800">
                    Manage <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-gray-50 rounded-lg">
                    <p class="text-2xl font-bold text-gray-900">{{ $blockedStats['total'] }}</p>
                    <p class="text-xs text-gray-600">Total</p>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600">{{ $blockedStats['active'] }}</p>
                    <p class="text-xs text-gray-600">Active</p>
                </div>
                <div class="text-center p-3 bg-red-50 rounded-lg">
                    <p class="text-2xl font-bold text-red-600">{{ $blockedStats['full'] }}</p>
                    <p class="text-xs text-gray-600">Full Blocks</p>
                </div>
                <div class="text-center p-3 bg-yellow-50 rounded-lg">
                    <p class="text-2xl font-bold text-yellow-600">{{ $blockedStats['silence'] }}</p>
                    <p class="text-xs text-gray-600">Silenced</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
