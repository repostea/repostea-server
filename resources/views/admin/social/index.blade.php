@extends('admin.layout')

@section('title', 'Social Media Management')
@section('page-title', 'Social Media')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Total Posted</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_posted'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Manual</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['manual_posts'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Automatic</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['auto_posts'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">By Votes</p>
            <p class="text-2xl font-bold text-purple-600">{{ $stats['by_votes'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Original Articles</p>
            <p class="text-2xl font-bold text-orange-600">{{ $stats['by_article'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 mb-1">Pending</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Configuration Panel -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fab fa-x-twitter mr-2"></i>Twitter/X Configuration
                    </h2>
                </div>
                <div class="p-6">
                    @if(!$config['is_configured'])
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Twitter API not configured. Add credentials in <code>.env</code>
                        </div>
                    @else
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-green-700 text-sm">
                            <i class="fas fa-check-circle mr-1"></i>
                            Twitter API configured
                        </div>
                    @endif

                    <form action="{{ route('admin.social.config') }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <!-- Auto-post enabled -->
                        <div class="flex items-start">
                            <input type="checkbox" name="auto_post_enabled" id="auto_post_enabled" value="1"
                                {{ $config['auto_post_enabled'] ? 'checked' : '' }}
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="auto_post_enabled" class="ml-2 text-sm text-gray-700">
                                <span class="font-medium">Enable automatic posting</span>
                                <p class="text-xs text-gray-500">Automatically post to X when criteria are met</p>
                            </label>
                        </div>

                        <!-- Minimum votes -->
                        <div>
                            <label for="min_votes" class="block text-sm font-medium text-gray-700 mb-1">
                                Minimum votes to post
                            </label>
                            <input type="number" name="min_votes" id="min_votes"
                                value="{{ $config['min_votes'] }}"
                                min="1" max="1000"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Posts with this many votes will be auto-posted</p>
                        </div>

                        <!-- Delay minutes -->
                        <div>
                            <label for="post_delay_minutes" class="block text-sm font-medium text-gray-700 mb-1">
                                Delay after frontpage (minutes)
                            </label>
                            <input type="number" name="post_delay_minutes" id="post_delay_minutes"
                                value="{{ $config['post_delay_minutes'] }}"
                                min="0" max="1440"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Wait time before posting (allows editing)</p>
                        </div>

                        <!-- Max days back -->
                        <div>
                            <label for="max_days_back" class="block text-sm font-medium text-gray-700 mb-1">
                                Max days back to check
                            </label>
                            <input type="number" name="max_days_back" id="max_days_back"
                                value="{{ $config['max_days_back'] }}"
                                min="1" max="30"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Only auto-post content from the last X days (prevents bulk posting old content)</p>
                        </div>

                        <!-- Auto-post original articles -->
                        <div class="flex items-start">
                            <input type="checkbox" name="auto_post_original_articles" id="auto_post_original_articles" value="1"
                                {{ $config['auto_post_original_articles'] ? 'checked' : '' }}
                                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="auto_post_original_articles" class="ml-2 text-sm text-gray-700">
                                <span class="font-medium">Auto-post original articles</span>
                                <p class="text-xs text-gray-500">Automatically post text posts (articles) regardless of votes</p>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Posts History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-history mr-2"></i>Recent Posts to X
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    @if($twitterPosts->isEmpty())
                        <div class="p-8 text-center text-gray-500">
                            <i class="fab fa-x-twitter text-4xl mb-3"></i>
                            <p>No posts have been shared to X yet.</p>
                        </div>
                    @else
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Post</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Posted</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Link</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($twitterPosts as $post)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.posts.view', $post) }}" class="text-sm font-medium text-blue-600 hover:underline line-clamp-2">
                                                {{ Str::limit($post->title, 50) }}
                                            </a>
                                            <p class="text-xs text-gray-500">by {{ $post->user->username ?? 'deleted' }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $post->twitter_posted_at->format('d M Y') }}
                                            <span class="block text-xs text-gray-400">{{ $post->twitter_posted_at->format('H:i') }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($post->twitter_post_method === 'manual')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                                    <i class="fas fa-hand-pointer mr-1"></i>Manual
                                                </span>
                                                @if($post->twitter_post_reason === 'admin_action')
                                                    <span class="block text-xs text-gray-500 mt-1">
                                                        <i class="fas fa-user-shield"></i> Admin
                                                    </span>
                                                @endif
                                            @elseif($post->twitter_post_method === 'auto')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700">
                                                    <i class="fas fa-robot mr-1"></i>Auto
                                                </span>
                                                @if($post->twitter_post_reason === 'popular_votes')
                                                    <span class="block text-xs text-gray-500 mt-1">
                                                        <i class="fas fa-arrow-up"></i> Popular
                                                    </span>
                                                @elseif($post->twitter_post_reason === 'original_article')
                                                    <span class="block text-xs text-gray-500 mt-1">
                                                        <i class="fas fa-newspaper"></i> Article
                                                    </span>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                                                    <i class="fas fa-clock-rotate-left mr-1"></i>Legacy
                                                </span>
                                                <span class="block text-xs text-gray-400 mt-1">Pre-tracking</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if($post->twitterPostedBy)
                                                <span class="italic">{{ $post->twitterPostedBy->username }}</span>
                                            @elseif($post->twitter_post_method === 'auto')
                                                <span class="text-gray-400">System</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($post->twitter_tweet_id)
                                                <a href="https://x.com/i/status/{{ $post->twitter_tweet_id }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
