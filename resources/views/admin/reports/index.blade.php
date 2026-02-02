@extends('admin.layout')

@section('title', 'Reports')
@section('page-title', 'Reports Management')

@section('content')
<div class="bg-white rounded-lg shadow">
    <!-- Search and Filters -->
    <div class="px-3 md:px-6 py-3 md:py-4 border-b border-gray-200">
        <form method="GET" class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
            <select name="status" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Status</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="reviewing" {{ request('status') === 'reviewing' ? 'selected' : '' }}>Reviewing</option>
                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="dismissed" {{ request('status') === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
            </select>
            <select name="reason" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Reasons</option>
                <option value="spam" {{ request('reason') === 'spam' ? 'selected' : '' }}>Spam</option>
                <option value="harassment" {{ request('reason') === 'harassment' ? 'selected' : '' }}>Harassment</option>
                <option value="inappropriate" {{ request('reason') === 'inappropriate' ? 'selected' : '' }}>Inappropriate</option>
                <option value="misinformation" {{ request('reason') === 'misinformation' ? 'selected' : '' }}>Misinformation</option>
                <option value="hate_speech" {{ request('reason') === 'hate_speech' ? 'selected' : '' }}>Hate Speech</option>
                <option value="violence" {{ request('reason') === 'violence' ? 'selected' : '' }}>Violence</option>
                <option value="illegal_content" {{ request('reason') === 'illegal_content' ? 'selected' : '' }}>Illegal Content</option>
                <option value="copyright" {{ request('reason') === 'copyright' ? 'selected' : '' }}>Copyright</option>
                <option value="other" {{ request('reason') === 'other' ? 'selected' : '' }}>Other</option>
            </select>
            <select name="type" class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">All Types</option>
                <option value="post" {{ request('type') === 'post' ? 'selected' : '' }}>Post</option>
                <option value="comment" {{ request('type') === 'comment' ? 'selected' : '' }}>Comment</option>
                <option value="user" {{ request('type') === 'user' ? 'selected' : '' }}>User</option>
            </select>
            <button type="submit" class="px-4 md:px-6 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 whitespace-nowrap">
                <i class="fas fa-filter md:mr-2"></i><span class="hidden md:inline">Filter</span>
            </button>
        </form>
    </div>

    <!-- Reports Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported By</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reported User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($reports as $report)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="max-w-md">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-admin.badge type="purple" :label="ucfirst(str_replace('_', ' ', $report->reason))" />
                                    <x-admin.badge type="info" :label="class_basename($report->reportable_type)" />
                                </div>
                                @if($report->reportable_type === 'App\\Models\\Post' && $report->reportable)
                                    <p class="text-sm font-medium text-gray-900 line-clamp-2 mb-1">
                                        {{ $report->reportable->title }}
                                    </p>
                                    @php
                                        $appUrl = config('app.client_url') . '/posts/' . ($report->reportable->slug ?? $report->reportable->id);
                                    @endphp
                                    <x-admin.action-link :href="$appUrl" :external="true" class="text-xs">
                                        View in app
                                    </x-admin.action-link>
                                @elseif($report->reportable_type === 'App\\Models\\Comment' && $report->reportable)
                                    <p class="text-sm text-gray-700 line-clamp-2 mb-1">
                                        {{ Str::limit($report->reportable->content, 100) }}
                                    </p>
                                    @php
                                        $comment = $report->reportable;
                                        $post = $comment->post;
                                        $appUrl = $post ? config('app.client_url') . '/posts/' . ($post->slug ?? $post->id) . '#comment-' . $comment->id : null;
                                    @endphp
                                    @if($appUrl)
                                        <x-admin.action-link :href="$appUrl" :external="true" class="text-xs">
                                            View comment in app
                                        </x-admin.action-link>
                                    @endif
                                @elseif($report->reportable_type === 'App\\Models\\User' && $report->reportable)
                                    <p class="text-sm text-gray-700 mb-1">
                                        User: <strong class="italic">{{ $report->reportable?->username ?? 'Deleted' }}</strong>
                                    </p>
                                    @if($report->reportable)
                                    <x-admin.action-link :href="config('app.client_url') . '/u/' . $report->reportable->username" :external="true" class="text-xs">
                                        View profile in app
                                    </x-admin.action-link>
                                    @endif
                                @else
                                    <p class="text-sm text-gray-500 italic">Content no longer available</p>
                                @endif
                                @if($report->description)
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $report->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($report->reportedBy?->avatar)
                                        <img src="{{ $report->reportedBy?->avatar }}" alt="{{ $report->reportedBy?->username ?? 'Deleted' }}" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                    @endif
                                </div>
                                <div class="ml-2">
                                    <p class="text-sm font-medium text-gray-900 italic">{{ $report->reportedBy?->username ?? 'Deleted' }}</p>
                                    @if($report->reportedBy)
                                    <div class="flex gap-2 text-xs mt-0.5">
                                        <x-admin.action-link :href="route('admin.users.show', $report->reportedBy)">
                                            Admin
                                        </x-admin.action-link>
                                        <x-admin.action-link :href="config('app.client_url') . '/u/' . $report->reportedBy->username" :external="true">
                                            App
                                        </x-admin.action-link>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            @if($report->reportedUser)
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                                        @if($report->reportedUser->avatar)
                                            <img src="{{ $report->reportedUser->avatar }}" alt="{{ $report->reportedUser->username }}" class="w-full h-full object-cover">
                                        @else
                                            <i class="fas fa-user text-gray-400 text-sm"></i>
                                        @endif
                                    </div>
                                    <div class="ml-2">
                                        <p class="text-sm font-medium text-gray-900 italic">{{ $report->reportedUser->username }}</p>
                                        <div class="flex gap-2 text-xs mt-0.5">
                                            <x-admin.action-link :href="route('admin.users.show', $report->reportedUser)">
                                                Admin
                                            </x-admin.action-link>
                                            <x-admin.action-link :href="config('app.client_url') . '/u/' . $report->reportedUser->username" :external="true">
                                                App
                                            </x-admin.action-link>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-admin.badge :type="$report->status" :label="ucfirst($report->status)" />
                            @if(($report->status === 'resolved' || $report->status === 'dismissed') && $report->reviewedBy)
                                <p class="text-xs text-gray-500 mt-1">By {{ $report->reviewedBy?->username ?? 'System' }}</p>
                            @endif
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $report->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $report->created_at->format('H:i') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                            <x-admin.action-link :href="route('admin.reports.view', $report)">
                                View
                            </x-admin.action-link>
                            @if($report->status === 'pending' || $report->status === 'reviewing')
                                <x-admin.action-link href="#" onclick="event.preventDefault(); showResolveModal({{ $report->id }})" class="text-green-600 hover:text-green-800">
                                    Resolve
                                </x-admin.action-link>
                                <x-admin.action-link href="#" onclick="event.preventDefault(); showDismissModal({{ $report->id }})" class="text-red-600 hover:text-red-800">
                                    Dismiss
                                </x-admin.action-link>
                            @elseif($report->status === 'resolved' || $report->status === 'dismissed')
                                <x-admin.action-link href="#" onclick="event.preventDefault(); showReopenModal({{ $report->id }})">
                                    Reopen
                                </x-admin.action-link>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="flag" message="No reports found" colspan="6" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Reports Cards - Mobile -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($reports as $report)
            <div class="p-3">
                <div class="flex items-center gap-2 mb-2">
                    <x-admin.badge type="purple" :label="ucfirst(str_replace('_', ' ', $report->reason))" />
                    <x-admin.badge type="info" :label="class_basename($report->reportable_type)" />
                </div>

                @if($report->reportable_type === 'App\\Models\\Post' && $report->reportable)
                    <p class="text-sm font-medium text-gray-900 mb-1 line-clamp-2">{{ $report->reportable->title }}</p>
                @elseif($report->reportable_type === 'App\\Models\\Comment' && $report->reportable)
                    <p class="text-sm text-gray-700 mb-1 line-clamp-2">{{ Str::limit($report->reportable->content, 100) }}</p>
                @elseif($report->reportable_type === 'App\\Models\\User' && $report->reportable)
                    <p class="text-sm text-gray-700 mb-1">User: <strong class="italic">{{ $report->reportable?->username ?? 'Deleted' }}</strong></p>
                @endif

                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    <div>
                        <x-admin.mobile-label label="Reported by" />
                        <span class="italic">{{ $report->reportedBy?->username ?? 'Deleted' }}</span>
                    </div>
                    @if($report->reportedUser)
                        <div>
                            <x-admin.mobile-label label="Reported user" />
                            <span class="italic">{{ $report->reportedUser?->username ?? 'Deleted' }}</span>
                        </div>
                    @endif
                    <div>
                        <x-admin.mobile-label label="Status" />
                        <x-admin.badge :type="$report->status" :label="ucfirst($report->status)" />
                        •
                        <x-admin.mobile-label label="Created" />
                        {{ $report->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>

                <div class="flex gap-2 text-sm flex-wrap">
                    <x-admin.action-link :href="route('admin.reports.view', $report)">
                        View
                    </x-admin.action-link>
                    @if($report->status === 'pending' || $report->status === 'reviewing')
                        <x-admin.action-link href="#" onclick="event.preventDefault(); showResolveModal({{ $report->id }})" class="text-green-600 hover:text-green-800">
                            Resolve
                        </x-admin.action-link>
                        <x-admin.action-link href="#" onclick="event.preventDefault(); showDismissModal({{ $report->id }})" class="text-red-600 hover:text-red-800">
                            Dismiss
                        </x-admin.action-link>
                    @elseif($report->status === 'resolved' || $report->status === 'dismissed')
                        <x-admin.action-link href="#" onclick="event.preventDefault(); showReopenModal({{ $report->id }})">
                            Reopen
                        </x-admin.action-link>
                    @endif
                </div>
            </div>
        @empty
            <x-admin.empty-state-mobile icon="flag" message="No reports found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$reports" />
</div>

<!-- Resolve Modal -->
<div id="resolveModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-green-900 mb-4">
            <i class="fas fa-check-circle mr-2"></i>Resolve Report
        </h3>
        <p class="text-sm text-gray-600 mb-4">Mark this report as resolved after taking appropriate action.</p>
        <form id="resolveForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Resolution Notes (optional)</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-green-500" placeholder="Describe what action was taken..."></textarea>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hideResolveModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Resolve Report</button>
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
        <p class="text-sm text-gray-600 mb-4">Dismiss this report if it's invalid or doesn't violate rules.</p>
        <form id="dismissForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dismissal Notes (optional)</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500" placeholder="Explain why this report is being dismissed..."></textarea>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hideDismissModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Dismiss Report</button>
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
        <p class="text-sm text-gray-600 mb-4">Reopen this report to review it again. The report status will be changed to "pending".</p>
        <form id="reopenForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reopen Reason (optional)</label>
                <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Explain why this report is being reopened..."></textarea>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hideReopenModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Reopen Report</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function showResolveModal(reportId) {
        document.getElementById('resolveForm').action = '/admin/reports/' + reportId + '/resolve';
        document.getElementById('resolveModal').classList.remove('hidden');
    }
    function hideResolveModal() {
        document.getElementById('resolveModal').classList.add('hidden');
    }
    function showDismissModal(reportId) {
        document.getElementById('dismissForm').action = '/admin/reports/' + reportId + '/dismiss';
        document.getElementById('dismissModal').classList.remove('hidden');
    }
    function hideDismissModal() {
        document.getElementById('dismissModal').classList.add('hidden');
    }
    function showReopenModal(reportId) {
        document.getElementById('reopenForm').action = '/admin/reports/' + reportId + '/reopen';
        document.getElementById('reopenModal').classList.remove('hidden');
    }
    function hideReopenModal() {
        document.getElementById('reopenModal').classList.add('hidden');
    }
</script>
@endpush
