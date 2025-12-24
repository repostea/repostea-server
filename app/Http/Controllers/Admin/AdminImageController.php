<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

final class AdminImageController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of images.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin');

        // Don't eager load user - it's in a different database connection
        $query = Image::orderBy('created_at', 'desc');

        // Filter by NSFW status
        if ($request->has('nsfw') && $request->get('nsfw') !== '') {
            $query->where('is_nsfw', $request->get('nsfw') === '1');
        }

        // Filter by type
        if ($request->has('type') && ! empty($request->get('type'))) {
            $query->where('type', $request->get('type'));
        }

        // Filter by user
        if ($request->has('user_id') && ! empty($request->get('user_id'))) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Search by hash
        if ($request->has('search') && ! empty($request->get('search'))) {
            $search = $request->get('search');
            $query->where('hash', 'like', "%{$search}%");
        }

        $images = $query->paginate(24)->withQueryString();

        // Load users manually (they're in a different database)
        $userIds = $images->pluck('user_id')->filter()->unique()->toArray();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Attach users to images
        foreach ($images as $image) {
            $image->setRelation('user', $users->get($image->user_id));
        }

        // Get distinct types for filter dropdown
        $types = Image::distinct()->pluck('type')->filter()->sort()->values();

        return view('admin.images.index', compact('images', 'types'));
    }

    /**
     * Toggle NSFW status for an image.
     */
    public function toggleNsfw(Request $request, Image $image)
    {
        $this->authorize('access-admin');

        $image->update([
            'is_nsfw' => ! $image->is_nsfw,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_nsfw' => $image->is_nsfw,
                'message' => $image->is_nsfw
                    ? 'Image marked as NSFW'
                    : 'NSFW mark removed from image',
            ]);
        }

        return redirect()->back()->with('success',
            $image->is_nsfw
                ? 'Image marked as NSFW'
                : 'NSFW mark removed from image',
        );
    }

    /**
     * Bulk update NSFW status for multiple images.
     */
    public function bulkNsfw(Request $request)
    {
        $this->authorize('access-admin');

        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'integer|exists:media.images,id',
            'is_nsfw' => 'required|boolean',
        ]);

        $count = Image::whereIn('id', $validated['image_ids'])
            ->update(['is_nsfw' => $validated['is_nsfw']]);

        $action = $validated['is_nsfw'] ? 'marked as NSFW' : 'unmarked as NSFW';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} images {$action}",
            ]);
        }

        return redirect()->back()->with('success', "{$count} images {$action}");
    }
}
