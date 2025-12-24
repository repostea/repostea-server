@extends('admin.layout')

@section('title', 'Post: ' . $post->title)
@section('page-title', 'Post Details')

@section('content')
<div class="mb-4">
    <x-admin.action-link :href="route('admin.posts')">
        <i class="fas fa-arrow-left mr-2"></i>Back to Posts
    </x-admin.action-link>
</div>

@if(session('success'))
    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Post Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <!-- Author -->
            <div class="flex items-center mb-6 pb-6 border-b border-gray-200">
                <div class="h-12 w-12 rounded-full flex items-center justify-center overflow-hidden bg-gray-100">
                    @if($post->user->avatar)
                        <img src="{{ $post->user->avatar }}" alt="{{ $post->user->username }}" class="w-full h-full object-cover">
                    @else
                        <i class="fas fa-user text-gray-400 text-xl"></i>
                    @endif
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900 italic">{{ $post->user->username }}</p>
                    <x-admin.action-link :href="route('admin.users.show', $post->user)" class="text-xs">
                        <i class="fas fa-user-shield mr-1"></i>View User
                    </x-admin.action-link>
                </div>
            </div>

            <!-- Post Stats -->
            <div class="space-y-3 mb-6 pb-6 border-b border-gray-200">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Votes</span>
                    <span class="text-sm font-semibold text-gray-900">
                        <i class="fas fa-arrow-up text-green-600"></i> {{ $post->votes_count }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Comments</span>
                    <span class="text-sm font-semibold text-gray-900">
                        <i class="fas fa-comments text-blue-600"></i> {{ $post->comment_count }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Views</span>
                    <span class="text-sm font-semibold text-gray-900">
                        <i class="fas fa-eye text-gray-600"></i> {{ $post->views }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Status</span>
                    <span>
                        @if($post->status === 'published')
                            <x-admin.badge type="success" label="Published" />
                        @elseif($post->status === 'pending')
                            <x-admin.badge type="info" label="Pending" />
                        @elseif($post->status === 'draft')
                            <x-admin.badge type="warning" label="Draft" />
                        @elseif($post->status === 'hidden')
                            <x-admin.badge type="danger" label="Unpublished" />
                        @endif
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Created</span>
                    <span class="text-sm text-gray-900">{{ $post->created_at->format('d M Y H:i') }}</span>
                </div>
            </div>

            <!-- Moderation Info -->
            @if($post->moderated_by)
                <div class="mb-6 pb-6 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Moderation</h4>
                    <div class="text-xs text-gray-600 space-y-1">
                        <p><strong>By:</strong> <span class="italic">{{ $post->moderatedBy->username }}</span></p>
                        <p><strong>Date:</strong> {{ $post->moderated_at?->format('d M Y H:i') }}</p>
                        @if($post->moderation_reason)
                            <p class="mt-2"><strong>Reason:</strong></p>
                            <p class="text-gray-700 bg-gray-50 p-2 rounded">{{ $post->moderation_reason }}</p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Moderation Settings -->
            <div class="mb-6 pb-6 border-b border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Moderation Settings</h4>
                <form action="{{ route('admin.posts.updateModeration', $post) }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <!-- Language -->
                    <div>
                        <label for="language_search" class="block text-xs font-medium text-gray-700 mb-2">
                            Language
                        </label>

                        <!-- Current Selection Display -->
                        @php
                            $currentLang = config('languages.available')[$post->language_code] ?? null;
                        @endphp
                        <div id="current-language" class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg" id="current-lang-flag">{{ $currentLang['flag'] ?? '🌐' }}</span>
                                <div>
                                    <div class="text-sm font-medium text-gray-900" id="current-lang-native">{{ $currentLang['native'] ?? 'Select language' }}</div>
                                    <div class="text-xs text-gray-500" id="current-lang-name">{{ $currentLang['name'] ?? '' }}</div>
                                </div>
                            </div>
                            <button type="button" onclick="showLanguageSelector()" class="px-2 py-1 text-xs text-blue-600 hover:bg-blue-100 rounded">
                                <i class="fas fa-edit mr-1"></i>Change
                            </button>
                        </div>

                        <!-- Hidden input for form submission -->
                        <input type="hidden" name="language_code" id="language_code" value="{{ $post->language_code }}">

                        <!-- Search Input with Dropdown (hidden by default) -->
                        <div id="language-selector" class="hidden mb-2">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400 text-xs"></i>
                                </div>
                                <input
                                    type="text"
                                    id="language_search"
                                    placeholder="Search language..."
                                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    oninput="filterLanguages()"
                                    onkeydown="if(event.key === 'Escape') hideLanguageSelector()"
                                >
                            </div>

                            <!-- Language Dropdown -->
                            <div id="language-dropdown" class="mt-1 border border-gray-200 rounded bg-white shadow-lg max-h-60 overflow-y-auto">
                                @foreach(config('languages.available') as $code => $lang)
                                    @if($lang['active'])
                                        <button
                                            type="button"
                                            class="language-option w-full flex items-center gap-2 px-3 py-2 hover:bg-gray-50 transition-colors text-left border-b border-gray-100 last:border-b-0"
                                            data-code="{{ $code }}"
                                            data-flag="{{ $lang['flag'] }}"
                                            data-native="{{ $lang['native'] }}"
                                            data-name="{{ $lang['name'] }}"
                                            onclick="selectLanguage('{{ $code }}', '{{ $lang['flag'] }}', '{{ $lang['native'] }}', '{{ $lang['name'] }}')"
                                        >
                                            <span class="text-base">{{ $lang['flag'] }}</span>
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900 text-sm">{{ $lang['native'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $lang['name'] }}</div>
                                            </div>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-2 flex items-center">
                            <input type="checkbox" name="lock_language" id="lock_language" value="1"
                                {{ $post->language_locked_by_admin ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="lock_language" class="ml-2 text-xs text-gray-600">
                                <i class="fas fa-lock text-xs mr-1"></i>Lock language (author can't change)
                            </label>
                        </div>
                    </div>

                    <!-- NSFW -->
                    <div>
                        <div class="flex items-start">
                            <input type="checkbox" name="is_nsfw" id="is_nsfw" value="1"
                                {{ $post->is_nsfw ? 'checked' : '' }}
                                class="h-4 w-4 mt-0.5 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                            <label for="is_nsfw" class="ml-2 text-xs font-medium text-gray-700">
                                Mark as NSFW/Adult content (+18)
                            </label>
                        </div>
                        <div class="mt-1.5 ml-6 flex items-center">
                            <input type="checkbox" name="lock_nsfw" id="lock_nsfw" value="1"
                                {{ $post->nsfw_locked_by_admin ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="lock_nsfw" class="ml-2 text-xs text-gray-600">
                                <i class="fas fa-lock text-xs mr-1"></i>Lock NSFW status (author can't change)
                            </label>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div>
                        <button type="submit" class="w-full px-3 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-1"></i>Save Moderation Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Social Sharing -->
            @if($post->status === 'published')
                <div class="mb-6 pb-6 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Social Sharing</h4>
                    <div class="space-y-2">
                        @if($post->twitter_posted_at)
                            <div class="w-full px-3 py-2 bg-green-50 text-green-700 rounded text-left border border-green-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <i class="fab fa-x-twitter mr-1"></i>Posted to X
                                        <span class="text-xs block mt-1 text-green-600">{{ $post->twitter_posted_at->format('d M Y H:i') }}</span>
                                    </div>
                                    @if($post->twitter_tweet_id)
                                        <a href="https://x.com/i/status/{{ $post->twitter_tweet_id }}" target="_blank" class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <form action="{{ route('admin.posts.twitter.repost', $post) }}" method="POST" class="w-full" onsubmit="return confirmSubmit(this, 'This will post again to X. Are you sure?', {title: 'Repost to X', confirmText: 'Repost'})">
                                @csrf
                                <button type="submit" class="w-full px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600 transition-colors text-left text-sm">
                                    <i class="fas fa-redo mr-1"></i>Repost to X
                                </button>
                            </form>
                        @else
                            <form action="{{ route('admin.posts.twitter', $post) }}" method="POST" class="w-full" onsubmit="return confirmSubmit(this, 'Post this to Twitter/X?', {title: 'Post to X', confirmText: 'Post'})">
                                @csrf
                                <button type="submit" class="w-full px-3 py-2 bg-black text-white rounded hover:bg-gray-800 transition-colors text-left">
                                    <i class="fab fa-x-twitter mr-1"></i>Post to X
                                </button>
                            </form>
                        @endif

                        <!-- ActivityPub / Fediverse -->
                        <form action="{{ route('admin.posts.federate', $post) }}" method="POST" class="w-full" onsubmit="return confirmSubmit(this, 'Federate this post to the Fediverse?', {title: 'Federate Post', confirmText: 'Federate'})">
                            @csrf
                            <button type="submit" class="w-full px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition-colors text-left">
                                <i class="fab fa-mastodon mr-1"></i>Federate to Fediverse
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Moderation Actions -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Moderation</h4>
                <div class="space-y-2">
                    @if($post->status === 'pending')
                        <form action="{{ route('admin.posts.approve', $post) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" class="w-full px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors text-left">
                                <i class="fas fa-check mr-1"></i>Approve & Publish
                            </button>
                        </form>
                        <button type="button" onclick="showUnpublishModal({{ $post->id }}, '{{ addslashes($post->title) }}', '{{ $post->status }}')" class="w-full px-3 py-1.5 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors text-left">
                            <i class="fas fa-ban mr-1"></i>Reject
                        </button>
                    @elseif($post->status === 'published' || ($post->status === 'draft' && !$post->moderated_by))
                        <button type="button" onclick="showUnpublishModal({{ $post->id }}, '{{ addslashes($post->title) }}', '{{ $post->status }}')" class="w-full px-3 py-1.5 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors text-left">
                            <i class="fas fa-ban mr-1"></i>{{ $post->status === 'draft' ? 'Pre-moderate' : 'Unpublish' }}
                        </button>
                    @elseif($post->status === 'draft' && $post->moderated_by)
                        <form action="{{ route('admin.posts.show', $post) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" class="w-full px-3 py-1.5 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors text-left">
                                <i class="fas fa-undo mr-1"></i>Allow
                            </button>
                        </form>
                    @elseif($post->status === 'hidden')
                        <form action="{{ route('admin.posts.show', $post) }}" method="POST" class="w-full">
                            @csrf
                            <button type="submit" class="w-full px-3 py-1.5 bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors text-left">
                                <i class="fas fa-check mr-1"></i>Republish
                            </button>
                        </form>
                    @endif

                    <button onclick="showDeleteModal({{ $post->id }}, '{{ addslashes($post->title) }}')" class="w-full px-3 py-1.5 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors text-left">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Post Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Title and Content -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $post->title }}</h2>

            <!-- View in App Button -->
            @if($post->status === 'published')
                <div class="mb-4">
                    <x-admin.action-link :href="config('app.client_url') . '/posts/' . $post->slug" :external="true" class="text-sm">
                        View in App
                    </x-admin.action-link>
                </div>
            @endif

            <!-- URL -->
            @if($post->url)
                <div class="mb-4 p-3 bg-gray-50 rounded">
                    <p class="text-xs text-gray-500 mb-1">URL:</p>
                    <x-admin.action-link :href="$post->url" :external="true" class="text-sm break-all">
                        {{ $post->url }}
                    </x-admin.action-link>
                </div>
            @endif

            <!-- Content -->
            @if($post->content)
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Content:</h3>
                    <div class="prose max-w-none text-gray-900 markdown-content">
                        @php
                            $converter = new \League\CommonMark\CommonMarkConverter([
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                            ]);
                            echo $converter->convert($post->content);
                        @endphp
                    </div>
                </div>
            @endif

            <!-- Thumbnail -->
            @if($post->thumbnail_url)
                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Thumbnail:</h3>
                    <img src="{{ $post->thumbnail_url }}" alt="Thumbnail" class="max-w-xs rounded border border-gray-200">
                </div>
            @endif

            <!-- Image Content -->
            @if($post->content_type === 'image' && $post->url)
                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Image:</h3>
                    <img src="{{ $post->url }}" alt="{{ $post->title }}" class="max-w-full rounded border border-gray-200">
                </div>
            @endif

            <!-- Media Info -->
            @if($post->isMediaContent())
                <div class="mt-4 p-3 bg-blue-50 rounded">
                    <p class="text-sm"><strong>Content Type:</strong> {{ ucfirst($post->content_type) }}</p>
                    @if($post->media_provider)
                        <p class="text-sm"><strong>Provider:</strong> {{ $post->getFormattedMediaProvider() }}</p>
                    @endif
                </div>
            @endif

            <!-- Source -->
            @if($post->isExternalImport())
                <div class="mt-4 p-3 bg-yellow-50 rounded">
                    <p class="text-sm"><strong>External Import:</strong> {{ $post->external_source }}</p>
                    @if($post->getSourceName())
                        <p class="text-sm"><strong>Source:</strong> {{ $post->getSourceName() }}</p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Comments -->
        @if($post->comments->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-comments mr-2"></i>Comments ({{ $post->comment_count }})
                </h3>

                <div class="space-y-3">
                    @foreach($post->comments as $comment)
                        @include('admin.posts.partials.comment-item', ['comment' => $comment, 'level' => 0])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Unpublish Modal -->
<div id="unpublishModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-orange-900 mb-4" id="unpublishModalTitle">
            <i class="fas fa-ban mr-2 text-orange-600"></i>Unpublish Post
        </h3>
        <div id="unpublishModalWarning" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
            <p class="text-sm text-yellow-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Draft Post:</strong> This will prevent the post from being visible if the user publishes it.
            </p>
        </div>
        <p class="text-sm text-gray-600 mb-4"><span id="unpublishModalAction">You are about to unpublish</span>: <strong id="unpublishPostTitle"></strong></p>
        <form id="unpublishForm" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" id="unpublishModalLabel">Reason for unpublishing</label>
                    <textarea name="reason" id="unpublishModalTextarea" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500" required placeholder="Explain why this post violates the rules..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideUnpublishModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="unpublishModalButton" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    Unpublish Post
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Post Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-red-900 mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>Delete Post
        </h3>
        <p class="text-sm text-gray-600 mb-4">
            <strong>Warning:</strong> This action is permanent and cannot be undone.
        </p>
        <p class="text-sm text-gray-600 mb-4">You are about to delete: <strong id="deletePostTitle"></strong></p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for deletion</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500" required placeholder="Explain why this post is being permanently deleted..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hide Comment Modal -->
<div id="hideCommentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-orange-900 mb-4">
            <i class="fas fa-eye-slash mr-2 text-orange-600"></i>Hide Comment
        </h3>
        <p class="text-sm text-gray-600 mb-4">You are about to hide: <strong id="hideCommentContent"></strong></p>
        <form id="hideCommentForm" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for hiding</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500" required placeholder="Explain why this comment is being hidden..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideHideCommentModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    Hide Comment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Comment Modal -->
<div id="deleteCommentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-red-900 mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i>⚠️ PERMANENT DELETE - ADMIN ONLY
        </h3>
        <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 mb-4">
            <p class="text-sm font-bold text-red-900 mb-2">
                <i class="fas fa-skull-crossbones mr-1"></i> CRITICAL WARNING:
            </p>
            <ul class="text-sm text-red-800 space-y-1 ml-4 list-disc">
                <li>This will <strong>PERMANENTLY DELETE</strong> the comment from the database</li>
                <li>This will <strong>BREAK THE CONVERSATION THREAD</strong> and confuse users</li>
                <li>This action <strong>CANNOT BE UNDONE</strong></li>
                <li>Consider using "Hide" instead to preserve thread structure</li>
            </ul>
        </div>
        <p class="text-sm text-gray-600 mb-4">You are about to delete: <strong id="deleteCommentContent"></strong></p>
        <p class="text-xs text-red-600 mb-4"><strong>Only use this for spam, illegal content, or severe policy violations.</strong></p>
        <form id="deleteCommentForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for deletion</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500" required placeholder="Explain why this comment is being permanently deleted..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideDeleteCommentModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- User Agents Statistics Section -->
<div class="bg-white rounded-lg shadow p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">User Agents Statistics</h3>

    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-1">Total Visitors</p>
            <p class="text-2xl font-bold text-gray-900">{{ $userAgents->sum('total_visitors') }}</p>
        </div>
        <div class="bg-green-50 rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-1">Total Identified</p>
            <p class="text-2xl font-bold text-gray-900">{{ $userAgents->sum('identified_visitors') }}</p>
        </div>
        <div class="bg-orange-50 rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-1">Total Anonymous</p>
            <p class="text-2xl font-bold text-gray-900">{{ $userAgents->sum('anonymous_visitors') }}</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-4">
            <p class="text-sm text-gray-600 mb-1">Total Views</p>
            <p class="text-2xl font-bold text-gray-900">{{ $post->views }}</p>
        </div>
    </div>

    @if($userAgents->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Agent</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visitors</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Identified</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Anonymous</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($userAgents as $agent)
                        <tr class="{{ $agent->is_unusual ? 'bg-yellow-50' : 'hover:bg-gray-50' }}">
                            <td class="px-4 py-3 text-sm text-gray-900 font-mono max-w-md truncate">
                                {{ $agent->user_agent }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $agent->total_visitors }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $agent->identified_visitors }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $agent->anonymous_visitors }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500 text-center py-8">No user agent data available yet.</p>
    @endif
</div>

@endsection

@push('scripts')
<script>
    function showUnpublishModal(postId, postTitle, postStatus) {
        document.getElementById('unpublishPostTitle').textContent = postTitle;
        document.getElementById('unpublishForm').action = '/admin/posts/' + postId + '/hide';

        const warningDiv = document.getElementById('unpublishModalWarning');
        const modalTitle = document.getElementById('unpublishModalTitle');
        const modalAction = document.getElementById('unpublishModalAction');
        const modalLabel = document.getElementById('unpublishModalLabel');
        const modalButton = document.getElementById('unpublishModalButton');
        const modalTextarea = document.getElementById('unpublishModalTextarea');

        if (postStatus === 'draft') {
            // Draft post - use pre-moderation terminology
            warningDiv.classList.remove('hidden');
            modalTitle.innerHTML = '<i class="fas fa-exclamation-triangle mr-2 text-orange-600"></i>Pre-moderate Draft';
            modalAction.textContent = 'You are about to pre-moderate';
            modalLabel.textContent = 'Reason for pre-moderation';
            modalButton.textContent = 'Pre-moderate Draft';
            modalTextarea.placeholder = 'Explain why this draft should be prevented from publishing...';
        } else {
            // Published post - use unpublish terminology
            warningDiv.classList.add('hidden');
            modalTitle.innerHTML = '<i class="fas fa-ban mr-2 text-orange-600"></i>Unpublish Post';
            modalAction.textContent = 'You are about to unpublish';
            modalLabel.textContent = 'Reason for unpublishing';
            modalButton.textContent = 'Unpublish Post';
            modalTextarea.placeholder = 'Explain why this post violates the rules...';
        }

        document.getElementById('unpublishModal').classList.remove('hidden');
    }

    function hideUnpublishModal() {
        document.getElementById('unpublishModal').classList.add('hidden');
    }

    function showDeleteModal(postId, postTitle) {
        document.getElementById('deletePostTitle').textContent = postTitle;
        document.getElementById('deleteForm').action = '/admin/posts/' + postId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function hideDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Comment moderation functions
    function showHideCommentModal(commentId, commentContent) {
        document.getElementById('hideCommentContent').textContent = commentContent;
        document.getElementById('hideCommentForm').action = '/admin/comments/' + commentId + '/hide';
        document.getElementById('hideCommentModal').classList.remove('hidden');
    }

    function hideHideCommentModal() {
        document.getElementById('hideCommentModal').classList.add('hidden');
    }

    function showDeleteCommentModal(commentId, commentContent) {
        document.getElementById('deleteCommentContent').textContent = commentContent;
        document.getElementById('deleteCommentForm').action = '/admin/comments/' + commentId;
        document.getElementById('deleteCommentModal').classList.remove('hidden');
    }

    function hideDeleteCommentModal() {
        document.getElementById('deleteCommentModal').classList.add('hidden');
    }

    // Language selector functions
    function showLanguageSelector() {
        document.getElementById('language-selector').classList.remove('hidden');
        document.getElementById('language_search').focus();
    }

    function hideLanguageSelector() {
        document.getElementById('language-selector').classList.add('hidden');
        document.getElementById('language_search').value = '';
        filterLanguages(); // Reset filter
    }

    function selectLanguage(code, flag, native, name) {
        // Update hidden input
        document.getElementById('language_code').value = code;

        // Update display
        document.getElementById('current-lang-flag').textContent = flag;
        document.getElementById('current-lang-native').textContent = native;
        document.getElementById('current-lang-name').textContent = name;

        // Hide selector
        hideLanguageSelector();
    }

    function filterLanguages() {
        const searchTerm = document.getElementById('language_search').value.toLowerCase();
        const options = document.querySelectorAll('.language-option');

        options.forEach(option => {
            const native = option.dataset.native.toLowerCase();
            const name = option.dataset.name.toLowerCase();
            const code = option.dataset.code.toLowerCase();

            if (native.includes(searchTerm) || name.includes(searchTerm) || code.includes(searchTerm)) {
                option.style.display = 'flex';
            } else {
                option.style.display = 'none';
            }
        });
    }

    // Close language selector when clicking outside
    document.addEventListener('click', function(event) {
        const selector = document.getElementById('language-selector');
        const currentLang = document.getElementById('current-language');

        if (selector && !selector.classList.contains('hidden')) {
            if (!selector.contains(event.target) && !currentLang.contains(event.target)) {
                hideLanguageSelector();
            }
        }
    });
</script>

<style>
    .markdown-content {
        line-height: 1.6;
    }
    .markdown-content h1 {
        font-size: 2em;
        font-weight: bold;
        margin-top: 1em;
        margin-bottom: 0.5em;
    }
    .markdown-content h2 {
        font-size: 1.5em;
        font-weight: bold;
        margin-top: 0.83em;
        margin-bottom: 0.5em;
    }
    .markdown-content h3 {
        font-size: 1.17em;
        font-weight: bold;
        margin-top: 1em;
        margin-bottom: 0.5em;
    }
    .markdown-content p {
        margin-top: 1em;
        margin-bottom: 1em;
    }
    .markdown-content ul, .markdown-content ol {
        margin-left: 2em;
        margin-top: 1em;
        margin-bottom: 1em;
    }
    .markdown-content ul {
        list-style-type: disc;
    }
    .markdown-content ol {
        list-style-type: decimal;
    }
    .markdown-content li {
        margin-bottom: 0.5em;
    }
    .markdown-content code {
        background-color: #f3f4f6;
        padding: 0.125em 0.25em;
        border-radius: 0.25em;
        font-family: 'Courier New', monospace;
        font-size: 0.875em;
    }
    .markdown-content pre {
        background-color: #f3f4f6;
        padding: 1em;
        border-radius: 0.5em;
        overflow-x: auto;
        margin-top: 1em;
        margin-bottom: 1em;
    }
    .markdown-content pre code {
        background-color: transparent;
        padding: 0;
    }
    .markdown-content blockquote {
        border-left: 4px solid #d1d5db;
        padding-left: 1em;
        margin-left: 0;
        margin-top: 1em;
        margin-bottom: 1em;
        color: #6b7280;
    }
    .markdown-content a {
        color: #2563eb;
        text-decoration: underline;
    }
    .markdown-content a:hover {
        color: #1d4ed8;
    }
    .markdown-content strong {
        font-weight: bold;
    }
    .markdown-content em {
        font-style: italic;
    }
    .markdown-content hr {
        border: 0;
        border-top: 1px solid #e5e7eb;
        margin: 2em 0;
    }
</style>
@endpush
