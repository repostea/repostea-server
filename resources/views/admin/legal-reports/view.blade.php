@extends('admin.layout')

@section('title', 'Legal Report #' . $legalReport->reference_number)
@section('page-title', 'Legal Report Details')

@section('content')
<div class="max-w-4xl">
    <!-- Back Button -->
    <div class="mb-4">
        <x-admin.action-link :href="route('admin.legal-reports')">
            <i class="fas fa-arrow-left mr-2"></i>Back to Legal Reports
        </x-admin.action-link>
    </div>

    <!-- Report Overview -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">{{ $legalReport->reference_number }}</h2>
                @php
                    $statusType = match($legalReport->status) {
                        'pending' => 'warning',
                        'under_review' => 'info',
                        'resolved' => 'success',
                        'rejected' => 'danger',
                        default => 'default'
                    };
                @endphp
                <x-admin.badge :type="$statusType" :label="ucfirst(str_replace('_', ' ', $legalReport->status))" />
            </div>
        </div>

        <div class="px-6 py-4 space-y-4">
            <!-- Report Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <x-admin.badge type="purple" :label="ucfirst($legalReport->type)" />
            </div>

            <!-- Reporter Information -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Reporter Information</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Name</dt>
                        <dd class="text-sm text-gray-900">{{ $legalReport->reporter_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-700">Email</dt>
                        <dd class="text-sm text-gray-900">
                            @php
                                // Mask email: show first 2 chars of local + *** + @first2chars***.ext
                                $email = $legalReport->reporter_email;
                                $parts = explode('@', $email);
                                $localPart = $parts[0];
                                $domain = $parts[1] ?? '';

                                // Mask local part
                                $charsToShow = min(2, strlen($localPart));
                                $charsToMask = max(0, min(strlen($localPart) - $charsToShow, 4));
                                $maskedLocal = substr($localPart, 0, $charsToShow) . str_repeat('*', $charsToMask);

                                // Mask domain part
                                $domainParts = explode('.', $domain);
                                if (count($domainParts) >= 2) {
                                    $maskedDomain = substr($domainParts[0], 0, 2) . '***.' . end($domainParts);
                                } else {
                                    $maskedDomain = '***';
                                }

                                echo $maskedLocal . '@' . $maskedDomain;
                            @endphp
                        </dd>
                    </div>
                    @if($legalReport->reporter_organization)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-700">Organization</dt>
                            <dd class="text-sm text-gray-900">{{ $legalReport->reporter_organization }}</dd>
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-gray-700">IP Address</dt>
                        <dd class="text-sm text-gray-900 font-mono">
                            @php
                                // Mask IP: show first 2 octets only
                                $ip = $legalReport->ip_address;
                                $octets = explode('.', $ip);
                                if (count($octets) === 4) {
                                    echo $octets[0] . '.' . $octets[1] . '.***.***';
                                } else {
                                    // IPv6 or other format - show first segment
                                    $segments = explode(':', $ip);
                                    echo $segments[0] . ':***:***';
                                }
                            @endphp
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Content URLs -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Content Information</h3>
                <div class="space-y-3">
                    <div>
                        <dt class="text-sm font-medium text-gray-700 mb-1">Reported Content URL</dt>
                        <dd class="text-sm break-all">
                            <x-admin.action-link :href="$legalReport->content_url" :external="true">
                                {{ $legalReport->content_url }}
                            </x-admin.action-link>
                        </dd>
                    </div>
                    @if($legalReport->original_url)
                        <div>
                            <dt class="text-sm font-medium text-gray-700 mb-1">Original Content URL</dt>
                            <dd class="text-sm break-all">
                                <x-admin.action-link :href="$legalReport->original_url" :external="true">
                                    {{ $legalReport->original_url }}
                                </x-admin.action-link>
                            </dd>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Description -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Description</h3>
                <div class="bg-gray-50 rounded p-4">
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $legalReport->description }}</p>
                </div>
            </div>

            @if($legalReport->ownership_proof)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Ownership Proof</h3>
                    <div class="bg-gray-50 rounded p-4">
                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $legalReport->ownership_proof }}</p>
                    </div>
                </div>
            @endif

            <!-- Legal Declarations -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Legal Declarations</h3>
                <div class="space-y-2">
                    <div class="flex items-center">
                        <i class="fas {{ $legalReport->good_faith ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600' }} mr-2"></i>
                        <span class="text-sm">Good faith declaration</span>
                    </div>
                    @if($legalReport->type === 'copyright')
                        <div class="flex items-center">
                            <i class="fas {{ $legalReport->authorized ? 'fa-check-circle text-green-600' : 'fa-times-circle text-red-600' }} mr-2"></i>
                            <span class="text-sm">Authorized to act on behalf of copyright owner</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Timestamps -->
            <div class="border-t pt-4">
                <h3 class="text-lg font-semibold mb-3">Timeline</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-700">Submitted</dt>
                        <dd class="text-gray-900">{{ $legalReport->created_at->format('Y-m-d H:i:s') }}</dd>
                    </div>
                    @if($legalReport->reviewed_at)
                        <div>
                            <dt class="font-medium text-gray-700">Reviewed</dt>
                            <dd class="text-gray-900">{{ $legalReport->reviewed_at->format('Y-m-d H:i:s') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if($legalReport->user_response)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Response to User</h3>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-4">
                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $legalReport->user_response }}</p>
                        @if($legalReport->response_sent_at)
                            <p class="text-xs text-gray-500 mt-2">Sent: {{ $legalReport->response_sent_at->format('Y-m-d H:i:s') }}</p>
                        @endif
                    </div>
                    @if($legalReport->status === 'resolved' || $legalReport->status === 'rejected')
                        <div class="mt-3">
                            <button type="button" onclick="openNotifyModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                <i class="fas fa-envelope mr-2"></i>Send Notification Email
                            </button>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Notification History -->
            @if($legalReport->notifications->count() > 0)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Notification Email History ({{ $legalReport->notifications->count() }})</h3>
                    <div class="space-y-3">
                        @foreach($legalReport->notifications as $notification)
                            @if($notification->status === 'sent')
                                <div class="bg-green-50 dark:bg-green-900/20 rounded p-4 border-l-4 border-green-500">
                            @elseif($notification->status === 'sending')
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded p-4 border-l-4 border-blue-500">
                            @elseif($notification->status === 'failed')
                                <div class="bg-red-50 dark:bg-red-900/20 rounded p-4 border-l-4 border-red-500">
                            @else
                                <div class="bg-gray-50 dark:bg-gray-900/20 rounded p-4 border-l-4 border-gray-500">
                            @endif
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        @if($notification->status === 'sent')
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Sent
                                            </span>
                                        @elseif($notification->status === 'sending')
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-spinner fa-spin mr-1"></i>Sending...
                                            </span>
                                        @elseif($notification->status === 'failed')
                                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times mr-1"></i>Failed
                                            </span>
                                        @endif
                                        <span class="text-xs text-gray-600">{{ $notification->created_at->format('Y-m-d H:i:s') }}</span>
                                    </div>
                                </div>
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-3">
                                    <div>
                                        <dt class="font-medium text-gray-700">Sent by</dt>
                                        <dd class="text-gray-900">{{ $notification->sender->username ?? 'Unknown' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Language</dt>
                                        <dd class="text-gray-900">{{ $notification->locale === 'es' ? 'Spanish' : 'English' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Recipient</dt>
                                        <dd class="text-gray-900">{{ $notification->recipient_email }}</dd>
                                    </div>
                                </dl>
                                @if($notification->status === 'failed' && $notification->error_message)
                                    <div class="border-t border-red-200 pt-3 mt-3">
                                        <p class="text-xs font-medium text-red-700 mb-2">
                                            <i class="fas fa-bug mr-1"></i>Error message:
                                        </p>
                                        <p class="text-sm text-red-900 bg-red-100 p-2 rounded font-mono">{{ $notification->error_message }}</p>
                                    </div>
                                @endif
                                @if($notification->content)
                                    <div class="border-t {{ $notification->status === 'sent' ? 'border-green-200' : ($notification->status === 'failed' ? 'border-red-200' : 'border-blue-200') }} pt-3 mt-3">
                                        <p class="text-xs font-medium text-gray-700 mb-2">Content sent:</p>
                                        <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ $notification->content }}</p>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Internal Notes History -->
            @if($legalReport->notes->count() > 0)
                <div class="border-t pt-4">
                    <h3 class="text-lg font-semibold mb-3">Internal Notes History</h3>
                    <div class="space-y-3">
                        @foreach($legalReport->notes as $note)
                            <div class="bg-gray-50 rounded p-4 border-l-4 border-gray-400">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-900">{{ $note->user->username }}</span>
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
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Actions</h2>
            <div class="flex space-x-3">
                <button type="button" onclick="openStatusModal()" class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    <i class="fas fa-edit mr-2"></i>Update Status & Response
                </button>
                <button type="button" onclick="openNoteModal()" class="px-6 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">
                    <i class="fas fa-plus mr-2"></i>Add Internal Note
                </button>
                <a href="{{ route('admin.legal-reports') }}" class="px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-xl font-semibold">Update Report Status & Response</h2>
                <button type="button" onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.legal-reports.update-status', $legalReport) }}" class="px-6 py-4">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="pending" {{ $legalReport->status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="under_review" {{ $legalReport->status === 'under_review' ? 'selected' : '' }}>Under Review</option>
                            <option value="resolved" {{ $legalReport->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="rejected" {{ $legalReport->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Response to User (Public)</label>
                        <textarea name="user_response" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="This message will be shown to the user when they check the report status...">{{ $legalReport->user_response }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">This response will be visible to the reporter when they check the status of their report.</p>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeStatusModal()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
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

            <form method="POST" action="{{ route('admin.legal-reports.add-note', $legalReport) }}" class="px-6 py-4">
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

    <!-- Notify Modal -->
    <div id="notifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-blue-900">
                    <i class="fas fa-envelope mr-2"></i>Send Notification Email
                </h2>
                <button type="button" onclick="closeNotifyModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 mb-4">
                    This will send an email notification to <strong>{{ $legalReport->reporter_email }}</strong> with the resolution details and your response.
                </p>

                <form method="POST" action="{{ route('admin.legal-reports.notify', $legalReport) }}" id="notifyForm" onsubmit="handleNotifySubmit(event)">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-language mr-1"></i>Email Language
                        </label>
                        <div class="bg-gray-50 rounded p-3 space-y-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="locale" value="en"
                                    {{ $legalReport->locale === 'en' ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    English
                                    @if($legalReport->locale === 'en')
                                        <span class="text-xs text-gray-500">(original report language)</span>
                                    @endif
                                </span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="locale" value="es"
                                    {{ $legalReport->locale === 'es' ? 'checked' : '' }}
                                    class="w-4 h-4 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    Spanish
                                    @if($legalReport->locale === 'es')
                                        <span class="text-xs text-gray-500">(original report language)</span>
                                    @endif
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="sendingIndicator" class="hidden mb-4 bg-blue-50 border border-blue-200 rounded p-3">
                        <div class="flex items-center text-blue-700">
                            <i class="fas fa-spinner fa-spin mr-2"></i>
                            <span class="text-sm font-medium">Sending email notification...</span>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeNotifyModal()" id="cancelNotifyBtn" class="px-6 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" id="sendEmailBtn" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-2"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Status Modal
    function openStatusModal() {
        document.getElementById('statusModal').classList.remove('hidden');
    }

    function closeStatusModal() {
        document.getElementById('statusModal').classList.add('hidden');
    }

    // Note Modal
    function openNoteModal() {
        document.getElementById('noteModal').classList.remove('hidden');
    }

    function closeNoteModal() {
        document.getElementById('noteModal').classList.add('hidden');
    }

    // Notify Modal
    function openNotifyModal() {
        document.getElementById('notifyModal').classList.remove('hidden');
    }

    function closeNotifyModal() {
        document.getElementById('notifyModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('statusModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeStatusModal();
        }
    });

    document.getElementById('noteModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeNoteModal();
        }
    });

    document.getElementById('notifyModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeNotifyModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStatusModal();
            closeNoteModal();
            closeNotifyModal();
        }
    });

    // Handle notification form submission
    function handleNotifySubmit(event) {
        // Show loading indicator
        const sendingIndicator = document.getElementById('sendingIndicator');
        const sendEmailBtn = document.getElementById('sendEmailBtn');
        const cancelNotifyBtn = document.getElementById('cancelNotifyBtn');

        sendingIndicator.classList.remove('hidden');
        sendEmailBtn.disabled = true;
        sendEmailBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        sendEmailBtn.classList.add('opacity-50', 'cursor-not-allowed');
        cancelNotifyBtn.disabled = true;
        cancelNotifyBtn.classList.add('opacity-50', 'cursor-not-allowed');

        // Form will submit normally
        return true;
    }
</script>
@endpush
