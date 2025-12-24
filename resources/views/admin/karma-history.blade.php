@extends('admin.layout')

@section('title', 'Karma History')
@section('page-title', 'Karma History Audit Log')

@section('content')

<!-- User Karma Summary (if filtering by user) -->
@if(isset($userKarma))
<div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg shadow-lg mb-6">
    <div class="px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="text-white">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-user mr-2"></i>{{ $userKarma['username'] }}
                </h3>
                <p class="text-purple-100 text-sm">ID: {{ $userKarma['user_id'] }}</p>
            </div>
            <div class="text-right">
                <div class="text-white text-3xl font-bold">{{ number_format($userKarma['current_karma']) }}</div>
                <div class="text-purple-100 text-sm">Current Karma</div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Statistics -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-6 mb-4 md:mb-6">
    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
            <div class="w-full">
                <p class="text-gray-500 text-xs md:text-sm">Total Trans.</p>
                <p class="text-lg md:text-2xl font-bold text-gray-900">{{ number_format($stats['total_transactions']) }}</p>
                <p class="text-gray-400 text-xs mt-1">Total transactions</p>
            </div>
            <div class="hidden md:block bg-blue-100 rounded-full p-2.5">
                <i class="fas fa-exchange-alt text-blue-600 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
            <div class="w-full">
                <p class="text-gray-500 text-xs md:text-sm">Earned</p>
                <p class="text-lg md:text-2xl font-bold text-green-600">+{{ number_format($stats['total_positive']) }}</p>
                <p class="text-gray-400 text-xs mt-1">Total positive karma</p>
            </div>
            <div class="hidden md:block bg-green-100 rounded-full p-2.5">
                <i class="fas fa-arrow-up text-green-600 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
            <div class="w-full">
                <p class="text-gray-500 text-xs md:text-sm">Lost</p>
                <p class="text-lg md:text-2xl font-bold text-red-600">{{ number_format($stats['total_negative']) }}</p>
                <p class="text-gray-400 text-xs mt-1">Total negative karma</p>
            </div>
            <div class="hidden md:block bg-red-100 rounded-full p-2.5">
                <i class="fas fa-arrow-down text-red-600 text-lg"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-3 md:p-6">
        <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
            <div class="w-full">
                <p class="text-gray-500 text-xs md:text-sm">Active Users</p>
                <p class="text-lg md:text-2xl font-bold text-purple-600">{{ number_format($stats['unique_users']) }}</p>
                <p class="text-gray-400 text-xs mt-1">Users with karma</p>
            </div>
            <div class="hidden md:block bg-purple-100 rounded-full p-2.5">
                <i class="fas fa-users text-purple-600 text-lg"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">
            <i class="fas fa-filter mr-2"></i>Filters
        </h3>
    </div>
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                <input
                    type="text"
                    name="user"
                    value="{{ request('user') }}"
                    placeholder="Search by username..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Transactions</option>
                    <option value="positive" {{ request('type') === 'positive' ? 'selected' : '' }}>Positive (+)</option>
                    <option value="negative" {{ request('type') === 'negative' ? 'selected' : '' }}>Negative (-)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                <input
                    type="date"
                    name="from"
                    value="{{ request('from') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                <input
                    type="date"
                    name="to"
                    value="{{ request('to') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>

            <div class="md:col-span-4 flex gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Apply Filters
                </button>
                <a href="{{ route('admin.karma-history') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="bg-white rounded-lg shadow">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">
            <i class="fas fa-list mr-2"></i>Transaction History
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related Content</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($history as $entry)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d') }}</div>
                            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($entry->created_at)->format('H:i:s') }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 italic">{{ $entry->username }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ $entry->user_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-admin.badge :type="$entry->amount > 0 ? 'success' : 'danger'" :label="($entry->amount > 0 ? '+' : '') . $entry->amount" />
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $description = $entry->description ?? $entry->source;
                                $icon = 'fa-circle';
                                $color = 'gray';

                                // Translate if it's a translation key
                                if (str_starts_with($description, 'autogenerated_karma_levels.')) {
                                    $description = __($description);
                                    $icon = 'fa-level-up-alt';
                                    $color = 'purple';
                                } elseif (str_starts_with($description, 'autogenerated_karma_events.') || str_starts_with($description, 'karma_events.')) {
                                    $description = __($description);
                                }

                                // Add icons and colors based on source
                                switch($entry->source) {
                                    case 'post_created':
                                    case 'post':
                                        $icon = 'fa-file-alt';
                                        $color = 'blue';
                                        if (!$entry->description) $description = 'Post created';
                                        break;
                                    case 'comment':
                                    case 'comment_created':
                                        $icon = 'fa-comment';
                                        $color = 'green';
                                        if (!$entry->description) $description = 'Comment posted';
                                        break;
                                    case 'vote':
                                    case 'vote_received':
                                        $icon = 'fa-arrow-up';
                                        $color = 'orange';
                                        if (!$entry->description) $description = $entry->amount > 0 ? 'Vote received' : 'Vote removed';
                                        break;
                                    case 'achievement':
                                        $icon = 'fa-trophy';
                                        $color = 'yellow';
                                        if (!$entry->description) $description = 'Achievement unlocked';
                                        break;
                                    case 'level':
                                    case 'level_up':
                                        $icon = 'fa-level-up-alt';
                                        $color = 'purple';
                                        if (!$entry->description) $description = 'Level up!';
                                        break;
                                    case 'streak':
                                        $icon = 'fa-fire';
                                        $color = 'red';
                                        if (!$entry->description) $description = 'Streak bonus';
                                        break;
                                }
                            @endphp
                            <div class="flex items-center">
                                <i class="fas {{ $icon }} text-{{ $color }}-500 mr-2"></i>
                                <span class="text-sm text-gray-900">{{ $description }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($entry->source && $entry->source_id)
                                <div class="text-sm">
                                    @if($entry->source === 'post')
                                        <i class="fas fa-file-alt text-blue-500 mr-1"></i>
                                        <span class="text-gray-600">Post ID:</span>
                                        <span class="text-gray-900">{{ $entry->source_id }}</span>
                                    @elseif($entry->source === 'comment')
                                        <i class="fas fa-comment text-green-500 mr-1"></i>
                                        <span class="text-gray-600">Comment ID:</span>
                                        <span class="text-gray-900">{{ $entry->source_id }}</span>
                                    @elseif($entry->source === 'achievement')
                                        <i class="fas fa-trophy text-yellow-500 mr-1"></i>
                                        <span class="text-gray-600">Achievement ID:</span>
                                        <span class="text-gray-900">{{ $entry->source_id }}</span>
                                    @else
                                        <i class="fas fa-circle text-gray-400 mr-1"></i>
                                        <span class="text-gray-600">{{ ucfirst($entry->source) }}:</span>
                                        <span class="text-gray-900">{{ $entry->source_id }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-2"></i>
                            <p>No karma transactions found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$history" />
</div>
@endsection
