@extends('admin.layout')

@section('title', 'Detection Logs')
@section('page-title', 'Spam Detection Logs')

@section('content')
<div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                    <i class="fas fa-list text-blue-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Total Detections</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Pending Review</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending_review'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                    <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">Today</p>
                    <p class="text-2xl font-bold text-green-600">{{ $stats['today'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-100 rounded-full p-3">
                    <i class="fas fa-calendar-week text-purple-600 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm text-gray-500">This Week</p>
                    <p class="text-2xl font-bold text-purple-600">{{ $stats['this_week'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white rounded-lg shadow">
        <!-- Search and Filters -->
        <x-admin.search-form placeholder="Search detections...">
            <x-slot name="filters">
                <select name="detection_type" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Types</option>
                    <option value="duplicate" {{ request('detection_type') == 'duplicate' ? 'selected' : '' }}>Duplicate Content</option>
                    <option value="rapid_fire" {{ request('detection_type') == 'rapid_fire' ? 'selected' : '' }}>Rapid Fire</option>
                    <option value="high_spam_score" {{ request('detection_type') == 'high_spam_score' ? 'selected' : '' }}>High Spam Score</option>
                </select>

                <select name="reviewed" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="0" {{ request('reviewed') === '0' ? 'selected' : '' }}>Pending Review</option>
                    <option value="1" {{ request('reviewed') === '1' ? 'selected' : '' }}>Reviewed</option>
                </select>

                <select name="days" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Time</option>
                    <option value="1" {{ request('days') == '1' ? 'selected' : '' }}>Last 24 hours</option>
                    <option value="7" {{ request('days') == '7' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30" {{ request('days') == '30' ? 'selected' : '' }}>Last 30 days</option>
                </select>

                @if(request('detection_type') || request('reviewed') || request('days'))
                    <a href="{{ route('admin.spam-logs') }}" class="px-4 md:px-6 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 whitespace-nowrap">
                        <i class="fas fa-times md:mr-2"></i><span class="hidden md:inline">Clear</span>
                    </a>
                @endif
            </x-slot>
        </x-admin.search-form>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                        <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($detections as $detection)
                        @php
                            $content = null;
                            $appLink = null;
                            $locale = auth()->user()->locale ?? 'es';
                            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

                            if ($detection->content_type === 'post') {
                                $content = \App\Models\Post::with(['sub', 'user'])->find($detection->content_id);
                                if ($content) {
                                    $appLink = $frontendUrl . '/' . $locale . $content->getPermalinkUrl();
                                }
                            } elseif ($detection->content_type === 'comment') {
                                $content = \App\Models\Comment::with(['post.sub', 'user'])->find($detection->content_id);
                                if ($content && $content->post) {
                                    // Convert comment ID to hexadecimal for anchor
                                    $hexId = dechex($content->id);
                                    $appLink = $frontendUrl . '/' . $locale . $content->post->getPermalinkUrl() . '#c-' . $hexId;
                                }
                            }
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <!-- Date -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-900">{{ $detection->created_at->format('d M Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $detection->created_at->format('H:i') }}</p>
                            </td>

                            <!-- User -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($detection->user)
                                    <x-admin.action-link :href="route('admin.users.show', $detection->user->id)" class="text-sm font-medium block italic">
                                        {{ $detection->user->username }}
                                    </x-admin.action-link>
                                    <span class="text-xs text-gray-500">ID: {{ $detection->user->id }}</span>
                                @else
                                    <span class="text-gray-400 text-sm">User deleted</span>
                                @endif
                            </td>

                            <!-- Type -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @php
                                    $badgeType = match($detection->detection_type) {
                                        'duplicate' => 'info',
                                        'rapid_fire' => 'warning',
                                        'high_spam_score' => 'danger',
                                        default => 'default',
                                    };
                                @endphp
                                <x-admin.badge :type="$badgeType" :label="ucfirst(str_replace('_', ' ', $detection->detection_type))" />
                            </td>

                            <!-- Content -->
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    @if($detection->content_type === 'comment' && $content && $content->post)
                                        <div class="text-xs font-medium text-gray-700" title="{{ $content->post->title }}">
                                            {{ Str::limit($content->post->title, 35) }}
                                        </div>
                                    @endif
                                    @if($content)
                                        @php
                                            $contentText = $detection->content_type === 'comment'
                                                ? $content->content
                                                : ($content->content ?? $content->title ?? '');
                                        @endphp
                                        <p class="text-sm text-gray-900 max-w-xs line-clamp-2">
                                            {{ Str::limit($contentText, 80) }}
                                        </p>
                                        @if($appLink)
                                            <x-admin.action-link :href="$appLink" :external="true" class="text-xs">
                                                View in app
                                            </x-admin.action-link>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400 italic">Content deleted</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Details -->
                            <td class="hidden lg:table-cell px-6 py-4 text-sm text-gray-700">
                                <div class="space-y-1">
                                    @if($detection->similarity)
                                        <div class="flex items-center text-xs">
                                            <i class="fas fa-percent text-blue-500 mr-1"></i>
                                            <span class="font-semibold">{{ round($detection->similarity * 100) }}% similar</span>
                                        </div>
                                    @endif
                                    @if($detection->metadata && isset($detection->metadata['duplicate_of_id']))
                                        @php
                                            $duplicateContent = null;
                                            if ($detection->content_type === 'comment') {
                                                $duplicateContent = \App\Models\Comment::find($detection->metadata['duplicate_of_id']);
                                            } elseif ($detection->content_type === 'post') {
                                                $duplicateContent = \App\Models\Post::find($detection->metadata['duplicate_of_id']);
                                            }
                                        @endphp
                                        <div class="text-xs text-gray-600">
                                            <span class="text-gray-500">Duplicate of</span>
                                            <span class="font-medium text-gray-700">{{ ucfirst($detection->content_type) }} #{{ $detection->metadata['duplicate_of_id'] }}</span>
                                        </div>
                                        @if($duplicateContent)
                                            <div class="text-xs text-gray-500 italic bg-gray-50 p-1 rounded mt-1">
                                                "{{ Str::limit($duplicateContent->body ?? $duplicateContent->title ?? '', 40) }}"
                                            </div>
                                        @endif
                                    @endif
                                    @if($detection->spam_score)
                                        <div class="flex items-center text-xs">
                                            <i class="fas fa-chart-line text-red-500 mr-1"></i>
                                            Score: {{ $detection->spam_score }}
                                        </div>
                                    @endif
                                    @if($detection->reasons && count($detection->reasons) > 0)
                                        <div class="text-xs text-gray-600 mt-1">
                                            @foreach($detection->reasons as $reason)
                                                <div class="flex items-start">
                                                    <i class="fas fa-angle-right text-gray-400 mr-1 mt-0.5"></i>
                                                    <span>{{ $reason }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($detection->reviewed)
                                    <div class="flex flex-col gap-1">
                                        <x-admin.badge type="success" label="Reviewed" />
                                        @if($detection->action_taken)
                                            <span class="text-xs text-gray-500">{{ ucfirst($detection->action_taken) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <x-admin.badge type="pending" label="Pending" />
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col gap-2">
                                    @if($content)
                                        <button onclick="showContentModal({{ $detection->id }})" class="text-purple-600 hover:text-purple-800 hover:underline text-left">
                                            View Details
                                        </button>
                                    @endif
                                    @if(!$detection->reviewed)
                                        <button onclick="reviewDetection({{ $detection->id }}, 'ignored')" class="text-green-600 hover:text-green-800 hover:underline text-left">
                                            Mark Reviewed
                                        </button>
                                    @endif
                                </div>

                                <!-- Hidden content for modal -->
                                @if($content)
                                    <div id="content-{{ $detection->id }}" class="hidden">
                                        <div class="space-y-4">
                                            @php
                                                $originalContent = null;
                                                $originalAppLink = null;

                                                // Calculate comment order/position
                                                $detectedCommentOrder = null;
                                                $originalCommentOrder = null;

                                                if ($detection->content_type === 'comment' && $content->post) {
                                                    $detectedCommentOrder = \App\Models\Comment::where('post_id', $content->post_id)
                                                        ->where('id', '<=', $content->id)
                                                        ->count();
                                                }

                                                // Only load original content if this is a duplicate detection with metadata
                                                if ($detection->detection_type === 'duplicate' && $detection->metadata && isset($detection->metadata['duplicate_of_id'])) {
                                                    if ($detection->content_type === 'comment') {
                                                        $originalContent = \App\Models\Comment::with(['post.sub', 'user'])->find($detection->metadata['duplicate_of_id']);
                                                        if ($originalContent && $originalContent->post) {
                                                            $hexId = dechex($originalContent->id);
                                                            $originalAppLink = $frontendUrl . '/' . $locale . $originalContent->post->getPermalinkUrl() . '#c-' . $hexId;

                                                            // Calculate original comment order
                                                            $originalCommentOrder = \App\Models\Comment::where('post_id', $originalContent->post_id)
                                                                ->where('id', '<=', $originalContent->id)
                                                                ->count();
                                                        }
                                                    } elseif ($detection->content_type === 'post') {
                                                        $originalContent = \App\Models\Post::with(['sub', 'user'])->find($detection->metadata['duplicate_of_id']);
                                                        if ($originalContent) {
                                                            $originalAppLink = $frontendUrl . '/' . $locale . $originalContent->getPermalinkUrl();
                                                        }
                                                    }
                                                }

                                                $detectedText = $detection->content_type === 'comment'
                                                    ? $content->content
                                                    : ($content->content ?? $content->title ?? '');

                                                $originalText = '';
                                                if ($originalContent) {
                                                    $originalText = $detection->content_type === 'comment'
                                                        ? $originalContent->content
                                                        : ($originalContent->content ?? $originalContent->title ?? '');
                                                }
                                            @endphp

                                            @if($originalContent)
                                                <!-- Two Column Comparison (Desktop) -->
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <!-- Original (Left Column) -->
                                                    <div>
                                                        <div class="bg-blue-50 px-3 py-2 rounded-t border-b border-blue-200">
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-sm font-semibold text-blue-900">
                                                                    <i class="fas fa-check-circle mr-1"></i>Original
                                                                </span>
                                                                <span class="text-xs text-blue-700">
                                                                    {{ $originalContent->created_at->format('d M H:i') }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="bg-white p-4 rounded-b border border-blue-200 border-t-0">
                                                            @if($detection->content_type === 'comment' && $originalContent->post)
                                                                <div class="mb-3 pb-3 border-b border-gray-200">
                                                                    <div class="font-semibold text-gray-900 text-sm mb-1">{{ $originalContent->post->title }}</div>
                                                                    <div class="text-xs text-gray-500">
                                                                        {{ $originalContent->user->username }} ·
                                                                        @if($originalCommentOrder)
                                                                            Comment #{{ $originalCommentOrder }} ·
                                                                        @endif
                                                                        {{ $originalContent->created_at->format('d M Y, H:i') }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <div class="text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">
                                                                {{ $originalText ?: '[Deleted]' }}
                                                            </div>
                                                            @if($originalAppLink)
                                                                <div class="mt-3 pt-3 border-t">
                                                                    <a href="{{ $originalAppLink }}" target="_blank" class="text-blue-600 hover:underline text-xs">
                                                                        <i class="fas fa-external-link-alt mr-1"></i>View in App
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <!-- Detected (Right Column) -->
                                                    <div>
                                                        <div class="bg-orange-50 px-3 py-2 rounded-t border-b border-orange-200">
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-sm font-semibold text-orange-900">
                                                                    <i class="fas fa-flag mr-1"></i>Detected
                                                                </span>
                                                                <span class="text-xs text-orange-700">
                                                                    {{ $content->created_at->format('d M H:i') }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="bg-white p-4 rounded-b border border-orange-200 border-t-0">
                                                            @if($detection->content_type === 'comment' && $content->post)
                                                                <div class="mb-3 pb-3 border-b border-gray-200">
                                                                    <div class="font-semibold text-gray-900 text-sm mb-1">{{ $content->post->title }}</div>
                                                                    <div class="text-xs text-gray-500">
                                                                        {{ $content->user->username }} ·
                                                                        @if($detectedCommentOrder)
                                                                            Comment #{{ $detectedCommentOrder }} ·
                                                                        @endif
                                                                        {{ $content->created_at->format('d M Y, H:i') }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <div class="text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">
                                                                {{ $detectedText ?: '[Deleted]' }}
                                                            </div>
                                                            @if($appLink)
                                                                <div class="mt-3 pt-3 border-t">
                                                                    <a href="{{ $appLink }}" target="_blank" class="text-blue-600 hover:underline text-xs">
                                                                        <i class="fas fa-external-link-alt mr-1"></i>View in App
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif($detection->detection_type === 'duplicate' && $detection->metadata && isset($detection->metadata['duplicate_of_id']))
                                                <!-- Show this only if it's a duplicate detection but original content not found -->
                                                <div class="border border-yellow-200 rounded-lg p-3 bg-yellow-50">
                                                    <h4 class="font-semibold text-yellow-900 mb-2 flex items-center">
                                                        <i class="fas fa-info-circle mr-2"></i>
                                                        Original Content Not Found
                                                    </h4>
                                                    <p class="text-sm text-yellow-800">
                                                        The original {{ $detection->content_type }} (#{{ $detection->metadata['duplicate_of_id'] }}) has been deleted or is no longer available.
                                                    </p>
                                                </div>
                                            @else
                                                <!-- Non-duplicate detection: just show the detected content -->
                                                <div>
                                                    <div class="bg-orange-50 px-3 py-2 rounded-t border-b border-orange-200">
                                                        <span class="text-sm font-semibold text-orange-900">
                                                            <i class="fas fa-flag mr-1"></i>Detected Content
                                                        </span>
                                                    </div>
                                                    <div class="bg-white p-4 rounded-b border border-orange-200">
                                                        <div class="text-xs text-gray-600 mb-2">
                                                            {{ ucfirst($detection->content_type) }} #{{ $detection->content_id }}
                                                            @if($detection->content_type === 'comment' && $content->post)
                                                                · {{ Str::limit($content->post->title, 50) }}
                                                            @endif
                                                        </div>
                                                        <div class="text-sm text-gray-900 whitespace-pre-wrap leading-relaxed">
                                                            {{ $detectedText ?: '[Deleted]' }}
                                                        </div>
                                                        @if($appLink)
                                                            <div class="mt-3 pt-3 border-t">
                                                                <a href="{{ $appLink }}" target="_blank" class="text-blue-600 hover:underline text-xs">
                                                                    <i class="fas fa-external-link-alt mr-1"></i>View in App
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-state icon="clipboard-list" message="No spam detections found" colspan="7" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden divide-y divide-gray-200">
            @forelse($detections as $detection)
                @php
                    $content = null;
                    $appLink = null;
                    $locale = auth()->user()->locale ?? 'es';
                    $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

                    if ($detection->content_type === 'post') {
                        $content = \App\Models\Post::with(['sub', 'user'])->find($detection->content_id);
                        if ($content) {
                            $appLink = $frontendUrl . '/' . $locale . $content->getPermalinkUrl();
                        }
                    } elseif ($detection->content_type === 'comment') {
                        $content = \App\Models\Comment::with(['post.sub', 'user'])->find($detection->content_id);
                        if ($content && $content->post) {
                            // Convert comment ID to hexadecimal for anchor
                            $hexId = dechex($content->id);
                            $appLink = $frontendUrl . '/' . $locale . $content->post->getPermalinkUrl() . '#c-' . $hexId;
                        }
                    }

                    $badgeType = match($detection->detection_type) {
                        'duplicate' => 'info',
                        'rapid_fire' => 'warning',
                        'high_spam_score' => 'danger',
                        default => 'default',
                    };
                @endphp
                <div class="p-3">
                    <div class="text-xs text-gray-600 space-y-1 mb-2">
                        <div>
                            <x-admin.mobile-label label="Date" />
                            {{ $detection->created_at->format('d/m/Y H:i') }}
                        </div>
                        @if($detection->user)
                            <div>
                                <x-admin.mobile-label label="User" />
                                <x-admin.action-link :href="route('admin.users.show', $detection->user->id)" class="font-medium italic">
                                    {{ $detection->user->username }}
                                </x-admin.action-link>
                            </div>
                        @endif
                        <div>
                            <x-admin.mobile-label label="Type" />
                            <x-admin.badge :type="$badgeType" :label="ucfirst(str_replace('_', ' ', $detection->detection_type))" />
                        </div>
                        <div>
                            <x-admin.mobile-label label="Content" />
                            @if($detection->content_type === 'comment' && $content && $content->post)
                                <div class="text-xs font-medium text-gray-700 mb-1">
                                    {{ Str::limit($content->post->title, 30) }}
                                </div>
                            @endif
                            @if($content)
                                @php
                                    $mobileText = $detection->content_type === 'comment'
                                        ? $content->content
                                        : ($content->content ?? $content->title ?? '');
                                @endphp
                                <p class="text-sm text-gray-900 line-clamp-2">
                                    {{ Str::limit($mobileText, 100) }}
                                </p>
                            @else
                                <p class="text-xs text-gray-400 italic">Content deleted</p>
                            @endif
                        </div>
                        <div>
                            <x-admin.mobile-label label="Status" />
                            @if($detection->reviewed)
                                <x-admin.badge type="success" label="Reviewed" />
                            @else
                                <x-admin.badge type="pending" label="Pending" />
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-3 text-sm flex-wrap">
                        @if($content)
                            <button onclick="showContentModal({{ $detection->id }})" class="text-purple-600 hover:text-purple-800 hover:underline">
                                View Details
                            </button>
                        @endif
                        @if($appLink)
                            <x-admin.action-link :href="$appLink" :external="true">
                                View in app
                            </x-admin.action-link>
                        @endif
                        @if(!$detection->reviewed)
                            <button onclick="reviewDetection({{ $detection->id }}, 'ignored')" class="text-green-600 hover:text-green-800 hover:underline">
                                Mark Reviewed
                            </button>
                        @endif
                    </div>

                    <!-- Hidden content for modal (mobile) - same as desktop -->
                    @if($content)
                        <div id="content-{{ $detection->id }}" class="hidden">
                            <div class="space-y-4">
                                @php
                                    // Calculate comment order for mobile too
                                    $detectedCommentOrderMobile = null;
                                    $originalCommentOrderMobile = null;

                                    if ($detection->content_type === 'comment' && $content->post) {
                                        $detectedCommentOrderMobile = \App\Models\Comment::where('post_id', $content->post_id)
                                            ->where('id', '<=', $content->id)
                                            ->count();
                                    }
                                @endphp

                                <!-- Detected Content -->
                                <div>
                                    <div class="bg-orange-50 px-3 py-2 rounded-t border-b border-orange-200">
                                        <span class="text-sm font-semibold text-orange-900">
                                            <i class="fas fa-flag mr-1"></i>Detected
                                        </span>
                                    </div>
                                    <div class="bg-white p-4 rounded-b border border-orange-200">
                                        @if($detection->content_type === 'comment' && $content->post)
                                            <div class="mb-3 pb-3 border-b border-gray-200">
                                                <div class="font-semibold text-gray-900 text-sm mb-1">{{ $content->post->title }}</div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $content->user->username }} ·
                                                    @if($detectedCommentOrderMobile)
                                                        Comment #{{ $detectedCommentOrderMobile }} ·
                                                    @endif
                                                    {{ $content->created_at->format('d M Y, H:i') }}
                                                </div>
                                            </div>
                                        @endif
                                        @php
                                            $detectedTextMobile = $detection->content_type === 'comment'
                                                ? $content->content
                                                : ($content->content ?? $content->title ?? '');
                                        @endphp
                                        @if($detectedTextMobile)
                                            <div class="text-sm text-gray-900 whitespace-pre-wrap">{{ $detectedTextMobile }}</div>
                                        @else
                                            <div class="text-sm text-gray-400 italic">[Content deleted]</div>
                                        @endif
                                        @if($appLink)
                                            <div class="mt-3 pt-3 border-t">
                                                <a href="{{ $appLink }}" target="_blank" class="text-blue-600 hover:underline text-xs">
                                                    <i class="fas fa-external-link-alt mr-1"></i>View in App
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Original Content (if duplicate) -->
                                @if($detection->detection_type === 'duplicate' && $detection->metadata && isset($detection->metadata['duplicate_of_id']))
                                    @php
                                        $originalContent = null;
                                        $originalAppLink = null;

                                        if ($detection->content_type === 'comment') {
                                            $originalContent = \App\Models\Comment::with(['post.sub', 'user'])->find($detection->metadata['duplicate_of_id']);
                                            if ($originalContent && $originalContent->post) {
                                                $hexId = dechex($originalContent->id);
                                                $originalAppLink = $frontendUrl . '/' . $locale . $originalContent->post->getPermalinkUrl() . '#c-' . $hexId;

                                                // Calculate original comment order
                                                $originalCommentOrderMobile = \App\Models\Comment::where('post_id', $originalContent->post_id)
                                                    ->where('id', '<=', $originalContent->id)
                                                    ->count();
                                            }
                                        } elseif ($detection->content_type === 'post') {
                                            $originalContent = \App\Models\Post::with(['sub', 'user'])->find($detection->metadata['duplicate_of_id']);
                                            if ($originalContent) {
                                                $originalAppLink = $frontendUrl . '/' . $locale . $originalContent->getPermalinkUrl();
                                            }
                                        }
                                    @endphp

                                    @if($originalContent)
                                        <div>
                                            <div class="bg-blue-50 px-3 py-2 rounded-t border-b border-blue-200">
                                                <span class="text-sm font-semibold text-blue-900">
                                                    <i class="fas fa-check-circle mr-1"></i>Original
                                                </span>
                                            </div>
                                            <div class="bg-white p-4 rounded-b border border-blue-200">
                                                @if($detection->content_type === 'comment' && $originalContent->post)
                                                    <div class="mb-3 pb-3 border-b border-gray-200">
                                                        <div class="font-semibold text-gray-900 text-sm mb-1">{{ $originalContent->post->title }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $originalContent->user->username }} ·
                                                            @if($originalCommentOrderMobile)
                                                                Comment #{{ $originalCommentOrderMobile }} ·
                                                            @endif
                                                            {{ $originalContent->created_at->format('d M Y, H:i') }}
                                                        </div>
                                                    </div>
                                                @endif
                                                @php
                                                    $originalTextMobile = $detection->content_type === 'comment'
                                                        ? $originalContent->content
                                                        : ($originalContent->content ?? $originalContent->title ?? '');
                                                @endphp
                                                @if($originalTextMobile)
                                                    <div class="text-sm text-gray-900 whitespace-pre-wrap">{{ $originalTextMobile }}</div>
                                                @else
                                                    <div class="text-sm text-gray-400 italic">[Content deleted]</div>
                                                @endif
                                                @if($originalAppLink)
                                                    <div class="mt-3 pt-3 border-t">
                                                        <a href="{{ $originalAppLink }}" target="_blank" class="text-blue-600 hover:underline text-xs">
                                                            <i class="fas fa-external-link-alt mr-1"></i>View in App
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="border border-yellow-200 rounded-lg p-3 bg-yellow-50">
                                            <h4 class="font-semibold text-yellow-900 mb-2 flex items-center">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Original Not Found
                                            </h4>
                                            <p class="text-sm text-yellow-800">
                                                The original {{ $detection->content_type }} (#{{ $detection->metadata['duplicate_of_id'] }}) has been deleted.
                                            </p>
                                        </div>
                                    @endif
                                @endif

                                <!-- Actions -->
                                @if($appLink)
                                    <div class="pt-2 border-t flex gap-3">
                                        <a href="{{ $appLink }}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                                            <i class="fas fa-external-link-alt mr-1"></i>View Detected
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-admin.empty-state-mobile icon="clipboard-list" message="No spam detections found" />
            @endforelse
        </div>

        <!-- Pagination -->
        <x-admin.pagination :paginator="$detections" />
    </div>
</div>

<!-- Content Details Modal -->
<div id="contentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center pb-3 border-b">
            <h3 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-file-alt mr-2 text-purple-500"></i>Content Details
            </h3>
            <button onclick="closeContentModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div id="modalContent" class="mt-4 max-h-96 overflow-y-auto">
            <!-- Content will be inserted here -->
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-1/4 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-lg bg-white">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check text-green-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Mark as Reviewed</h3>
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to mark this detection as reviewed?</p>
            <div class="flex gap-3 justify-center">
                <button onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 font-medium">
                    Cancel
                </button>
                <button id="confirmButton" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 font-medium">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Show notification helper
function showNotification(message, type = 'success') {
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center`;
    alertDiv.innerHTML = `<i class="fas ${icon} mr-2"></i>${message}`;
    document.body.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

function showContentModal(detectionId) {
    const contentElement = document.getElementById(`content-${detectionId}`);
    if (!contentElement) return;

    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = contentElement.innerHTML;

    const modal = document.getElementById('contentModal');
    modal.classList.remove('hidden');

    // Close modal when clicking outside
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeContentModal();
        }
    };
}

function closeContentModal() {
    const modal = document.getElementById('contentModal');
    modal.classList.add('hidden');
}

// Close modals with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeContentModal();
        closeConfirmModal();
    }
});

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.add('hidden');
}

function reviewDetection(id, action) {
    const modal = document.getElementById('confirmModal');
    const confirmButton = document.getElementById('confirmButton');

    // Show confirm modal
    modal.classList.remove('hidden');

    // Close modal when clicking outside
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeConfirmModal();
        }
    };

    // Remove any previous event listeners by cloning the button
    const newConfirmButton = confirmButton.cloneNode(true);
    confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);

    // Add click handler for confirmation
    newConfirmButton.onclick = function() {
        closeConfirmModal();

        fetch(`/admin/spam-detections/${id}/review`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Detection marked as reviewed', 'success');
                setTimeout(() => location.reload(), 500);
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred', 'error');
        });
    };
}
</script>
@endsection
