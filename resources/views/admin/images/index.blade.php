@extends('admin.layout')

@section('title', 'Image Management')
@section('page-title', 'Image Management')

@section('content')
<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <!-- Search by hash -->
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search by Hash</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    placeholder="Enter image hash...">
            </div>

            <!-- NSFW Filter -->
            <div class="w-40">
                <label for="nsfw" class="block text-sm font-medium text-gray-700 mb-1">NSFW Status</label>
                <select name="nsfw" id="nsfw" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All Images</option>
                    <option value="1" {{ request('nsfw') === '1' ? 'selected' : '' }}>NSFW Only</option>
                    <option value="0" {{ request('nsfw') === '0' ? 'selected' : '' }}>Safe Only</option>
                </select>
            </div>

            <!-- Type Filter -->
            <div class="w-40">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Image Type</label>
                <select name="type" id="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Submit -->
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                    <i class="fas fa-search mr-1"></i> Filter
                </button>
                <a href="{{ route('admin.images.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors text-sm">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Select All
                </label>
                <span id="selectedCount" class="text-sm text-gray-500">0 selected</span>
            </div>
            <div class="flex gap-2">
                <button onclick="bulkMarkNsfw(true)" class="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700 transition-colors disabled:opacity-50" id="bulkNsfwBtn" disabled>
                    <i class="fas fa-exclamation-triangle mr-1"></i> Mark as NSFW
                </button>
                <button onclick="bulkMarkNsfw(false)" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors disabled:opacity-50" id="bulkSafeBtn" disabled>
                    <i class="fas fa-check-circle mr-1"></i> Mark as Safe
                </button>
            </div>
        </div>
    </div>

    <!-- Image Grid - Smaller thumbnails -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 gap-2">
            @forelse($images as $image)
                <div class="relative group" data-image-id="{{ $image->id }}">
                    <!-- Checkbox -->
                    <div class="absolute top-1 left-1 z-20">
                        <input type="checkbox" class="image-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 shadow"
                            value="{{ $image->id }}"
                            onchange="updateSelectedCount()">
                    </div>

                    <!-- NSFW Badge -->
                    @if($image->is_nsfw)
                        <div class="absolute top-1 right-1 z-20 nsfw-badge-container">
                            <span class="px-1 py-0.5 bg-red-600 text-white text-[10px] font-bold rounded">+18</span>
                        </div>
                    @endif

                    <!-- Image Container - Clickable for lightbox -->
                    <div class="aspect-square rounded overflow-hidden bg-gray-100 border cursor-pointer {{ $image->is_nsfw ? 'border-red-300' : 'border-gray-200' }}"
                         onclick="openLightbox('{{ $image->getUrl('large') }}', {{ $image->id }}, {{ $image->is_nsfw ? 'true' : 'false' }})">
                        <img src="{{ $image->getUrl('medium') }}"
                            alt="Image {{ $image->hash }}"
                            class="w-full h-full object-cover {{ $image->is_nsfw ? 'blur-sm group-hover:blur-none transition-all' : '' }}"
                            loading="lazy">
                    </div>

                    <!-- Action bar - Always visible -->
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-1 rounded-b">
                        <div class="flex justify-between items-center">
                            <!-- Left: Content type icon -->
                            <div class="flex gap-1">
                                @if($image->uploadable_type === 'App\\Models\\Post' && $image->uploadable_id)
                                    <a href="{{ route('admin.posts.view', $image->uploadable_id) }}"
                                       onclick="event.stopPropagation()"
                                       class="p-1 rounded text-blue-300 hover:text-blue-100 hover:bg-white/20 transition-colors"
                                       title="View Post">
                                        <i class="fas fa-file-alt text-xs"></i>
                                    </a>
                                @elseif($image->uploadable_type === 'App\\Models\\User' && $image->uploadable_id)
                                    <a href="{{ route('admin.users.show', $image->uploadable_id) }}"
                                       onclick="event.stopPropagation()"
                                       class="p-1 rounded text-purple-300 hover:text-purple-100 hover:bg-white/20 transition-colors"
                                       title="View User (Avatar)">
                                        <i class="fas fa-user-circle text-xs"></i>
                                    </a>
                                @elseif($image->type === 'inline')
                                    <span class="p-1 text-yellow-300" title="Inline image (comment/agora)">
                                        <i class="fas fa-comment-dots text-xs"></i>
                                    </span>
                                @endif

                                <!-- User who uploaded -->
                                @if($image->user)
                                    <a href="{{ route('admin.users.show', $image->user_id) }}"
                                       onclick="event.stopPropagation()"
                                       class="p-1 rounded text-gray-300 hover:text-white hover:bg-white/20 transition-colors"
                                       title="Uploaded by: {{ $image->user->username }}">
                                        <i class="fas fa-user text-xs"></i>
                                    </a>
                                @endif
                            </div>

                            <!-- Right: NSFW toggle -->
                            <button onclick="event.stopPropagation(); toggleNsfw({{ $image->id }}, this)"
                                class="p-1 rounded text-white hover:bg-white/20 transition-colors nsfw-toggle-btn"
                                data-is-nsfw="{{ $image->is_nsfw ? '1' : '0' }}"
                                title="{{ $image->is_nsfw ? 'Mark Safe' : 'Mark NSFW' }}">
                                <i class="fas {{ $image->is_nsfw ? 'fa-check-circle text-green-400' : 'fa-exclamation-triangle text-red-400' }} text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-gray-500">
                    <i class="fas fa-images text-4xl mb-4 opacity-50"></i>
                    <p>No images found</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($images->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $images->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Lightbox Modal -->
<div id="lightbox" class="fixed inset-0 bg-black/90 z-50 hidden items-center justify-center p-4" onclick="closeLightbox()">
    <div class="relative max-w-5xl max-h-full">
        <!-- Close button -->
        <button onclick="closeLightbox()" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-2xl">
            <i class="fas fa-times"></i>
        </button>

        <!-- Image -->
        <img id="lightboxImage" src="" alt="Full size image" class="max-w-full max-h-[85vh] object-contain rounded-lg" onclick="event.stopPropagation()">

        <!-- Actions bar -->
        <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-black/70 rounded-lg px-4 py-2 flex gap-4 items-center" onclick="event.stopPropagation()">
            <!-- NSFW Toggle -->
            <button id="lightboxNsfwBtn" onclick="toggleNsfwFromLightbox()" class="flex items-center gap-2 text-white hover:text-gray-300 transition-colors">
                <i id="lightboxNsfwIcon" class="fas fa-exclamation-triangle"></i>
                <span id="lightboxNsfwText">Mark NSFW</span>
            </button>

            <!-- Link to content (will be populated by JS) -->
            <a id="lightboxContentLink" href="#" class="hidden flex items-center gap-2 text-white hover:text-gray-300 transition-colors">
                <i id="lightboxContentIcon" class="fas fa-external-link-alt"></i>
                <span id="lightboxContentText">View Content</span>
            </a>

            <!-- Open in new tab -->
            <a id="lightboxOpenNew" href="" target="_blank" class="flex items-center gap-2 text-white hover:text-gray-300 transition-colors">
                <i class="fas fa-external-link-alt"></i>
                <span>Open Original</span>
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let currentLightboxImageId = null;
    let currentLightboxIsNsfw = false;

    // Select All functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.image-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateSelectedCount();
    });

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.image-checkbox:checked');
        const count = checked.length;
        document.getElementById('selectedCount').textContent = count + ' selected';
        document.getElementById('bulkNsfwBtn').disabled = count === 0;
        document.getElementById('bulkSafeBtn').disabled = count === 0;
    }

    // Lightbox functions
    function openLightbox(imageUrl, imageId, isNsfw) {
        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxOpenNew = document.getElementById('lightboxOpenNew');

        currentLightboxImageId = imageId;
        currentLightboxIsNsfw = isNsfw;

        lightboxImage.src = imageUrl;
        lightboxOpenNew.href = imageUrl;

        updateLightboxNsfwButton(isNsfw);

        // Get content link from the grid item
        const gridItem = document.querySelector(`[data-image-id="${imageId}"]`);
        const contentLink = gridItem.querySelector('a[href*="admin/posts"], a[href*="admin/users"]');
        const lightboxContentLink = document.getElementById('lightboxContentLink');

        if (contentLink) {
            lightboxContentLink.href = contentLink.href;
            lightboxContentLink.classList.remove('hidden');
            lightboxContentLink.classList.add('flex');

            if (contentLink.href.includes('/admin/posts/')) {
                document.getElementById('lightboxContentIcon').className = 'fas fa-file-alt';
                document.getElementById('lightboxContentText').textContent = 'View Post';
            } else if (contentLink.href.includes('/admin/users/')) {
                document.getElementById('lightboxContentIcon').className = 'fas fa-user';
                document.getElementById('lightboxContentText').textContent = 'View User';
            }
        } else {
            lightboxContentLink.classList.add('hidden');
            lightboxContentLink.classList.remove('flex');
        }

        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const lightbox = document.getElementById('lightbox');
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
        document.body.style.overflow = '';
        currentLightboxImageId = null;
    }

    function updateLightboxNsfwButton(isNsfw) {
        const icon = document.getElementById('lightboxNsfwIcon');
        const text = document.getElementById('lightboxNsfwText');

        if (isNsfw) {
            icon.className = 'fas fa-check-circle text-green-400';
            text.textContent = 'Mark Safe';
        } else {
            icon.className = 'fas fa-exclamation-triangle text-red-400';
            text.textContent = 'Mark NSFW';
        }
    }

    async function toggleNsfwFromLightbox() {
        if (!currentLightboxImageId) return;

        const gridItem = document.querySelector(`[data-image-id="${currentLightboxImageId}"]`);
        const button = gridItem.querySelector('.nsfw-toggle-btn');

        await toggleNsfw(currentLightboxImageId, button);

        // Update lightbox button state
        currentLightboxIsNsfw = !currentLightboxIsNsfw;
        updateLightboxNsfwButton(currentLightboxIsNsfw);
    }

    // Close lightbox with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });

    // Toggle single image NSFW
    async function toggleNsfw(imageId, button) {
        try {
            const response = await fetch(`/admin/images/${imageId}/toggle-nsfw`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                const isNsfw = data.is_nsfw;
                button.dataset.isNsfw = isNsfw ? '1' : '0';

                // Update button icon
                const icon = button.querySelector('i');
                if (isNsfw) {
                    icon.className = 'fas fa-check-circle text-green-400 text-xs';
                    button.title = 'Mark Safe';
                } else {
                    icon.className = 'fas fa-exclamation-triangle text-red-400 text-xs';
                    button.title = 'Mark NSFW';
                }

                // Update container and image
                const container = button.closest('[data-image-id]');
                const img = container.querySelector('img');
                const badgeContainer = container.querySelector('.nsfw-badge-container');
                const border = container.querySelector('.aspect-square');

                if (isNsfw) {
                    img.classList.add('blur-sm', 'group-hover:blur-none');
                    border.classList.add('border-red-300');
                    border.classList.remove('border-gray-200');
                    // Add badge if not exists
                    if (!badgeContainer) {
                        const badgeHtml = '<div class="absolute top-1 right-1 z-20 nsfw-badge-container"><span class="px-1 py-0.5 bg-red-600 text-white text-[10px] font-bold rounded">+18</span></div>';
                        container.insertAdjacentHTML('afterbegin', badgeHtml);
                    }
                } else {
                    img.classList.remove('blur-sm', 'group-hover:blur-none');
                    border.classList.remove('border-red-300');
                    border.classList.add('border-gray-200');
                    // Remove badge
                    if (badgeContainer) badgeContainer.remove();
                }
            }
        } catch (error) {
            console.error('Error toggling NSFW:', error);
            alert('Error updating image status');
        }
    }

    // Bulk mark NSFW
    function bulkMarkNsfw(isNsfw) {
        const checked = document.querySelectorAll('.image-checkbox:checked');
        const imageIds = Array.from(checked).map(cb => parseInt(cb.value));

        if (imageIds.length === 0) return;

        const action = isNsfw ? 'mark as NSFW' : 'mark as safe';
        showConfirmModal(`Are you sure you want to ${action} ${imageIds.length} images?`, {
            title: isNsfw ? 'Mark as NSFW' : 'Mark as Safe',
            type: isNsfw ? 'danger' : 'warning',
            confirmText: isNsfw ? 'Mark NSFW' : 'Mark Safe',
            onConfirm: async function() {
                try {
                    const response = await fetch('/admin/images/bulk-nsfw', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            image_ids: imageIds,
                            is_nsfw: isNsfw
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Reload page to show updated state
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Error bulk updating:', error);
                    alert('Error updating images');
                }
            }
        });
    }
</script>
@endpush
@endsection
