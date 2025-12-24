<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const JSON_INVALID_UTF8_SUBSTITUTE;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Post;
use App\Models\User;
use App\Services\ImageService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class ImageController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Upload user avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif,webp|max:16384', // 16MB
        ]);

        try {
            $user = Auth::user();
            $image = $this->imageService->uploadAvatar($request->file('avatar'), $user->id);

            // Update user model with new avatar image reference
            $user->avatar_image_id = $image->id;
            $user->avatar = $image->getUrl('medium'); // For backward compatibility
            $user->avatar_url = $image->getUrl('small'); // For backward compatibility
            $user->save();

            return response()->json([
                'message' => __('messages.media.avatar_uploaded'),
                'image' => [
                    'id' => $image->id,
                    'urls' => $image->getUrls(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => ErrorHelper::getSafeMessage($e, __('messages.media.avatar_validation_error')),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.media.avatar_upload_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Delete user avatar.
     */
    public function deleteAvatar(): JsonResponse
    {
        $user = Auth::user();

        if ($user->avatar_image_id) {
            $image = $user->avatarImage;
            if ($image) {
                $image->delete();
            }
        }

        $user->avatar_image_id = null;
        $user->avatar = null;
        $user->avatar_url = null;
        $user->save();

        return response()->json([
            'message' => __('messages.media.avatar_deleted'),
        ]);
    }

    /**
     * Upload post thumbnail.
     */
    public function uploadThumbnail(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'thumbnail' => 'required|image|mimes:jpeg,png,gif,webp|max:16384', // 16MB
        ]);

        $post = Post::findOrFail($postId);
        Gate::authorize('update', $post);

        try {
            $image = $this->imageService->uploadThumbnail(
                $request->file('thumbnail'),
                $postId,
                Auth::id(),
            );

            // Update post model with new thumbnail image reference
            $post->thumbnail_image_id = $image->id;
            $post->thumbnail_url = $image->getUrl('medium'); // For backward compatibility
            $post->save();

            return response()->json([
                'message' => __('messages.media.thumbnail_uploaded'),
                'image' => [
                    'id' => $image->id,
                    'urls' => $image->getUrls(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => ErrorHelper::getSafeMessage($e, __('messages.media.thumbnail_validation_error')),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.media.thumbnail_upload_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Upload post thumbnail from external URL.
     */
    public function uploadThumbnailFromUrl(Request $request, int $postId): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $post = Post::findOrFail($postId);
        Gate::authorize('update', $post);

        try {
            $image = $this->imageService->uploadThumbnailFromUrl(
                $request->input('url'),
                $postId,
                Auth::id(),
            );

            $post->thumbnail_image_id = $image->id;
            $post->thumbnail_url = $image->getUrl('medium');
            $post->save();

            return response()->json([
                'message' => __('messages.media.thumbnail_uploaded'),
                'image' => [
                    'id' => $image->id,
                    'urls' => $image->getUrls(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => ErrorHelper::getSafeMessage($e, __('messages.media.thumbnail_validation_error')),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.media.thumbnail_download_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Delete post thumbnail.
     */
    public function deleteThumbnail(int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        Gate::authorize('update', $post);

        if ($post->thumbnail_image_id) {
            $image = $post->thumbnailImage;
            if ($image) {
                $image->delete();
            }
        }

        $post->thumbnail_image_id = null;
        $post->thumbnail_url = null;
        $post->save();

        return response()->json([
            'message' => __('messages.media.thumbnail_deleted'),
        ]);
    }

    /**
     * Upload inline image (for post/comment content).
     */
    public function uploadInlineImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:16384', // 16MB
            'uploadable_type' => 'nullable|string|in:post,comment',
            'uploadable_id' => 'nullable|integer',
            'is_nsfw' => 'nullable|boolean',
        ]);

        try {
            $uploadableType = $request->input('uploadable_type')
                ? 'App\\Models\\' . ucfirst($request->input('uploadable_type'))
                : null;
            $uploadableId = $request->input('uploadable_id');
            $isNsfw = $request->boolean('is_nsfw', false);

            $image = $this->imageService->uploadInlineImage(
                $request->file('image'),
                Auth::id(),
                $uploadableType,
                $uploadableId,
                $isNsfw,
            );

            return response()->json([
                'message' => __('messages.media.image_uploaded'),
                'image' => [
                    'id' => $image->id,
                    'urls' => $image->getUrls(),
                ],
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => ErrorHelper::getSafeMessage($e, __('messages.media.image_validation_error')),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.media.image_upload_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Serve image from database by hash.
     *
     * Simplified: Only stores 1 size (large) - IPX on frontend handles resizing.
     * Size parameter kept for backward compatibility with existing URLs.
     */
    public function serve(string $hash, string $size = 'medium'): Response
    {
        // Size parameter kept for backward compatibility but ignored
        // (all sizes serve the same image now, IPX handles resizing)

        // Check filesystem cache first
        $cachePath = "cache/images/{$hash}.webp";

        if (Storage::exists($cachePath)) {
            $imageData = Storage::get($cachePath);

            return response($imageData, 200)
                ->header('Content-Type', 'image/webp')
                ->header('Cache-Control', 'public, max-age=31536000'); // 1 year
        }

        // Not in cache, fetch from database
        $image = Image::where('hash', $hash)->first();

        if (! $image) {
            abort(404, 'Image not found');
        }

        $blobData = $image->getBlob();

        if (! $blobData) {
            abort(404, 'Image not found');
        }

        // Save to filesystem cache for future requests
        Storage::put($cachePath, $blobData);

        return response($blobData, 200)
            ->header('Content-Type', 'image/webp')
            ->header('Cache-Control', 'public, max-age=31536000'); // 1 year
    }
}
