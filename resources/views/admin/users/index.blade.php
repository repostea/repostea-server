@extends('admin.layout')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="bg-white rounded-lg shadow">
    <!-- Search and Filters -->
    <x-admin.search-form placeholder="Search users...">
        <x-slot name="filters">
            <select
                name="role"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" {{ request('role') === $role->slug ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
            <select
                name="banned"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All status</option>
                <option value="1" {{ request('banned') === '1' ? 'selected' : '' }}>Banned only</option>
            </select>
            <select
                name="verified"
                class="flex-1 md:flex-none px-3 md:px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
                <option value="">All verification</option>
                <option value="1" {{ request('verified') === '1' ? 'selected' : '' }}>Verified only</option>
                <option value="0" {{ request('verified') === '0' ? 'selected' : '' }}>Not verified</option>
            </select>
        </x-slot>
    </x-admin.search-form>

    <!-- Users Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    @php
                        $currentSort = request('sort', 'created_at');
                        $currentDirection = request('direction', 'desc');

                        function sortUrl($column) {
                            $currentSort = request('sort', 'created_at');
                            $currentDirection = request('direction', 'desc');
                            $newDirection = ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
                            return request()->fullUrlWithQuery(['sort' => $column, 'direction' => $newDirection]);
                        }

                        function sortIcon($column) {
                            $currentSort = request('sort', 'created_at');
                            $currentDirection = request('direction', 'desc');
                            if ($currentSort !== $column) {
                                return '<i class="fas fa-sort text-gray-300 ml-1"></i>';
                            }
                            return $currentDirection === 'asc'
                                ? '<i class="fas fa-sort-up text-blue-500 ml-1"></i>'
                                : '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
                        }
                    @endphp

                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ sortUrl('username') }}" class="flex items-center hover:text-gray-700">
                            User
                            {!! sortIcon('username') !!}
                        </a>
                    </th>
                    @can('admin-only')
                        <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="{{ sortUrl('email_verified_at') }}" class="flex items-center hover:text-gray-700">
                                Email Verified
                                {!! sortIcon('email_verified_at') !!}
                            </a>
                        </th>
                    @endcan
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roles</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posts</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Votes</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ sortUrl('karma') }}" class="flex items-center hover:text-gray-700">
                            Karma
                            {!! sortIcon('karma') !!}
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ sortUrl('status') }}" class="flex items-center hover:text-gray-700">
                            Status
                            {!! sortIcon('status') !!}
                        </a>
                    </th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ sortUrl('created_at') }}" class="flex items-center hover:text-gray-700">
                            Joined
                            {!! sortIcon('created_at') !!}
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                                    @if($user->avatar)
                                        <img src="{{ $user->avatar }}" alt="{{ $user->username }}" class="w-full h-full object-cover">
                                    @else
                                        <i class="fas fa-user text-gray-400 text-lg"></i>
                                    @endif
                                </div>
                                <div class="ml-3 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $user->username }}
                                    </p>
                                    <x-admin.action-link :href="config('app.client_url') . '/u/' . $user->username" :external="true" class="text-xs mt-0.5 inline-flex items-center">
                                        View in app
                                    </x-admin.action-link>
                                </div>
                            </div>
                        </td>
                        @can('admin-only')
                            <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                                <p class="text-sm text-gray-900" title="{{ $user->email }}">{{ mask_email($user->email, 'admin') }}</p>
                            </td>
                            <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                                @if($user->email_verified_at)
                                    <div class="flex items-center text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        <span class="text-xs">{{ $user->email_verified_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center text-gray-400">
                                        <i class="fas fa-times-circle mr-1"></i>
                                        <span class="text-xs">Not verified</span>
                                    </div>
                                @endif
                            </td>
                        @endcan
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    <span class="text-sm text-gray-900">{{ $role->name }}</span>@if(!$loop->last), @endif
                                @empty
                                    <span class="text-xs text-gray-400">No roles</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <x-admin.action-link :href="route('admin.posts', ['username' => $user->username])" class="text-sm">
                                {{ number_format($user->posts_count ?? 0) }}
                            </x-admin.action-link>
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <x-admin.action-link :href="route('admin.comments', ['username' => $user->username])" class="text-sm">
                                {{ number_format($user->comments_count ?? 0) }}
                            </x-admin.action-link>
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ number_format($user->votes_count ?? 0) }}</p>
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ number_format($user->karma_points) }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->bans->where('is_active', true)->count() > 0)
                                <x-admin.badge type="banned" label="Banned" />
                            @else
                                <x-admin.badge type="active" label="Active" />
                            @endif
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $user->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-admin.action-link :href="route('admin.users.show', $user)">
                                Edit
                            </x-admin.action-link>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="users" message="No users found" :colspan="Auth::user()->can('admin-only') ? 7 : 6" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Users Cards - Mobile (Simplified) -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($users as $user)
            <div class="p-3">
                <div class="flex items-center gap-3 mb-2">
                    <div class="h-10 w-10 rounded-full flex items-center justify-center overflow-hidden bg-gray-100 flex-shrink-0">
                        @if($user->avatar)
                            <img src="{{ $user->avatar }}" alt="{{ $user->username }}" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-user text-gray-400"></i>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $user->username }}</p>
                    </div>
                </div>
                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    @can('admin-only')
                        <div>
                            <x-admin.mobile-label label="Email" />
                            <span title="{{ $user->email }}">{{ mask_email($user->email, 'admin') }}</span>
                            •
                            <x-admin.mobile-label label="Verified" />
                            @if($user->email_verified_at)
                                <span class="text-green-600">
                                    <i class="fas fa-check-circle mr-1"></i>{{ $user->email_verified_at->format('d/m/Y H:i') }}
                                </span>
                            @else
                                <span class="text-gray-400">
                                    <i class="fas fa-times-circle mr-1"></i>Not verified
                                </span>
                            @endif
                        </div>
                    @endcan
                    <div>
                        <x-admin.mobile-label label="Roles" />
                        @forelse($user->roles as $role)
                            {{ $role->name }}@if(!$loop->last), @endif
                        @empty
                            No roles
                        @endforelse
                        •
                        <x-admin.mobile-label label="Karma" />
                        {{ number_format($user->karma_points) }}
                    </div>
                    <div>
                        <x-admin.mobile-label label="Status" />
                        @if($user->bans->where('is_active', true)->count() > 0)
                            <x-admin.badge type="banned" label="Banned" />
                        @else
                            <x-admin.badge type="active" label="Active" />
                        @endif
                        •
                        <x-admin.mobile-label label="Joined" />
                        {{ $user->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
                <div class="flex gap-3 text-sm">
                    <x-admin.action-link :href="route('admin.users.show', $user)">
                        Edit
                    </x-admin.action-link>
                    <x-admin.action-link :href="config('app.client_url') . '/u/' . $user->username" :external="true">
                        View in app
                    </x-admin.action-link>
                </div>
            </div>
        @empty
            <x-admin.empty-state-mobile icon="users" message="No users found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$users" />
</div>

@endsection
