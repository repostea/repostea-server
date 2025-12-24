@extends('admin.layout')

@section('title', 'Deleted Users')
@section('page-title', 'Deleted Users')

@section('content')
<div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
        <div>
            <p class="text-sm text-blue-800 font-medium">Recovery Period</p>
            <p class="text-xs text-blue-700 mt-1">
                Deleted users can be restored within 15 days. After that, they will be permanently deleted automatically.
            </p>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow">
    <!-- Search Form -->
    <x-admin.search-form placeholder="Search deleted users...">
        <x-slot name="filters"></x-slot>
    </x-admin.search-form>

    <!-- Deleted Users Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        User
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Email
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Deleted At
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Days Remaining
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                    @php
                        $deletedDaysAgo = $user->deleted_at->diffInDays(now());
                        $daysRemaining = 15 - $deletedDaysAgo;
                        $isExpiringSoon = $daysRemaining <= 3;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($user->avatar)
                                        <img src="{{ $user->avatar }}" alt="{{ $user->username }}" class="w-full h-full object-cover opacity-50">
                                    @else
                                        <i class="fas fa-user text-gray-300 text-lg"></i>
                                    @endif
                                </div>
                                <div class="ml-3 min-w-0">
                                    <p class="text-sm font-medium text-gray-500 line-through">
                                        {{ $user->username }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-500">{{ mask_email($user->email, 'admin') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $user->deleted_at->format('d/m/Y H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $user->deleted_at->diffForHumans() }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $isExpiringSoon ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-3">
                                <form action="{{ route('admin.users.restore', $user) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-undo mr-1"></i>Restore
                                    </button>
                                </form>
                                <button
                                    onclick="confirmPermanentDelete({{ $user->id }}, '{{ $user->username }}')"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    <i class="fas fa-trash-alt mr-1"></i>Delete Now
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500">No deleted users found</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$users" />
</div>

<!-- Permanent Delete Confirmation Modal -->
<div id="permanentDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>Permanent Delete
        </h3>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="text-sm text-red-800 font-medium">⚠️ This action CANNOT be undone!</p>
        </div>
        <p class="text-gray-700 mb-4">
            Are you sure you want to <strong>permanently delete</strong> the user <strong id="permanentDeleteUsername"></strong>?
        </p>
        <p class="text-sm text-gray-600 mb-4">
            This will immediately and permanently remove all data without the 15-day recovery period.
        </p>
        <form id="permanentDeleteForm" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type "PERMANENT" to confirm</label>
                <input
                    type="text"
                    id="permanentDeleteConfirmation"
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500"
                    placeholder="PERMANENT"
                    required
                >
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hidePermanentDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button
                    type="submit"
                    id="permanentDeleteButton"
                    disabled
                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function confirmPermanentDelete(userId, username) {
        document.getElementById('permanentDeleteUsername').textContent = username;
        document.getElementById('permanentDeleteForm').action = `/admin/users/${userId}/force-delete`;
        document.getElementById('permanentDeleteModal').classList.remove('hidden');
        document.getElementById('permanentDeleteConfirmation').value = '';
        document.getElementById('permanentDeleteButton').disabled = true;
    }

    function hidePermanentDeleteModal() {
        document.getElementById('permanentDeleteModal').classList.add('hidden');
        document.getElementById('permanentDeleteConfirmation').value = '';
        document.getElementById('permanentDeleteButton').disabled = true;
    }

    // Enable delete button only when "PERMANENT" is correctly typed
    document.addEventListener('DOMContentLoaded', function() {
        const deleteInput = document.getElementById('permanentDeleteConfirmation');
        const deleteButton = document.getElementById('permanentDeleteButton');

        if (deleteInput) {
            deleteInput.addEventListener('input', function() {
                if (this.value === 'PERMANENT') {
                    deleteButton.disabled = false;
                } else {
                    deleteButton.disabled = true;
                }
            });
        }
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hidePermanentDeleteModal();
        }
    });
</script>
@endpush
