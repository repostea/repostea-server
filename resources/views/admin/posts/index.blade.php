@extends('admin.layout')

@section('title', 'Posts')
@section('page-title', 'Post Management')

@section('content')

@php
    $currentSort = request('sort', 'created_at');
    $currentDirection = request('direction', 'desc');
@endphp
<div class="bg-white rounded-lg shadow">
    <!-- Search and Filters -->
    <x-admin.search-form placeholder="Search posts...">
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
                <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="hidden" {{ request('status') === 'hidden' ? 'selected' : '' }}>Hidden</option>
            </select>
            @if(request('search') || request('status') || request('username'))
                <a href="{{ route('admin.posts') }}" class="px-4 md:px-6 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 whitespace-nowrap">
                    <i class="fas fa-times md:mr-2"></i><span class="hidden md:inline">Clear</span>
                </a>
            @endif
        </x-slot>
    </x-admin.search-form>

    @if(request('username') && $posts->total() === 0)
        <div class="px-6 py-4 bg-yellow-50 border-b border-yellow-100">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <p class="text-sm text-yellow-800">
                    No user found with username containing "<strong>{{ request('username') }}</strong>". Try a different search.
                </p>
            </div>
        </div>
    @endif

    <!-- Posts Table - Desktop -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post</th>
                    <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'direction' => ($currentSort === 'created_at' && $currentDirection === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                            Created @if($currentSort === 'created_at') {!! $currentDirection === 'asc' ? '↑' : '↓' !!} @endif
                        </a>
                    </th>
                    <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'updated_at', 'direction' => ($currentSort === 'updated_at' && $currentDirection === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                            Modified @if($currentSort === 'updated_at') {!! $currentDirection === 'asc' ? '↑' : '↓' !!} @endif
                        </a>
                    </th>
                    <th class="hidden 2xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'total_visitors', 'direction' => ($currentSort === 'total_visitors' && $currentDirection === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                            Visitors @if($currentSort === 'total_visitors') {!! $currentDirection === 'asc' ? '↑' : '↓' !!} @endif
                        </a>
                    </th>
                    <th class="hidden 2xl:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'identified_visitors', 'direction' => ($currentSort === 'identified_visitors' && $currentDirection === 'asc') ? 'desc' : 'asc']) }}" class="hover:text-gray-700">
                            Identified @if($currentSort === 'identified_visitors') {!! $currentDirection === 'asc' ? '↑' : '↓' !!} @endif
                        </a>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($posts as $post)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="max-w-md">
                                <p class="text-sm font-medium text-gray-900 mb-1 line-clamp-2">
                                    {{ $post->title }}
                                </p>
                                <x-admin.action-link :href="config('app.client_url') . $post->getPermalinkUrl()" :external="true" class="text-xs">
                                    View in app
                                </x-admin.action-link>
                            </div>
                        </td>
                        <td class="hidden lg:table-cell px-6 py-4 whitespace-nowrap">
                            @if($post->hasDeletedAuthor())
                                <span class="text-sm font-medium block italic text-gray-400">
                                    {{ $post->getDisplayUsername() }}
                                </span>
                            @else
                                <x-admin.action-link :href="route('admin.users.show', $post->user_id)" class="text-sm font-medium block italic">
                                    {{ $post->user->username }}
                                </x-admin.action-link>
                                <x-admin.action-link :href="config('app.client_url') . '/u/' . $post->user->username" :external="true" class="text-xs mt-0.5 inline-flex items-center">
                                    View in app
                                </x-admin.action-link>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-admin.badge :type="$post->status" :label="ucfirst($post->status)" />
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $post->created_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $post->created_at->format('H:i') }}</p>
                        </td>
                        <td class="hidden xl:table-cell px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $post->updated_at->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $post->updated_at->format('H:i') }}</p>
                        </td>
                        <td class="hidden 2xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $post->total_visitors ?? 0 }}
                        </td>
                        <td class="hidden 2xl:table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $post->identified_visitors ?? 0 }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <x-admin.action-link :href="route('admin.posts.view', $post)">
                                Edit
                            </x-admin.action-link>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-state icon="file-alt" message="No posts found" colspan="8" />
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Posts Cards - Mobile (Simplified) -->
    <div class="md:hidden divide-y divide-gray-200">
        @forelse($posts as $post)
            <div class="p-3">
                <p class="text-sm font-medium text-gray-900 mb-1 line-clamp-2">
                    {{ $post->title }}
                </p>
                <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                    <div>
                        <span class="text-gray-500">by</span>
                        @if($post->hasDeletedAuthor())
                            <span class="font-medium italic text-gray-400">
                                {{ $post->getDisplayUsername() }}
                            </span>
                        @else
                            <x-admin.action-link :href="route('admin.users.show', $post->user_id)" class="font-medium italic">
                                {{ $post->user->username }}
                            </x-admin.action-link>
                        @endif
                        •
                        <x-admin.mobile-label label="Status" />
                        <x-admin.badge :type="$post->status" :label="ucfirst($post->status)" />
                    </div>
                    <div>
                        <x-admin.mobile-label label="Created" />
                        {{ $post->created_at->format('d/m/Y H:i') }}
                    </div>
                    @if($post->updated_at && $post->updated_at != $post->created_at)
                        <div>
                            <x-admin.mobile-label label="Modified" />
                            {{ $post->updated_at->format('d/m/Y H:i') }}
                        </div>
                    @endif
                </div>
                <div class="flex gap-3 text-sm">
                    <x-admin.action-link :href="route('admin.posts.view', $post)">
                        Edit
                    </x-admin.action-link>
                    <x-admin.action-link :href="config('app.client_url') . $post->getPermalinkUrl()" :external="true">
                        View in app
                    </x-admin.action-link>
                </div>
            </div>
        @empty
            <x-admin.empty-state-mobile icon="file-alt" message="No posts found" />
        @endforelse
    </div>

    <!-- Pagination -->
    <x-admin.pagination :paginator="$posts" />
</div>

@endsection
