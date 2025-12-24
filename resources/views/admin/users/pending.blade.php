@extends('admin.layout')

@section('title', 'Pending Users')
@section('page-title', 'Pending User Approvals')

@section('content')
<div class="space-y-4 md:space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-3 md:grid-cols-3 gap-2 md:gap-6">
        <!-- Pending Users -->
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 md:p-6">
            <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
                <div class="w-full">
                    <p class="text-xs md:text-sm font-medium text-orange-600 uppercase">Pending</p>
                    <p class="text-xl md:text-3xl font-bold text-orange-900 mt-1 md:mt-2">{{ $stats['pending'] }}</p>
                </div>
                <div class="hidden md:block bg-orange-200 rounded-full p-3">
                    <i class="fas fa-user-clock text-2xl text-orange-700"></i>
                </div>
            </div>
        </div>

        <!-- Approved Users -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 md:p-6">
            <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
                <div class="w-full">
                    <p class="text-xs md:text-sm font-medium text-green-600 uppercase">Approved</p>
                    <p class="text-xl md:text-3xl font-bold text-green-900 mt-1 md:mt-2">{{ $stats['approved'] }}</p>
                </div>
                <div class="hidden md:block bg-green-200 rounded-full p-3">
                    <i class="fas fa-user-check text-2xl text-green-700"></i>
                </div>
            </div>
        </div>

        <!-- Rejected Users -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 md:p-6">
            <div class="flex flex-col md:flex-row items-center md:justify-between text-center md:text-left">
                <div class="w-full">
                    <p class="text-xs md:text-sm font-medium text-red-600 uppercase">Rejected</p>
                    <p class="text-xl md:text-3xl font-bold text-red-900 mt-1 md:mt-2">{{ $stats['rejected'] }}</p>
                </div>
                <div class="hidden md:block bg-red-200 rounded-full p-3">
                    <i class="fas fa-user-times text-2xl text-red-700"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Users Table -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200">
        <div class="p-3 md:p-6 border-b border-gray-200">
            <h3 class="text-base md:text-xl font-semibold text-gray-900">
                <i class="fas fa-users mr-2 text-orange-500"></i>
                Users Awaiting Approval
            </h3>
            <p class="text-xs md:text-sm text-gray-600 mt-1">Review and approve or reject new user registrations</p>
        </div>

        @if($users->count() > 0)
            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                            {{ strtoupper(substr($user->username, 0, 1)) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 italic">{{ $user->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->created_at->format('d M Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <!-- Approve Button -->
                                    <form action="{{ route('admin.users.approve', $user->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition-colors"
                                                >
                                            <i class="fas fa-check mr-1"></i>
                                            Approve
                                        </button>
                                    </form>

                                    <!-- Reject Button -->
                                    <button type="button"
                                            onclick="openRejectModal({{ $user->id }}, '{{ $user->username }}')"
                                            class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700 transition-colors">
                                        <i class="fas fa-times mr-1"></i>
                                        Reject
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="md:hidden divide-y divide-gray-200">
                @foreach($users as $user)
                    <div class="p-2">
                        <div class="flex items-center justify-center mb-2">
                            <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold text-base">
                                {{ strtoupper(substr($user->username, 0, 1)) }}
                            </div>
                        </div>
                        <div class="text-center mb-2">
                            <div class="text-sm font-medium text-gray-900 italic">{{ $user->username }}</div>
                            <div class="text-xs text-gray-600 truncate">{{ $user->email }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $user->created_at->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('admin.users.approve', $user->id) }}" method="POST" class="flex-1" onsubmit="return confirmSubmit(this, 'Are you sure you want to approve this user?', {title: 'Approve User', confirmText: 'Approve'})">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-2 py-2 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition-colors"
                                        >
                                    <i class="fas fa-check mr-1"></i>
                                    Approve
                                </button>
                            </form>
                            <button type="button"
                                    onclick="openRejectModal({{ $user->id }}, '{{ $user->username }}')"
                                    class="flex-1 inline-flex items-center justify-center px-2 py-2 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700 transition-colors">
                                <i class="fas fa-times mr-1"></i>
                                Reject
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <x-admin.pagination :paginator="$users" />
        @else
            <div class="p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl font-medium text-gray-500">No Pending Users</p>
                <p class="text-sm text-gray-400 mt-2">All user registrations have been reviewed</p>
            </div>
        @endif
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                    Reject User
                </h3>
                <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <p class="text-sm text-gray-600 mb-4">
                You are about to reject <strong id="rejectUsername"></strong>.
                Please provide a reason for the rejection (required).
            </p>

            <form id="rejectForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Rejection Reason *
                    </label>
                    <textarea
                        id="rejection_reason"
                        name="reason"
                        rows="4"
                        required
                        maxlength="500"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                        placeholder="Explain why this user is being rejected..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Maximum 500 characters</p>
                </div>

                <div class="flex space-x-3">
                    <button
                        type="button"
                        onclick="closeRejectModal()"
                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-ban mr-2"></i>
                        Reject User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openRejectModal(userId, username) {
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    const usernameSpan = document.getElementById('rejectUsername');

    form.action = `/admin/users/${userId}/reject`;
    usernameSpan.textContent = username;
    modal.classList.remove('hidden');
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    const textarea = document.getElementById('rejection_reason');

    modal.classList.add('hidden');
    form.reset();
    textarea.value = '';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});

// Close modal on background click
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>
@endpush
@endsection
