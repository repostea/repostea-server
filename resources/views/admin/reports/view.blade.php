@extends('admin.layout')

@section('title', 'Report #' . $report->id)
@section('page-title', 'Report Details')

@section('content')
<div class="max-w-4xl">
    <!-- Back Button -->
    <div class="mb-4">
        <x-admin.action-link :href="route('admin.reports')">
            <i class="fas fa-arrow-left mr-2"></i>Back to Reports
        </x-admin.action-link>
    </div>

    <!-- Report Overview -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Report #{{ $report->id }}</h2>
                @php
                    $statusType = match($report->status) {
                        'pending' => 'warning',
                        'reviewing' => 'info',
                        'resolved' => 'success',
                        'dismissed' => 'danger',
                        default => 'default'
                    };
                @endphp
                <x-admin.badge :type="$statusType" :label="ucfirst(str_replace('_', ' ', $report->status))" />
            </div>
        </div>

        <div class="px-6 py-4 space-y-4">
            <!-- Report Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Reason</label>
                <x-admin.badge type="purple" :label="ucfirst(str_replace('_', ' ', $report->reason))" />
            </div>

            <!-- Reporter Information -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Reporter Information</h3>
                <div class="flex items-center space-x-4">
                    <div class="h-12 w-12 rounded-full flex items-center justify-center overflow-hidden bg-gray-100">
                        @if($report->reportedBy->avatar)
                            <img src="{{ $report->reportedBy->avatar }}" alt="{{ $report->reportedBy->username }}" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-user text-gray-400"></i>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 italic">{{ $report->reportedBy->username }}</p>
                        <div class="flex gap-3 text-xs mt-1">
                            <x-admin.action-link :href="route('admin.users.show', $report->reportedBy)">
                                <i class="fas fa-user-shield"></i> View in Admin
                            </x-admin.action-link>
                            <x-admin.action-link :href="config('app.client_url') . '/u/' . $report->reportedBy->username" :external="true">
                                View in App
                            </x-admin.action-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reported User Information (if applicable) -->
            @if($report->reportedUser)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Reported User</h3>
                    <div class="flex items-center space-x-4">
                        <div class="h-12 w-12 rounded-full flex items-center justify-center overflow-hidden bg-gray-100">
                            @if($report->reportedUser->avatar)
                                <img src="{{ $report->reportedUser->avatar }}" alt="{{ $report->reportedUser->username }}" class="w-full h-full object-cover">
                            @else
                                <i class="fas fa-user text-gray-400"></i>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 italic">{{ $report->reportedUser->username }}</p>
                            <div class="flex gap-3 text-xs mt-1">
                                <x-admin.action-link :href="route('admin.users.show', $report->reportedUser)">
                                    <i class="fas fa-user-shield"></i> View in Admin
                                </x-admin.action-link>
                                <x-admin.action-link :href="config('app.client_url') . '/u/' . $report->reportedUser->username" :external="true">
                                    View in App
                                </x-admin.action-link>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Reported Content -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Reported Content</h3>
                <div class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-700 mb-1">Content Type</dt>
                        <dd class="text-sm">
                            <x-admin.badge type="info" :label="class_basename($report->reportable_type)" />
                        </dd>
                    </div>

                    @if($report->reportable_type === 'App\\Models\\Post' && $report->reportable)
                        <div>
                            <dt class="text-sm font-medium text-gray-700 mb-1">Post Title</dt>
                            <dd class="text-sm text-gray-900">{{ $report->reportable->title }}</dd>
                        </div>
                        @php
                            $postSlug = $report->reportable->slug ?? $report->reportable->id;
                            $appUrl = config('app.client_url') . '/posts/' . $postSlug;
                        @endphp
                        <x-admin.action-link :href="$appUrl" :external="true" class="inline-block text-sm">
                            View Post in App
                        </x-admin.action-link>
                    @elseif($report->reportable_type === 'App\\Models\\Comment' && $report->reportable)
                        <div>
                            <dt class="text-sm font-medium text-gray-700 mb-1">Comment Content</dt>
                            <dd class="text-sm text-gray-900 bg-gray-50 rounded p-3 whitespace-pre-wrap">{{ $report->reportable->content }}</dd>
                        </div>
                        @php
                            $comment = $report->reportable;
                            $post = $comment->post;
                            if ($post) {
                                $postSlug = $post->slug ?? $post->id;
                                $appUrl = config('app.client_url') . '/posts/' . $postSlug . '#comment-' . $comment->id;
                            }
                        @endphp
                        @if(isset($appUrl))
                            <x-admin.action-link :href="$appUrl" :external="true" class="inline-block text-sm">
                                View Comment in App
                            </x-admin.action-link>
                        @endif
                    @elseif($report->reportable_type === 'App\\Models\\User' && $report->reportable)
                        <div>
                            <dt class="text-sm font-medium text-gray-700 mb-1">Reported User</dt>
                            <dd class="text-sm text-gray-900 italic">{{ $report->reportable->username }}</dd>
                        </div>
                        @php
                            $appUrl = config('app.client_url') . '/u/' . $report->reportable->username;
                        @endphp
                        <x-admin.action-link :href="$appUrl" :external="true" class="inline-block text-sm">
                            View Profile in App
                        </x-admin.action-link>
                    @else
                        <p class="text-sm text-gray-500 italic">Content no longer available</p>
                    @endif
                </div>
            </div>

            <!-- Description -->
            @if($report->description)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Report Description</h3>
                    <div class="bg-gray-50 rounded p-4">
                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $report->description }}</p>
                    </div>
                </div>
            @endif

            <!-- Moderator Notes (if any) -->
            @if($report->moderator_notes)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Moderator Notes</h3>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-4">
                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $report->moderator_notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Review Information -->
            @if($report->reviewed_at)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Review Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="font-medium text-gray-700">Reviewed By</dt>
                            <dd class="text-gray-900 italic">{{ $report->reviewedBy->username ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-700">Reviewed At</dt>
                            <dd class="text-gray-900">{{ $report->reviewed_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <!-- Timestamps -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Timeline</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-700">Submitted</dt>
                        <dd class="text-gray-900">{{ $report->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Last Updated</dt>
                        <dd class="text-gray-900">{{ $report->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Internal Notes History -->
            @if($report->notes->count() > 0)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Internal Notes History</h3>
                    <div class="space-y-3">
                        @foreach($report->notes as $note)
                            <div class="bg-gray-50 rounded p-4 border-l-4 border-gray-400">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-900 italic">{{ $note->user->username }}</span>
                                    <span class="text-xs text-gray-500">{{ $note->created_at->format('Y-m-d H:i:s') }}</span>
                                </div>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $note->note }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow mb-6 px-6 py-4">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <h2 class="text-xl font-semibold">Actions</h2>
            <div class="flex flex-wrap gap-3">
                @if($report->status === 'pending' || $report->status === 'reviewing')
                    <button type="button" onclick="showResolveModal()" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <i class="fas fa-check mr-2"></i>Resolve Report
                    </button>
                    <button type="button" onclick="showDismissModal()" class="px-6 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        <i class="fas fa-times mr-2"></i>Dismiss Report
                    </button>
                @elseif($report->status === 'resolved' || $report->status === 'dismissed')
                    <button type="button" onclick="showReopenModal()" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-redo mr-2"></i>Reopen Report
                    </button>
                @endif
                <button type="button" onclick="openNoteModal()" class="px-6 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">
                    <i class="fas fa-plus mr-2"></i>Add Internal Note
                </button>
                <x-admin.action-link :href="route('admin.reports')" class="inline-block px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </x-admin.action-link>
            </div>
        </div>
    </div>

    <!-- Resolve Modal -->
    <div id="resolveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-green-900 mb-4">
                <i class="fas fa-check-circle mr-2"></i>Resolve Report
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                Mark this report as resolved after taking appropriate action.
            </p>
            <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500" placeholder="Describe what action was taken..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex space-x-3">
                    <button type="button" onclick="hideResolveModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Resolve Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dismiss Modal -->
    <div id="dismissModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-red-900 mb-4">
                <i class="fas fa-times-circle mr-2"></i>Dismiss Report
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                Dismiss this report if it's invalid or doesn't violate rules.
            </p>
            <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dismissal Notes (optional)</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500" placeholder="Explain why this report is being dismissed..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex space-x-3">
                    <button type="button" onclick="hideDismissModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Dismiss Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reopen Modal -->
    <div id="reopenModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold text-blue-900 mb-4">
                <i class="fas fa-redo mr-2"></i>Reopen Report
            </h3>
            <p class="text-sm text-gray-600 mb-4">
                Reopen this report to review it again. The report status will be changed to "pending".
            </p>
            <form method="POST" action="{{ route('admin.reports.reopen', $report) }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reopen Reason (optional)</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Explain why this report is being reopened..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex space-x-3">
                    <button type="button" onclick="hideReopenModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Reopen Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Internal Note Modal -->
    <div id="noteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Add Internal Note</h2>
                <button type="button" onclick="closeNoteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.reports.add-note', $report) }}" class="px-6 py-4">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Internal Note</label>
                        <textarea name="note" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Add an internal note (only visible to admins)..." required></textarea>
                        <p class="text-xs text-gray-500 mt-1">Internal notes are only visible to admins and help track the investigation process.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeNoteModal()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">
                            <i class="fas fa-save mr-2"></i>Save Note
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Resolve Modal
    function showResolveModal() {
        document.getElementById('resolveModal').classList.remove('hidden');
    }
    function hideResolveModal() {
        document.getElementById('resolveModal').classList.add('hidden');
    }

    // Dismiss Modal
    function showDismissModal() {
        document.getElementById('dismissModal').classList.remove('hidden');
    }
    function hideDismissModal() {
        document.getElementById('dismissModal').classList.add('hidden');
    }

    // Reopen Modal
    function showReopenModal() {
        document.getElementById('reopenModal').classList.remove('hidden');
    }
    function hideReopenModal() {
        document.getElementById('reopenModal').classList.add('hidden');
    }

    // Note Modal
    function openNoteModal() {
        document.getElementById('noteModal').classList.remove('hidden');
    }

    function closeNoteModal() {
        document.getElementById('noteModal').classList.add('hidden');
    }

    // Close modals on background click
    document.getElementById('resolveModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideResolveModal();
    });

    document.getElementById('dismissModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideDismissModal();
    });

    document.getElementById('reopenModal')?.addEventListener('click', function(e) {
        if (e.target === this) hideReopenModal();
    });

    document.getElementById('noteModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeNoteModal();
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideResolveModal();
            hideDismissModal();
            hideReopenModal();
            closeNoteModal();
        }
    });
</script>
@endpush
