@extends('admin.layout')

@section('title', 'User: ' . $user->username)
@section('page-title', 'User: ' . $user->username)

@section('content')
<div class="mb-4">
    <x-admin.action-link :href="route('admin.users')">
        <i class="fas fa-arrow-left mr-2"></i>Back to Users
    </x-admin.action-link>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- User Info Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-center">
                <div class="mx-auto h-24 w-24 rounded-full flex items-center justify-center mb-4 overflow-hidden bg-gray-100">
                    @if($user->avatar)
                        <img src="{{ $user->avatar }}" alt="{{ $user->username }}" class="w-full h-full object-cover">
                    @else
                        <i class="fas fa-user text-gray-400 text-4xl"></i>
                    @endif
                </div>
                <h3 class="text-xl font-bold text-gray-900 italic">{{ $user->username }}</h3>
                <x-admin.action-link :href="config('app.client_url') . '/u/' . $user->username" :external="true" class="text-sm mt-1 inline-flex items-center">
                    <i class="fas fa-external-link-alt mr-1"></i>View in app
                </x-admin.action-link>
                @can('admin-only')
                    <p class="text-sm text-gray-500 mt-2" title="{{ $user->email }}">{{ mask_email($user->email, 'admin') }}</p>
                    @if($user->pending_email)
                        <p class="text-xs text-yellow-600 mt-1" title="Pending: {{ $user->pending_email }}">
                            <i class="fas fa-clock mr-1"></i>Pending: {{ mask_email($user->pending_email, 'admin') }}
                        </p>
                    @endif
                @endcan

                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    @foreach($user->roles->where('slug', '!=', 'user') as $role)
                        @php
                            $roleType = match($role->slug) {
                                'admin' => 'purple',
                                'moderator' => 'info',
                                default => 'default'
                            };
                        @endphp
                        <x-admin.badge :type="$roleType" :label="$role->name" />
                    @endforeach
                    @if($user->roles->where('slug', '!=', 'user')->count() === 0)
                        <span class="text-xs text-gray-400 italic">Regular User (no special roles)</span>
                    @endif
                </div>

                <div class="mt-6 space-y-3">
                    <div class="text-left">
                        <p class="text-xs text-gray-500">Karma Points</p>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format($user->karma_points) }}</p>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-gray-500">Posts</p>
                        <x-admin.action-link :href="route('admin.posts', ['user_id' => $user->id])" class="text-lg font-semibold inline-flex items-center">
                            {{ $user->posts->count() }}
                            <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                        </x-admin.action-link>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-gray-500">Comments</p>
                        <x-admin.action-link :href="route('admin.comments', ['user_id' => $user->id])" class="text-lg font-semibold inline-flex items-center">
                            {{ $user->comments->count() }}
                            <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                        </x-admin.action-link>
                    </div>
                    <div class="text-left">
                        <p class="text-xs text-gray-500">Joined</p>
                        <p class="text-sm text-gray-900">{{ $user->created_at->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Moderation Actions -->
            <div class="mt-6 pt-6 border-t border-gray-200 space-y-2">
                @if($user->isBanned())
                    <form action="{{ route('admin.users.unban', $user) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            <i class="fas fa-check mr-2"></i>Unban User
                        </button>
                    </form>
                @else
                    <button onclick="showBanModal()" class="w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        <i class="fas fa-ban mr-2"></i>Ban User
                    </button>
                @endif

                <button onclick="showStrikeModal()" class="w-full px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Give Strike
                </button>

                @can('admin-only')
                    <button onclick="showDeleteUserModal()" class="w-full px-4 py-2 bg-gray-800 text-white rounded hover:bg-gray-900 border border-red-600">
                        <i class="fas fa-trash mr-2"></i>Delete User
                    </button>
                @endcan
            </div>
        </div>
    </div>

    <!-- Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Role Management -->
        @can('admin-only')
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-user-shield mr-2"></i>Role Management
            </h3>

            <!-- Current Roles -->
            <div class="mb-4">
                <p class="text-sm text-gray-700 font-medium mb-3">Current Roles:</p>
                <div class="flex flex-wrap gap-2">
                    @php
                        $specialRoles = $user->roles->where('slug', '!=', 'user');
                    @endphp
                    @forelse($specialRoles as $role)
                        <div class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-lg border-2
                            {{ $role->slug === 'admin' ? 'bg-purple-50 border-purple-200 text-purple-800' : '' }}
                            {{ $role->slug === 'moderator' ? 'bg-blue-50 border-blue-200 text-blue-800' : '' }}
                        ">
                            <i class="fas fa-shield-alt mr-2"></i>
                            {{ $role->name }}
                            <form method="POST" action="{{ route('admin.users.remove-role', $user) }}" class="inline ml-2" onsubmit="return confirmSubmit(this, 'Remove {{ $role->name }} role from {{ $user->username }}?', {title: 'Remove Role', type: 'danger', confirmText: 'Remove'})">
                                @csrf
                                <input type="hidden" name="role_id" value="{{ $role->id }}">
                                <button type="submit" class="hover:opacity-70 text-red-600" title="Remove role">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <span class="text-sm text-gray-400 italic">No special roles - Regular user</span>
                    @endforelse
                </div>
            </div>

            <!-- Add Role -->
            <div class="pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-700 font-medium mb-3">Assign New Role:</p>
                <form method="POST" action="{{ route('admin.users.assign-role', $user) }}" class="flex gap-3">
                    @csrf
                    <select name="role_id" class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select a role to assign...</option>
                        @foreach($allRoles as $role)
                            @if(!$user->roles->contains($role->id) && $role->slug !== 'user')
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition-colors">
                        <i class="fas fa-plus mr-2"></i>Assign Role
                    </button>
                </form>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <div class="flex items-start justify-between">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> Role changes take effect immediately. Admin and Moderator roles grant access to the admin panel.
                    </p>
                    <button onclick="showRolesHelpModal()" class="text-xs text-blue-600 hover:text-blue-800 font-semibold whitespace-nowrap ml-2">
                        <i class="fas fa-question-circle mr-1"></i>What do roles do?
                    </button>
                </div>
            </div>
        </div>
        @endcan

        <!-- Special Permissions (Admin Only) -->
        @can('admin-only')
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-key mr-2"></i>Special Permissions
                <span class="text-xs font-normal text-gray-500">(Admin Only)</span>
            </h3>

            <div class="space-y-4">
                <!-- Can Create Subs -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-users-rectangle text-blue-500"></i>
                            <span class="font-medium text-gray-900">Can Create Communities</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Allow this user to create communities without karma requirements
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.users.toggle-permission', $user) }}">
                        @csrf
                        <input type="hidden" name="permission" value="can_create_subs">
                        <button type="submit" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $user->can_create_subs ? 'bg-blue-600' : 'bg-gray-200' }}">
                            <span class="sr-only">Toggle can create subs</span>
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $user->can_create_subs ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <p class="text-xs text-blue-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Note:</strong> These permissions bypass the normal requirements. Use with caution.
                </p>
            </div>
        </div>
        @endcan

        <!-- Achievement Management (Admin Only) -->
        @can('admin-only')
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-trophy mr-2"></i>Achievement Management
                <span class="text-xs font-normal text-gray-500">(Admin Only)</span>
            </h3>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-4">
                    <button type="button" onclick="switchTab('special')" id="tab-special" class="tab-button active px-4 py-2 border-b-2 border-blue-600 text-sm font-medium text-blue-600">
                        <i class="fas fa-trophy mr-1"></i>Special Achievements
                    </button>
                    <button type="button" onclick="switchTab('normal')" id="tab-normal" class="tab-button px-4 py-2 border-b-2 border-transparent text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-star mr-1"></i>Normal Achievements
                    </button>
                </nav>
            </div>

            <!-- Special Achievements Tab -->
            <div id="content-special" class="tab-content">
                <!-- Current Special Achievements -->
                <div class="mb-4">
                    @php
                        $specialAchievementsUser = $user->achievements->filter(function($a) {
                            return str_contains($a->slug, 'collaborator_');
                        })->sortByDesc('pivot.created_at');
                    @endphp
                    <p class="text-sm text-gray-700 font-medium mb-3">Current Special Achievements ({{ $specialAchievementsUser->count() }}):</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                        @forelse($specialAchievementsUser as $achievement)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <i class="{{ $achievement->icon }} text-yellow-500 text-lg flex-shrink-0"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            @if(str_contains($achievement->slug, 'bronze'))
                                                🥉
                                            @elseif(str_contains($achievement->slug, 'silver'))
                                                🥈
                                            @elseif(str_contains($achievement->slug, 'gold'))
                                                🥇
                                            @endif
                                            {{ __($achievement->name) }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                Special
                                            </span>
                                            • +{{ $achievement->karma_bonus }} karma
                                        </p>
                                    </div>
                                </div>
                                <button type="button"
                                        onclick="showRemoveAchievementModal({{ $achievement->id }}, '{{ addslashes(__($achievement->name)) }}', '{{ route('admin.users.remove-achievement', [$user, $achievement]) }}')"
                                        class="text-red-600 hover:text-red-800 text-sm"
                                        title="Remove achievement">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic col-span-2">No special achievements yet</p>
                        @endforelse
                    </div>
                </div>

                <!-- Assign Collaborator Achievement -->
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-700 font-medium mb-3">Assign Collaborator Achievement:</p>
                    <form method="POST" action="{{ route('admin.users.assign-achievement', $user) }}" class="space-y-3">
                        @csrf
                        <select name="achievement_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">Select collaborator level...</option>
                            @foreach($collaboratorAchievements as $achievement)
                                @if(!$user->achievements->contains($achievement->id))
                                    <option value="{{ $achievement->id }}">
                                        @if(str_contains($achievement->slug, 'bronze'))
                                            🥉 Bronze
                                        @elseif(str_contains($achievement->slug, 'silver'))
                                            🥈 Silver
                                        @elseif(str_contains($achievement->slug, 'gold'))
                                            🥇 Gold
                                        @endif
                                        - {{ __($achievement->name) }} (+{{ $achievement->karma_bonus }} karma)
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition-colors">
                            <i class="fas fa-trophy mr-2"></i>Assign Achievement
                        </button>
                    </form>
                </div>

                <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Collaborators:</strong> For people who help with development by reporting bugs, suggesting improvements, or contributing code.
                    </p>
                    <ul class="mt-2 text-xs text-blue-800 space-y-1 ml-4">
                        <li>🥉 <strong>Bronze:</strong> Minor contributions (50 karma)</li>
                        <li>🥈 <strong>Silver:</strong> Regular contributions (100 karma)</li>
                        <li>🥇 <strong>Gold:</strong> Significant contributions (200 karma)</li>
                    </ul>
                </div>
            </div>

            <!-- Normal Achievements Tab -->
            <div id="content-normal" class="tab-content hidden">
                <!-- Current Normal Achievements -->
                <div class="mb-4">
                    @php
                        $normalAchievementsUser = $user->achievements->filter(function($a) {
                            return !str_contains($a->slug, 'collaborator_');
                        })->sortByDesc('pivot.created_at');
                    @endphp
                    <p class="text-sm text-gray-700 font-medium mb-3">Current Normal Achievements ({{ $normalAchievementsUser->count() }}):</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                        @forelse($normalAchievementsUser as $achievement)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <i class="{{ $achievement->icon }} text-yellow-500 text-lg flex-shrink-0"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ __($achievement->name) }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ ucfirst($achievement->type) }}
                                            • +{{ $achievement->karma_bonus }} karma
                                        </p>
                                    </div>
                                </div>
                                <button type="button"
                                        onclick="showRemoveAchievementModal({{ $achievement->id }}, '{{ addslashes(__($achievement->name)) }}', '{{ route('admin.users.remove-achievement', [$user, $achievement]) }}')"
                                        class="text-red-600 hover:text-red-800 text-sm"
                                        title="Remove achievement">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400 italic col-span-2">No normal achievements yet</p>
                        @endforelse
                    </div>
                </div>

                <!-- Assign Normal Achievement -->
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-700 font-medium mb-3">Assign Normal Achievement:</p>
                </div>
                <form method="POST" action="{{ route('admin.users.assign-achievement', $user) }}" class="space-y-3">
                    @csrf
                    <select name="achievement_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select an achievement...</option>
                        @foreach($normalAchievements->groupBy('type') as $type => $achievements)
                            <optgroup label="{{ ucfirst($type) }}">
                                @foreach($achievements as $achievement)
                                    @if(!$user->achievements->contains($achievement->id))
                                        <option value="{{ $achievement->id }}">
                                            {{ __($achievement->name) }} (+{{ $achievement->karma_bonus }} karma)
                                        </option>
                                    @endif
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm transition-colors">
                        <i class="fas fa-trophy mr-2"></i>Assign Achievement
                    </button>
                </form>

                <div class="mt-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <p class="text-xs text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Note:</strong> Most of these achievements are earned automatically through activity. Only assign manually in special cases.
                    </p>
                </div>
            </div>
        </div>
        @endcan

        <!-- Active Bans -->
        @if($user->bans->where('is_active', true)->count() > 0)
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-red-900 mb-4">
                    <i class="fas fa-ban mr-2"></i>Active Bans
                </h3>
                @foreach($user->bans->where('is_active', true) as $ban)
                    <div class="bg-white rounded p-4 mb-3">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <x-admin.badge :type="$ban->type === 'permanent' ? 'danger' : 'warning'" :label="ucfirst($ban->type)" />
                                <p class="text-sm text-gray-900 mt-2"><strong>Reason:</strong> {{ $ban->reason }}</p>
                                @if($ban->internal_notes)
                                    <p class="text-xs text-gray-600 mt-1"><strong>Notes:</strong> {{ $ban->internal_notes }}</p>
                                @endif
                                @if($ban->expires_at)
                                    <p class="text-xs text-gray-600 mt-1">Expires: {{ $ban->expires_at->format('d M Y H:i') }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-1">
                                    By <span class="italic">{{ $ban->bannedBy->username ?? 'System' }}</span> on {{ $ban->created_at->format('d M Y H:i') }}
                                </p>
                            </div>
                            <button onclick="showEditBanModal({{ $ban->id }}, '{{ $ban->type }}', '{{ addslashes($ban->reason) }}', '{{ addslashes($ban->internal_notes ?? '') }}', {{ $ban->expires_at ? "'".$ban->expires_at->diffInDays(now())."'" : 'null' }})" class="ml-4 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Strikes -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Strikes History</h3>
            </div>
            <div class="p-6">
                @forelse($user->strikes as $strike)
                    <div class="border-l-4 {{ $strike->is_active ? 'border-orange-500 bg-orange-50' : 'border-gray-300 bg-gray-50' }} pl-4 py-3 mb-3">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $strike->type === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $strike->type === 'major' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ $strike->type === 'minor' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $strike->type === 'warning' ? 'bg-blue-100 text-blue-800' : '' }}
                                    ">
                                        {{ ucfirst($strike->type) }}
                                    </span>
                                    @if($strike->is_active)
                                        <span class="text-xs text-green-600 font-semibold">Active</span>
                                    @else
                                        <span class="text-xs text-gray-400">Expired</span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-900 mt-2">{{ $strike->reason }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    By {{ $strike->issuedBy->username ?? 'System' }} on {{ $strike->created_at->format('d M Y H:i') }}
                                </p>
                                @if($strike->expires_at)
                                    <p class="text-xs text-gray-600 mt-1">Expires: {{ $strike->expires_at->format('d M Y H:i') }}</p>
                                @endif
                            </div>
                            @if($strike->is_active)
                                <div class="flex space-x-3">
                                    <button onclick="showEditStrikeModal({{ $strike->id }}, '{{ $strike->type }}', '{{ addslashes($strike->reason) }}', '{{ addslashes($strike->internal_notes ?? '') }}', {{ $strike->expires_at ? "'".$strike->expires_at->diffInDays(now())."'" : 'null' }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <form action="{{ route('admin.strikes.remove', $strike) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Remove</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-center text-gray-500 py-8">No strikes</p>
                @endforelse
            </div>
        </div>

        <!-- Invitations -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-envelope mr-2"></i>Invitations
                </h3>
            </div>
            <div class="p-6">
                <!-- Invitation Stats -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase">Limit</p>
                        <p class="text-2xl font-bold text-blue-600">
                            @if($user->getInvitationLimit() === PHP_INT_MAX)
                                ∞
                            @else
                                {{ $user->getInvitationLimit() }}
                            @endif
                        </p>
                        @if($user->invitation_limit !== null)
                            <p class="text-xs text-gray-500 mt-1">(Custom)</p>
                        @else
                            <p class="text-xs text-gray-500 mt-1">(Karma-based)</p>
                        @endif
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase">Used</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ $user->getInvitationCount() }}
                        </p>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase">Remaining</p>
                        <p class="text-2xl font-bold text-green-600">
                            @if($user->getRemainingInvitations() === PHP_INT_MAX)
                                ∞
                            @else
                                {{ $user->getRemainingInvitations() }}
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Adjust Invitation Limit -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Adjust Invitation Limit</h4>
                    <div class="flex space-x-2">
                        <form action="{{ route('admin.users.invitation-limit', $user) }}" method="POST" class="flex-1">
                            @csrf
                            <div class="flex space-x-2">
                                <input
                                    type="number"
                                    name="invitation_limit"
                                    min="0"
                                    max="10000"
                                    value="{{ $user->invitation_limit ?? $user->getInvitationLimit() }}"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Invitation limit"
                                >
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 whitespace-nowrap">
                                    <i class="fas fa-save mr-1"></i>Set Limit
                                </button>
                            </div>
                        </form>

                        @if($user->invitation_limit !== null)
                            <form action="{{ route('admin.users.invitation-limit.reset', $user) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 whitespace-nowrap">
                                    <i class="fas fa-undo mr-1"></i>Reset to Default
                                </button>
                            </form>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Default karma-based limit for {{ $user->karma_points ?? 0 }} karma:
                        @php
                            $karmaLimits = config('invitations.karma_limits', [0 => 5]);
                            $karma = $user->karma_points ?? 0;
                            $karmaLimit = 5;
                            foreach ($karmaLimits as $threshold => $limit) {
                                if ($karma >= $threshold) {
                                    $karmaLimit = $limit;
                                }
                            }
                        @endphp
                        <strong>{{ $karmaLimit }}</strong>
                    </p>
                </div>

                <!-- User's Invitations -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Created Invitations</h4>
                    @php
                        $invitations = $user->invitations()->orderBy('created_at', 'desc')->get();
                    @endphp

                    @forelse($invitations as $invitation)
                        <div class="border border-gray-200 rounded-lg p-4 mb-3 {{ $invitation->is_active ? 'bg-white' : 'bg-gray-50' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <span class="font-mono text-sm font-semibold text-gray-900">{{ $invitation->code }}</span>

                                        @if($invitation->is_active && $invitation->isValid())
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Inactive
                                            </span>
                                        @endif

                                        @if($invitation->expires_at && $invitation->expires_at->isPast())
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                Expired
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-xs text-gray-600 space-y-1">
                                        <p>
                                            <i class="fas fa-users w-4"></i>
                                            Uses: <strong>{{ $invitation->current_uses }}/{{ $invitation->max_uses }}</strong>
                                        </p>
                                        <p>
                                            <i class="fas fa-calendar w-4"></i>
                                            Created: {{ $invitation->created_at->format('d M Y H:i') }}
                                        </p>
                                        @if($invitation->expires_at)
                                            <p>
                                                <i class="fas fa-clock w-4"></i>
                                                Expires: {{ $invitation->expires_at->format('d M Y H:i') }}
                                                @if(!$invitation->expires_at->isPast())
                                                    ({{ $invitation->expires_at->diffForHumans() }})
                                                @endif
                                            </p>
                                        @endif
                                        @if($invitation->used_at)
                                            <p>
                                                <i class="fas fa-check w-4"></i>
                                                First used: {{ $invitation->used_at->format('d M Y H:i') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="ml-4">
                                    @if($invitation->current_uses === 0)
                                        <button
                                            onclick="copyToClipboard('{{ config('app.client_url') }}/auth/register?invitation={{ $invitation->code }}')"
                                            class="text-blue-600 hover:text-blue-800 text-xs"
                                            title="Copy invitation URL"
                                        >
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-gray-500 py-6 text-sm">No invitations created yet</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Email Change History (Admin Only) -->
        @can('admin-only')
        @if($emailChanges->count() > 0)
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-envelope mr-2"></i>Email Change History
                </h3>
            </div>
            <div class="divide-y divide-gray-200">
                @foreach($emailChanges as $change)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-2 mb-2">
                                    <span class="text-sm font-mono text-gray-500">{{ $change->metadata['old_email'] ?? 'Unknown' }}</span>
                                    <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                    <span class="text-sm font-mono text-gray-900 font-semibold">{{ $change->metadata['new_email'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="text-xs text-gray-500 space-y-1">
                                    <p>
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        {{ \Carbon\Carbon::parse($change->metadata['changed_at'] ?? $change->created_at)->format('d M Y H:i:s') }}
                                    </p>
                                    @if(isset($change->metadata['ip_address']))
                                        <p>
                                            <i class="fas fa-network-wired mr-1"></i>
                                            IP: {{ $change->metadata['ip_address'] }}
                                        </p>
                                    @endif
                                    @if(isset($change->metadata['user_agent']))
                                        <p class="truncate max-w-md" title="{{ $change->metadata['user_agent'] }}">
                                            <i class="fas fa-desktop mr-1"></i>
                                            {{ Str::limit($change->metadata['user_agent'], 50) }}
                                        </p>
                                    @endif
                                    @if(isset($change->metadata['verification_method']))
                                        <p>
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            Method: {{ ucwords(str_replace('_', ' ', $change->metadata['verification_method'])) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        @endcan

        <!-- Moderation Logs -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Moderation History</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($moderationLogs as $log)
                    <div class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">
                            {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            By {{ $log->moderator?->username ?? 'System' }} • {{ $log->created_at->diffForHumans() }}
                        </p>
                        @if($log->reason)
                            <p class="text-xs text-gray-600 mt-1 italic">"{{ $log->reason }}"</p>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500">
                        <p>No moderation history</p>
                    </div>
                @endforelse
            </div>
            <x-admin.pagination :paginator="$moderationLogs" />
        </div>
    </div>
</div>

<!-- Ban Modal -->
<div id="banModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Ban User</h3>
        <form action="{{ route('admin.users.ban', $user) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ban Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="temporary">Temporary</option>
                        <option value="permanent">Permanent</option>
                        <option value="shadowban">Shadowban</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) - Only for temporary</label>
                    <input type="number" name="duration_days" min="1" max="365" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="7">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required placeholder="Explain why this user is being banned..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes (optional)</label>
                    <textarea name="internal_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Private notes for moderators..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideBanModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Ban User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Strike Modal -->
<div id="strikeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Give Strike</h3>
        <form action="{{ route('admin.users.strike', $user) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Strike Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="warning">Warning</option>
                        <option value="minor">Minor</option>
                        <option value="major">Major</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) - Optional</label>
                    <input type="number" name="duration_days" min="1" max="365" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Leave empty for permanent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required placeholder="Explain why this strike is being given..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes (optional)</label>
                    <textarea name="internal_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Private notes for moderators..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideStrikeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    Give Strike
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-trash text-orange-600 mr-2"></i>Delete User
        </h3>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <p class="text-sm text-blue-800 font-medium">
                <i class="fas fa-info-circle mr-1"></i>15-day recovery period
            </p>
            <p class="text-xs text-blue-700 mt-1">
                The user will be hidden but can be restored within 15 days. After that, all data will be permanently deleted.
            </p>
        </div>
        <p class="text-gray-700 mb-4">
            Are you sure you want to delete the user <strong>{{ $user->username }}</strong>?
        </p>
        <p class="text-sm text-gray-600 mb-4">
            What will happen:
        </p>
        <ul class="text-sm text-gray-600 mb-4 list-disc list-inside space-y-1">
            <li>User account will be hidden immediately</li>
            <li>Login will be disabled</li>
            <li>Posts and comments become anonymous</li>
            <li>Can be restored from "Deleted Users" within 15 days</li>
        </ul>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
            <p class="text-xs text-yellow-800">
                <i class="fas fa-clock mr-1"></i>After 15 days, the account will be <strong>permanently deleted</strong> and cannot be recovered.
            </p>
        </div>
        <form action="{{ route('admin.users.destroy', $user) }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type "{{ $user->username }}" to confirm</label>
                <input
                    type="text"
                    id="deleteConfirmation"
                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-red-500"
                    placeholder="{{ $user->username }}"
                    required
                >
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hideDeleteUserModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button
                    type="submit"
                    id="deleteUserButton"
                    disabled
                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                >
                    Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Ban Modal -->
<div id="editBanModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Ban</h3>
        <form id="editBanForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ban Type</label>
                    <select id="editBanType" name="type" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="temporary">Temporary</option>
                        <option value="permanent">Permanent</option>
                        <option value="shadowban">Shadowban</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) - Only for temporary</label>
                    <input type="number" id="editBanDuration" name="duration_days" min="1" max="365" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="7">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea id="editBanReason" name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required placeholder="Explain why this user is being banned..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes (optional)</label>
                    <textarea id="editBanNotes" name="internal_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Private notes for moderators..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideEditBanModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Update Ban
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Strike Modal -->
<div id="editStrikeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Edit Strike</h3>
        <form id="editStrikeForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Strike Type</label>
                    <select id="editStrikeType" name="type" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required>
                        <option value="warning">Warning</option>
                        <option value="minor">Minor</option>
                        <option value="major">Major</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) - Optional</label>
                    <input type="number" id="editStrikeDuration" name="duration_days" min="1" max="365" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Leave empty for permanent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea id="editStrikeReason" name="reason" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" required placeholder="Explain why this strike is being given..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes (optional)</label>
                    <textarea id="editStrikeNotes" name="internal_notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" placeholder="Private notes for moderators..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex space-x-3">
                <button type="button" onclick="hideEditStrikeModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Update Strike
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Remove Achievement Modal -->
<div id="removeAchievementModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="ml-4 text-lg font-semibold text-gray-900">Remove Achievement</h3>
        </div>

        <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to remove the achievement <strong id="removeAchievementName" class="text-gray-900"></strong> from <strong class="text-gray-900">{{ $user->username }}</strong>?
        </p>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
            <p class="text-xs text-yellow-800">
                <i class="fas fa-info-circle mr-1"></i>
                The karma bonus associated with this achievement will be deducted from the user's total.
            </p>
        </div>

        <form id="removeAchievementForm" method="POST">
            @csrf
            @method('DELETE')
            <div class="flex space-x-3">
                <button type="button" onclick="hideRemoveAchievementModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i>Remove
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Roles Help Modal -->
<div id="rolesHelpModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-900">
                <i class="fas fa-user-shield mr-2 text-blue-600"></i>Role Permissions Guide
            </h3>
            <button onclick="hideRolesHelpModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="p-6 space-y-6">
            <!-- Admin Role -->
            <div class="border-2 border-purple-200 rounded-lg p-4 bg-purple-50">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-crown text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="text-lg font-bold text-purple-900">Admin (Administrator)</h4>
                        <p class="text-sm text-purple-700 mt-1">Maximum level of access - Full control of the platform</p>
                        <div class="mt-3 space-y-2">
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Full access to admin panel</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Manage user roles (assign/remove)</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Execute scheduled commands</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>All moderation powers</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>View system logs and analytics</p>
                        </div>
                        <div class="mt-3 p-2 bg-purple-100 rounded text-xs text-purple-800">
                            <strong>Use for:</strong> Platform owners and main administrators (1-3 trusted people)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Moderator Role -->
            <div class="border-2 border-blue-200 rounded-lg p-4 bg-blue-50">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-shield-alt text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="text-lg font-bold text-blue-900">Moderator</h4>
                        <p class="text-sm text-blue-700 mt-1">Content moderation and community management</p>
                        <div class="mt-3 space-y-2">
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Access admin panel (limited)</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Hide/show posts</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Ban/unban users</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Manage reports</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>Cannot manage roles</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>Cannot execute commands</p>
                        </div>
                        <div class="mt-3 p-2 bg-blue-100 rounded text-xs text-blue-800">
                            <strong>Use for:</strong> Moderation team that manages community daily (5-10 people)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Editor Role -->
            <div class="border-2 border-yellow-200 rounded-lg p-4 bg-yellow-50">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-pen text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="text-lg font-bold text-yellow-900">Editor</h4>
                        <p class="text-sm text-yellow-700 mt-1">Content editing and review</p>
                        <div class="mt-3 space-y-2">
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Edit posts and comments</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Review and approve content</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Limited admin panel access</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>Cannot ban users (usually)</p>
                        </div>
                        <div class="mt-3 p-2 bg-yellow-100 rounded text-xs text-yellow-800">
                            <strong>Use for:</strong> Editorial team that reviews and improves content quality
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expert Role -->
            <div class="border-2 border-green-200 rounded-lg p-4 bg-green-50">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-certificate text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="text-lg font-bold text-green-900">Expert (Verified)</h4>
                        <p class="text-sm text-green-700 mt-1">Verified expert in their field - Public recognition</p>
                        <div class="mt-3 space-y-2">
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Verified badge on profile</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Special badge on posts/comments</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Increased credibility</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>May earn more karma</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>No admin panel access</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>No moderation powers</p>
                        </div>
                        <div class="mt-3 p-2 bg-green-100 rounded text-xs text-green-800">
                            <strong>Use for:</strong> Verified professionals, scientists, recognized contributors
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Role (Regular User) -->
            <div class="border-2 border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-gray-600 rounded-lg flex items-center justify-center text-white">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h4 class="text-lg font-bold text-gray-900">Regular User (No Role)</h4>
                        <p class="text-sm text-gray-700 mt-1">Default permissions - Standard community member</p>
                        <div class="mt-3 space-y-2">
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Post content</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Comment and vote</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Report inappropriate content</p>
                            <p class="text-sm"><i class="fas fa-check text-green-600 mr-2"></i>Edit own content</p>
                            <p class="text-sm"><i class="fas fa-times text-red-600 mr-2"></i>No admin access</p>
                        </div>
                        <div class="mt-3 p-2 bg-gray-100 rounded text-xs text-gray-800">
                            <strong>Default:</strong> All registered users without special roles
                        </div>
                    </div>
                </div>
            </div>

            <!-- Important Notes -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-bold text-blue-900 mb-2"><i class="fas fa-info-circle mr-2"></i>Important Notes</h4>
                <ul class="space-y-1 text-sm text-blue-800">
                    <li><i class="fas fa-bolt mr-2"></i>Role changes take effect immediately</li>
                    <li><i class="fas fa-users mr-2"></i>Users can have multiple roles simultaneously</li>
                    <li><i class="fas fa-shield-alt mr-2"></i>Only Admins can manage roles</li>
                    <li><i class="fas fa-exclamation-triangle mr-2"></i>Be careful when assigning Admin role - it has full system access</li>
                </ul>
            </div>
        </div>

        <div class="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4">
            <button onclick="hideRolesHelpModal()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                Got it, thanks!
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function showBanModal() {
        document.getElementById('banModal').classList.remove('hidden');
    }
    function hideBanModal() {
        document.getElementById('banModal').classList.add('hidden');
    }
    function showStrikeModal() {
        document.getElementById('strikeModal').classList.remove('hidden');
    }
    function hideStrikeModal() {
        document.getElementById('strikeModal').classList.add('hidden');
    }
    function showDeleteUserModal() {
        document.getElementById('deleteUserModal').classList.remove('hidden');
        document.getElementById('deleteConfirmation').value = '';
        document.getElementById('deleteUserButton').disabled = true;
    }
    function hideDeleteUserModal() {
        document.getElementById('deleteUserModal').classList.add('hidden');
        document.getElementById('deleteConfirmation').value = '';
        document.getElementById('deleteUserButton').disabled = true;
    }

    // Enable delete button only when username is correctly typed
    document.addEventListener('DOMContentLoaded', function() {
        const deleteInput = document.getElementById('deleteConfirmation');
        const deleteButton = document.getElementById('deleteUserButton');
        const expectedUsername = '{{ $user->username }}';

        if (deleteInput) {
            deleteInput.addEventListener('input', function() {
                if (this.value === expectedUsername) {
                    deleteButton.disabled = false;
                } else {
                    deleteButton.disabled = true;
                }
            });
        }
    });

    function showEditBanModal(banId, type, reason, notes, duration) {
        document.getElementById('editBanForm').action = '/admin/bans/' + banId;
        document.getElementById('editBanType').value = type;
        document.getElementById('editBanReason').value = reason;
        document.getElementById('editBanNotes').value = notes || '';
        document.getElementById('editBanDuration').value = duration || '';
        document.getElementById('editBanModal').classList.remove('hidden');
    }

    function hideEditBanModal() {
        document.getElementById('editBanModal').classList.add('hidden');
    }

    function showEditStrikeModal(strikeId, type, reason, notes, duration) {
        document.getElementById('editStrikeForm').action = '/admin/strikes/' + strikeId;
        document.getElementById('editStrikeType').value = type;
        document.getElementById('editStrikeReason').value = reason;
        document.getElementById('editStrikeNotes').value = notes || '';
        document.getElementById('editStrikeDuration').value = duration || '';
        document.getElementById('editStrikeModal').classList.remove('hidden');
    }

    function hideEditStrikeModal() {
        document.getElementById('editStrikeModal').classList.add('hidden');
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Invitation URL copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
            alert('Failed to copy to clipboard. Please copy manually.');
        });
    }

    function showRolesHelpModal() {
        document.getElementById('rolesHelpModal').classList.remove('hidden');
    }

    function hideRolesHelpModal() {
        document.getElementById('rolesHelpModal').classList.add('hidden');
    }

    function showRemoveAchievementModal(achievementId, achievementName, actionUrl) {
        document.getElementById('removeAchievementName').textContent = achievementName;
        document.getElementById('removeAchievementForm').action = actionUrl;
        document.getElementById('removeAchievementModal').classList.remove('hidden');
    }

    function hideRemoveAchievementModal() {
        document.getElementById('removeAchievementModal').classList.add('hidden');
    }

    function switchTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active', 'border-blue-600', 'text-blue-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });

        // Show selected tab
        document.getElementById('content-' + tabName).classList.remove('hidden');
        // Add active class to selected button
        const activeButton = document.getElementById('tab-' + tabName);
        activeButton.classList.add('active', 'border-blue-600', 'text-blue-600');
        activeButton.classList.remove('border-transparent', 'text-gray-500');
    }
</script>
@endpush
