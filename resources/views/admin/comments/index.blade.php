@extends('admin.layout')

@section('title', 'Comments Management')
@section('page-title', 'Comments Management')

@section('content')
<div class="bg-white rounded-lg shadow">
    <!-- Search and Filters -->
    <x-admin.search-form placeholder="Search by comment content...">
        <x-slot name="filters">
            <input
                type="text"
                name="username"
                placeholder="Filter by username..."
                value="{{ request('username') }}"
                class="flex-1 md:flex-none md:min-w-[200px] px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
            <select
                name="status"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All Status</option>
                <option value="visible" {{ request('status') === 'visible' ? 'selected' : '' }}>Visible Only</option>
                <option value="hidden" {{ request('status') === 'hidden' ? 'selected' : '' }}>Hidden Only</option>
            </select>
            @if(request('search') || request('status') || request('username'))
                <a href="{{ route('admin.comments') }}" class="px-4 md:px-6 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 whitespace-nowrap">
                    <i class="fas fa-times md:mr-2"></i><span class="hidden md:inline">Clear</span>
                </a>
            @endif
        </x-slot>
    </x-admin.search-form>

    @if(request('username') && $comments->total() === 0)
        <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-100">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <p class="text-sm text-yellow-800">
                    No user found with username containing "<strong>{{ request('username') }}</strong>". Try a different search.
                </p>
            </div>
        </div>
    @endif

    <!-- Comments Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comment</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($comments as $comment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="max-w-md">
                                <p class="text-sm text-gray-900 line-clamp-2">{{ $comment->content }}</p>
                                @if($comment->parent_id)
                                    <x-admin.badge type="purple" class="mt-1">
                                        <i class="fas fa-reply mr-1"></i>Reply
                                    </x-admin.badge>
                                @endif
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($comment->user->avatar)
                                        <img src="{{ $comment->user->avatar }}" alt="{{ $comment->user->username }}" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                    @endif
                                </div>
                                <div class="ml-2">
                                    <x-admin.action-link :href="route('admin.users.show', $comment->user)" class="text-sm font-medium italic">
                                        {{ $comment->user->username }}
                                    </x-admin.action-link>
                                </div>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4">
                            @if($comment->post)
                                <div class="max-w-xs">
                                    <x-admin.action-link :href="route('admin.posts.view', $comment->post)" class="text-sm line-clamp-2">
                                        {{ $comment->post->title }}
                                    </x-admin.action-link>
                                </div>
                            @else
                                <span class="text-sm text-gray-400">Post deleted</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($comment->status === 'visible')
                                <x-admin.badge type="success" label="Visible" />
                            @elseif($comment->status === 'hidden')
                                <x-admin.badge type="danger" label="Hidden" />
                                @if($comment->moderated_by)
                                    <p class="text-xs text-gray-500 mt-1">
                                        By {{ $comment->moderatedBy->username }}
                                    </p>
                                @endif
                            @else
                                <x-admin.badge type="default" :label="ucfirst($comment->status)" />
                            @endif
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $comment->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $comment->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <x-admin.action-link href="#" onclick="event.preventDefault(); showDetailsModal({{ $comment->id }})">
                                View Details
                            </x-admin.action-link>

                            @if($comment->status !== 'hidden')
                                <x-admin.action-link href="#" onclick="event.preventDefault(); showHideModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')">
                                    Hide
                                </x-admin.action-link>
                            @else
                                <form action="{{ route('admin.comments.show', $comment) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        Show
                                    </button>
                                </form>
                            @endif

                            @can('admin-only')
                                <x-admin.action-link href="#" onclick="event.preventDefault(); showDeleteModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')" class="text-red-600 hover:text-red-800">
                                    Delete
                                </x-admin.action-link>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="comments" message="No comments found" colspan="6" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Comments Cards - Mobile -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($comments as $comment)
            <div class="p-3">
                <p class="text-sm text-gray-900 mb-1 line-clamp-2">{{ $comment->content }}</p>
                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    <div>
                        <span class="text-gray-500">by</span>
                        <x-admin.action-link :href="route('admin.users.show', $comment->user)" class="font-medium italic">
                            {{ $comment->user->username }}
                        </x-admin.action-link>
                        @if($comment->parent_id)
                            • <x-admin.badge type="purple" label="Reply" />
                        @endif
                    </div>
                    @if($comment->post)
                        <div>
                            <x-admin.mobile-label label="Post" />
                            <x-admin.action-link :href="route('admin.posts.view', $comment->post)">
                                {{ Str::limit($comment->post->title, 40) }}
                            </x-admin.action-link>
                        </div>
                    @endif
                    <div>
                        <x-admin.mobile-label label="Status" />
                        @if($comment->status === 'visible')
                            <x-admin.badge type="success" label="Visible" />
                        @elseif($comment->status === 'hidden')
                            <x-admin.badge type="danger" label="Hidden" />
                        @else
                            <x-admin.badge type="default" :label="ucfirst($comment->status)" />
                        @endif
                        •
                        <x-admin.mobile-label label="Date" />
                        {{ $comment->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
                <div class="flex gap-3 text-sm flex-wrap">
                    <x-admin.action-link href="#" onclick="event.preventDefault(); showDetailsModal({{ $comment->id }})">
                        View Details
                    </x-admin.action-link>

                    @if($comment->status !== 'hidden')
                        <x-admin.action-link href="#" onclick="event.preventDefault(); showHideModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')">
                            Hide
                        </x-admin.action-link>
                    @else
                        <form action="{{ route('admin.comments.show', $comment) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-blue-600 hover:text-blue-800 hover:underline">
                                Show
                            </button>
                        </form>
                    @endif

                    @can('admin-only')
                        <x-admin.action-link href="#" onclick="event.preventDefault(); showDeleteModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')" class="text-red-600 hover:text-red-800">
                            Delete
                        </x-admin.action-link>
                    @endcan
                </div>
            </div>
        @empty
            <x-admin.empty-state-mobile icon="comments" message="No comments found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$comments" />
</div>

<!-- Comment Details Modal -->
<div id="detailsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-comment mr-2 text-blue-600"></i>Comment Details
            </h3>
            <button onclick="hideDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-6" id="detailsContent">
            <!-- Content will be filled by JavaScript -->
        </div>

        <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex gap-3">
            <button onclick="hideDetailsModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100">
                Close
            </button>
            <button id="detailsHideBtn" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                <i class="fas fa-eye-slash mr-2"></i>Hide Comment
            </button>
            <button id="detailsDeleteBtn" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                <i class="fas fa-trash mr-2"></i>Delete
            </button>
        </div>
    </div>
</div>

<!-- Hide Comment Modal -->
<div id="hideModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-orange-900 mb-4">
            <i class="fas fa-eye-slash mr-2 text-orange-600"></i>Hide Comment
        </h3>
        <p class="text-sm text-gray-600 mb-4">You are about to hide: <strong id="hideCommentText"></strong></p>
        <form id="hideForm" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for hiding</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-orange-500" required placeholder="Explain why this comment is being hidden..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideHideModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
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
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
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
        <p class="text-sm text-gray-600 mb-4">You are about to delete: <strong id="deleteCommentText"></strong></p>
        <p class="text-xs text-red-600 mb-4"><strong>Only use this for spam, illegal content, or severe policy violations.</strong></p>
        <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for deletion</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500" required placeholder="Explain why this comment is being permanently deleted..."></textarea>
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
@endsection

@push('scripts')
<script>
    // Store comment data for the details modal
    const commentsData = {!! json_encode($comments->mapWithKeys(function($c) {
        return [$c->id => [
            'id' => $c->id,
            'content' => $c->content,
            'username' => $c->user->username,
            'user_id' => $c->user_id,
            'user_avatar' => $c->user->avatar,
            'post_title' => $c->post ? $c->post->title : 'Post deleted',
            'post_id' => $c->post_id,
            'status' => $c->status,
            'created_at' => $c->created_at->format('d M Y H:i'),
            'is_reply' => $c->parent_id ? true : false,
            'moderated_by' => $c->moderatedBy ? $c->moderatedBy->username : null,
        ]];
    })) !!};

    function showDetailsModal(commentId) {
        const comment = commentsData[commentId];
        if (!comment) return;

        const content = `
            <div class="space-y-6">
                <!-- User Info -->
                <div class="flex items-center gap-3 pb-4 border-b border-gray-200">
                    <div class="h-12 w-12 rounded-full flex items-center justify-center overflow-hidden bg-gray-100">
                        ${comment.user_avatar ?
                            `<img src="${comment.user_avatar}" alt="${comment.username}" class="w-full h-full object-cover">` :
                            `<i class="fas fa-user text-gray-400"></i>`
                        }
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 italic">${comment.username}</p>
                        <p class="text-xs text-gray-500">User ID: ${comment.user_id}</p>
                    </div>
                    ${comment.is_reply ? '<span class="ml-auto px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full"><i class="fas fa-reply mr-1"></i>Reply</span>' : ''}
                </div>

                <!-- Comment Content -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Comment:</h4>
                    <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-900 whitespace-pre-wrap break-words">
${comment.content}
                    </div>
                </div>

                <!-- Post Info -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Post:</h4>
                    <p class="text-sm text-gray-900">${comment.post_title}</p>
                    ${comment.post_id ? `<p class="text-xs text-gray-500 mt-1">Post ID: ${comment.post_id}</p>` : ''}
                </div>

                <!-- Status & Date -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Status:</h4>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            comment.status === 'visible' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">
                            ${comment.status === 'visible' ? 'Visible' : 'Hidden'}
                        </span>
                        ${comment.moderated_by ? `<p class="text-xs text-gray-500 mt-1">By ${comment.moderated_by}</p>` : ''}
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Date:</h4>
                        <p class="text-sm text-gray-900">${comment.created_at}</p>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('detailsContent').innerHTML = content;

        // Set up action buttons
        document.getElementById('detailsHideBtn').onclick = () => {
            hideDetailsModal();
            showHideModal(commentId, comment.content.substring(0, 50));
        };

        document.getElementById('detailsDeleteBtn').onclick = () => {
            hideDetailsModal();
            showDeleteModal(commentId, comment.content.substring(0, 50));
        };

        // Show/hide buttons based on status
        const hideBtn = document.getElementById('detailsHideBtn');
        if (comment.status === 'hidden') {
            hideBtn.style.display = 'none';
        } else {
            hideBtn.style.display = 'block';
        }

        document.getElementById('detailsModal').classList.remove('hidden');
    }

    function hideDetailsModal() {
        document.getElementById('detailsModal').classList.add('hidden');
    }

    function showHideModal(commentId, commentText) {
        document.getElementById('hideCommentText').textContent = commentText;
        document.getElementById('hideForm').action = '/admin/comments/' + commentId + '/hide';
        document.getElementById('hideModal').classList.remove('hidden');
    }

    function hideHideModal() {
        document.getElementById('hideModal').classList.add('hidden');
    }

    function showDeleteModal(commentId, commentText) {
        document.getElementById('deleteCommentText').textContent = commentText;
        document.getElementById('deleteForm').action = '/admin/comments/' + commentId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function hideDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modal on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideDetailsModal();
        }
    });
</script>
@endpush
