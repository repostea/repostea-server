@php
    $bgColor = $comment->status === 'hidden' ? 'bg-red-50' : 'bg-white';
    $borderColor = $comment->status === 'hidden' ? 'border-red-300' : 'border-gray-200';
    $indent = $level * 2; // 2rem per level
@endphp

<div class="border-l-4 {{ $borderColor }} pl-4 py-3 {{ $bgColor }} rounded-r" style="margin-left: {{ $indent }}rem;">
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-2 mb-2">
            <div class="h-6 w-6 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                @if($comment->user->avatar)
                    <img src="{{ $comment->user->avatar }}" alt="{{ $comment->user->username }}" class="w-full h-full object-cover">
                @else
                    <i class="fas fa-user text-gray-400 text-xs"></i>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.action-link :href="route('admin.users.show', $comment->user)" class="text-sm font-medium italic">
                    {{ $comment->user->username }}
                </x-admin.action-link>
                <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                @if($comment->status === 'hidden')
                    <x-admin.badge type="danger" label="Hidden" />
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-1 flex-shrink-0">
            @if($comment->status !== 'hidden')
                <button
                    onclick="showHideCommentModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')"
                    class="px-2 py-1 text-xs bg-orange-100 text-orange-700 rounded hover:bg-orange-200 transition-colors"
                    title="Hide comment">
                    <i class="fas fa-eye-slash"></i>
                </button>
            @else
                <form action="{{ route('admin.comments.show', $comment) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors"
                        title="Show comment">
                        <i class="fas fa-eye"></i>
                    </button>
                </form>
            @endif

            @can('admin-only')
                <button
                    onclick="showDeleteCommentModal({{ $comment->id }}, '{{ addslashes(Str::limit($comment->content, 50)) }}')"
                    class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                    title="Delete comment">
                    <i class="fas fa-trash"></i>
                </button>
            @endcan
        </div>
    </div>

    <div class="text-sm text-gray-700 mt-1 whitespace-pre-wrap">{{ $comment->content }}</div>

    <!-- Moderation Info -->
    @if($comment->moderated_by)
        <div class="mt-2 text-xs text-gray-500 bg-gray-50 p-2 rounded">
            <strong>Moderated by:</strong> <span class="italic">{{ $comment->moderatedBy->username ?? 'Unknown' }}</span>
            @if($comment->moderation_reason)
                <br><strong>Reason:</strong> {{ $comment->moderation_reason }}
            @endif
        </div>
    @endif

    <!-- Nested Replies -->
    @if($comment->replies && $comment->replies->count() > 0 && $level < 5)
        <div class="mt-3 space-y-3">
            @foreach($comment->replies as $reply)
                @include('admin.posts.partials.comment-item', ['comment' => $reply, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
