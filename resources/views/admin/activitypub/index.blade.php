@extends('admin.layout')

@section('title', 'ActivityPub / Fediverse')
@section('page-title', 'ActivityPub / Fediverse')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Config Status -->
    <div class="bg-white rounded-lg shadow p-4">
        @if($config['enabled'])
            <div class="flex items-center justify-between">
                <div class="flex items-center text-green-700">
                    <i class="fab fa-mastodon text-2xl mr-3"></i>
                    <div>
                        <p class="font-semibold">ActivityPub Enabled</p>
                        <p class="text-sm text-gray-600">{{ $config['actor_id'] }}</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                    @{{ $config['username'] }}
                </span>
            </div>
        @else
            <div class="flex items-center text-red-700">
                <i class="fab fa-mastodon text-2xl mr-3"></i>
                <div>
                    <p class="font-semibold">ActivityPub Disabled</p>
                    <p class="text-sm">Set ACTIVITYPUB_ENABLED=true in .env to enable</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <a href="{{ route('admin.activitypub') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow {{ !$filters['status'] ? 'ring-2 ring-purple-500' : '' }}">
            <p class="text-xs text-gray-500 mb-1">Followers</p>
            <p class="text-2xl font-bold text-purple-600">{{ $stats['followers'] }}</p>
        </a>
        <a href="{{ route('admin.activitypub') }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow {{ !$filters['status'] ? 'ring-2 ring-gray-400' : '' }}">
            <p class="text-xs text-gray-500 mb-1">Total Deliveries</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_deliveries'] }}</p>
        </a>
        <a href="{{ route('admin.activitypub', ['status' => 'delivered']) }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow {{ $filters['status'] === 'delivered' ? 'ring-2 ring-green-500' : '' }}">
            <p class="text-xs text-gray-500 mb-1">Delivered</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['delivered'] }}</p>
        </a>
        <a href="{{ route('admin.activitypub', ['status' => 'pending']) }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow {{ $filters['status'] === 'pending' ? 'ring-2 ring-yellow-500' : '' }}">
            <p class="text-xs text-gray-500 mb-1">Pending</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
        </a>
        <a href="{{ route('admin.activitypub', ['status' => 'failed']) }}" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow {{ $filters['status'] === 'failed' ? 'ring-2 ring-red-500' : '' }}">
            <p class="text-xs text-gray-500 mb-1">Failed</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Followers Panel -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-users mr-2"></i>Followers ({{ $stats['followers'] }})
                    </h2>
                    @if($domains->isNotEmpty())
                        <form method="GET" action="{{ route('admin.activitypub') }}" class="mt-3">
                            @if($filters['status'])
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                            @endif
                            <select name="domain" onchange="this.form.submit()" class="w-full text-sm border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                <option value="">All domains</option>
                                @foreach($domains as $domain)
                                    <option value="{{ $domain }}" {{ $filters['domain'] === $domain ? 'selected' : '' }}>
                                        {{ $domain }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                </div>
                <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                    @forelse($followers as $follower)
                        <div class="px-4 py-3 hover:bg-gray-50">
                            <div class="flex items-center">
                                @if($follower->avatar_url)
                                    <img src="{{ $follower->avatar_url }}" alt="" class="w-10 h-10 rounded-full mr-3">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                                        <i class="fab fa-mastodon text-purple-600"></i>
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $follower->display_name ?? $follower->username ?? 'Unknown' }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">
                                        @{{ $follower->username }}@{{ $follower->domain }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                Followed {{ $follower->followed_at?->diffForHumans() ?? 'unknown' }}
                            </p>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-user-slash text-3xl mb-2"></i>
                            <p>No followers yet</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Deliveries History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-paper-plane mr-2"></i>Deliveries
                            @if($filters['status'])
                                <span class="text-sm font-normal text-gray-500">
                                    - {{ ucfirst($filters['status']) }}
                                </span>
                            @endif
                        </h2>
                        <div class="flex items-center gap-2">
                            @if($filters['status'])
                                <a href="{{ route('admin.activitypub') }}" class="text-sm text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times mr-1"></i>Clear filter
                                </a>
                            @endif
                            <form method="GET" action="{{ route('admin.activitypub') }}" class="flex items-center gap-2">
                                @if($filters['status'])
                                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                @endif
                                @if($filters['domain'])
                                    <input type="hidden" name="domain" value="{{ $filters['domain'] }}">
                                @endif
                                <select name="per_page" onchange="this.form.submit()" class="text-sm border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="25" {{ $filters['per_page'] == 25 ? 'selected' : '' }}>25 / page</option>
                                    <option value="50" {{ $filters['per_page'] == 50 ? 'selected' : '' }}>50 / page</option>
                                    <option value="100" {{ $filters['per_page'] == 100 ? 'selected' : '' }}>100 / page</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    @if($deliveries->isEmpty())
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p>No deliveries found.</p>
                        </div>
                    @else
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Post</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Inbox</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($deliveries as $delivery)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            @if($delivery->post)
                                                <a href="{{ route('admin.posts.view', $delivery->post) }}" class="text-sm font-medium text-blue-600 hover:underline line-clamp-1">
                                                    {{ Str::limit($delivery->post->title, 40) }}
                                                </a>
                                            @else
                                                <span class="text-sm text-gray-400">Post #{{ $delivery->post_id ?? '?' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @php
                                                $inboxDomain = parse_url($delivery->target_inbox, PHP_URL_HOST);
                                            @endphp
                                            <span title="{{ $delivery->target_inbox }}">{{ $inboxDomain }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($delivery->status === 'delivered')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    <i class="fas fa-check mr-1"></i>Delivered
                                                </span>
                                            @elseif($delivery->status === 'pending')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    <i class="fas fa-clock mr-1"></i>Pending
                                                </span>
                                                @if($delivery->attempts > 0)
                                                    <span class="text-xs text-gray-500 block mt-1">{{ $delivery->attempts }} attempts</span>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700">
                                                    <i class="fas fa-times mr-1"></i>Failed
                                                </span>
                                                @if($delivery->last_error)
                                                    <span class="text-xs text-red-500 block mt-1 truncate max-w-32" title="{{ $delivery->last_error }}">
                                                        {{ Str::limit($delivery->last_error, 30) }}
                                                    </span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $delivery->created_at->format('d M H:i') }}
                                            @if($delivery->delivered_at)
                                                <span class="block text-xs text-green-600">
                                                    Delivered {{ $delivery->delivered_at->format('H:i') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        @if($deliveries->hasPages())
                            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                                {{ $deliveries->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
