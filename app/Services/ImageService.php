<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Image;
use App\Models\ImageSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

final class ImageService
{
    protected ImageManager $manager;

    public const WEBP_QUALITY = 92;

    public const USE_LOSSLESS_FOR_PNG = true;

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/bmp',
        'image/tiff',
    ];

    public const MAX_FILE_SIZE = 16 * 1024 * 1024;

    public function __construct(
        private readonly UrlValidationService $urlValidator,
    ) {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Upload and process an image.
     *
     * Simplified: Only generates 1 size (large) - IPX on frontend handles resizing.
     *
     * @param  string  $type  Image type: 'avatar', 'thumbnail', 'inline'
     * @param  int  $userId  User ID who is uploading
     * @param  string|null  $uploadableType  Owner model class (User, Post, Comment)
     * @param  int|null  $uploadableId  Owner model ID
     * @param  bool  $isNsfw  Whether the image contains NSFW content
     *
     * @return Image Created image model
     */
    public function uploadImage(
        UploadedFile $file,
        string $type,
        int $userId,
        ?string $uploadableType = null,
        ?int $uploadableId = null,
        bool $isNsfw = false,
    ): Image {
        $this->validateImage($file);

        // Get sizes for this image type from database settings
        $sizes = ImageSetting::getSizesForType($type);

        if (empty($sizes)) {
            throw new InvalidArgumentException("No size settings found for image type: {$type}");
        }

        // Read original image to get metadata
        $image = $this->manager->read($file->getRealPath());
        $originalWidth = $image->width();
        $originalHeight = $image->height();
        $originalMimeType = $file->getMimeType();
        $originalPath = $file->getRealPath();

        // Generate unique hash for this image
        $hash = hash('sha256', $userId . time() . Str::random(16));

        // Determine if we should use lossless compression
        $useLossless = $this->shouldUseLosslessCompression($originalMimeType);

        // Only generate the largest size - IPX on frontend handles resizing
        $maxWidth = max($sizes);
        $freshImage = $this->manager->read($originalPath);
        $blobData = $this->getResizedImageBlob($freshImage, $maxWidth, $useLossless);

        // Create Image model with single size stored in large_blob
        return Image::create([
            'hash' => $hash,
            'type' => $type,
            'uploadable_type' => $uploadableType,
            'uploadable_id' => $uploadableId,
            'small_blob' => null,
            'medium_blob' => null,
            'large_blob' => $blobData,
            'original_width' => $originalWidth,
            'original_height' => $originalHeight,
            'file_size' => strlen($blobData),
            'mime_type' => 'image/webp',
            'is_nsfw' => $isNsfw,
            'user_id' => $userId,
        ]);
    }

    /**
     * Upload avatar for a user.
     */
    public function uploadAvatar(UploadedFile $file, int $userId): Image
    {
        // Delete old avatar if exists
        $this->deleteUserImages($userId, 'avatar');

        return $this->uploadImage($file, 'avatar', $userId, 'App\\Models\\User', $userId);
    }

    /**
     * Upload thumbnail for a post.
     */
    public function uploadThumbnail(UploadedFile $file, int $postId, int $userId): Image
    {
        // Delete old thumbnail if exists
        $this->deletePostImages($postId, 'thumbnail');

        return $this->uploadImage($file, 'thumbnail', $userId, 'App\\Models\\Post', $postId);
    }

    /**
     * Upload thumbnail from external URL.
     */
    public function uploadThumbnailFromUrl(string $url, int $postId, int $userId): Image
    {
        // Delete old thumbnail if exists
        $this->deletePostImages($postId, 'thumbnail');

        return $this->uploadImageFromUrl($url, 'thumbnail', $userId, 'App\\Models\\Post', $postId);
    }

    /**
     * Upload inline image (for post/comment content).
     */
    public function uploadInlineImage(
        UploadedFile $file,
        int $userId,
        ?string $uploadableType = null,
        ?int $uploadableId = null,
        bool $isNsfw = false,
    ): Image {
        return $this->uploadImage($file, 'inline', $userId, $uploadableType, $uploadableId, $isNsfw);
    }

    /**
     * Upload icon for a sub (community).
     */
    public function uploadSubIcon(UploadedFile $file, int $subId, int $userId): Image
    {
        // Delete old sub icon if exists
        $this->deleteSubImages($subId, 'sub_icon');

        return $this->uploadImage($file, 'sub_icon', $userId, 'App\\Models\\Sub', $subId);
    }

    /**
     * Delete all images for a user of specific type.
     */
    public function deleteUserImages(int $userId, string $type): void
    {
        Image::where('uploadable_type', 'App\\Models\\User')
            ->where('uploadable_id', $userId)
            ->where('type', $type)
            ->each(fn (Image $image) => $image->delete());
    }

    /**
     * Delete all images for a post of specific type.
     */
    public function deletePostImages(int $postId, string $type): void
    {
        Image::where('uploadable_type', 'App\\Models\\Post')
            ->where('uploadable_id', $postId)
            ->where('type', $type)
            ->each(fn (Image $image) => $image->delete());
    }

    /**
     * Delete all images for a sub of specific type.
     */
    public function deleteSubImages(int $subId, string $type): void
    {
        Image::where('uploadable_type', 'App\\Models\\Sub')
            ->where('uploadable_id', $subId)
            ->where('type', $type)
            ->each(fn (Image $image) => $image->delete());
    }

    /**
     * Validate uploaded image.
     */
    protected function validateImage(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid image type. Allowed: JPG, PNG, GIF, WebP');
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('Image too large. Max size: 5MB');
        }
    }

    /**
     * Determine if we should use lossless compression for this image.
     * PNGs are typically screenshots or graphics with text/sharp edges.
     */
    protected function shouldUseLosslessCompression(string $mimeType): bool
    {
        // PNGs are usually screenshots, diagrams, or graphics with text
        // These benefit from lossless compression to preserve sharp edges
        if (self::USE_LOSSLESS_FOR_PNG && $mimeType === 'image/png') {
            return true;
        }

        return false;
    }

    /**
     * Get resized image as binary blob in WebP format.
     * Uses scaleDown() to prevent upscaling small images (which degrades quality).
     *
     * @param  \Intervention\Image\Interfaces\ImageInterface  $image  The image to resize
     * @param  int  $width  Target width
     * @param  bool  $useLossless  Whether to use lossless compression
     */
    protected function getResizedImageBlob($image, int $width, bool $useLossless = false): string
    {
        // scaleDown() only scales DOWN, never UP
        // If image is already smaller than $width, keeps original size
        $processed = $image->scaleDown(width: $width);

        if ($useLossless) {
            // Lossless WebP - perfect for screenshots, text, UI elements
            // Preserves sharp edges and text clarity
            // Quality 100 triggers lossless mode in GD driver (IMG_WEBP_LOSSLESS)
            return $processed->toWebp(quality: 100)->toString();
        }

        // Lossy WebP with high quality for photos
        return $processed->toWebp(quality: self::WEBP_QUALITY)->toString();
    }

    /**
     * Detect MIME type from image bytes using magic numbers.
     */
    private function detectMimeTypeFromBytes(string $data): ?string
    {
        if (strlen($data) < 12) {
            return null;
        }

        $bytes = substr($data, 0, 12);

        // JPEG: FF D8 FF
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        // GIF: GIF87a or GIF89a
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }

        // WebP: RIFF....WEBP
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        // AVIF: ....ftypavif or ....ftypavis
        if (substr($bytes, 4, 4) === 'ftyp') {
            $brand = substr($data, 8, 4);
            if (in_array($brand, ['avif', 'avis', 'mif1', 'miaf'], true)) {
                return 'image/avif';
            }
        }

        // BMP: BM
        if (str_starts_with($bytes, 'BM')) {
            return 'image/bmp';
        }

        // TIFF: II or MM
        if (str_starts_with($bytes, "II\x2a\x00") || str_starts_with($bytes, "MM\x00\x2a")) {
            return 'image/tiff';
        }

        return null;
    }

    /**
     * Upload and process an image from external URL.
     */
    public function uploadImageFromUrl(
        string $url,
        string $type,
        int $userId,
        ?string $uploadableType = null,
        ?int $uploadableId = null,
        bool $isNsfw = false,
    ): Image {
        $this->urlValidator->validate($url);

        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download image from URL');
        }

        $imageData = $response->body();

        // Detect mime type from image bytes (more reliable than Content-Type header)
        $mimeType = $this->detectMimeTypeFromBytes($imageData);
        if ($mimeType === null) {
            // Fallback to Content-Type header
            $contentType = $response->header('Content-Type');
            $mimeType = explode(';', $contentType)[0] ?? '';
        }

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException('Invalid image type. Allowed: JPG, PNG, GIF, WebP, AVIF, BMP, TIFF');
        }

        // Validate size
        if (strlen($imageData) > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('Image too large. Max size: 16MB');
        }

        // Get sizes for this image type
        $sizes = ImageSetting::getSizesForType($type);
        if (empty($sizes)) {
            throw new InvalidArgumentException("No size settings found for image type: {$type}");
        }

        // Process image
        $image = $this->manager->read($imageData);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $hash = hash('sha256', $userId . time() . Str::random(16));
        $useLossless = $this->shouldUseLosslessCompression($mimeType);

        $maxWidth = max($sizes);
        $blobData = $this->getResizedImageBlob($image, $maxWidth, $useLossless);

        return Image::create([
            'hash' => $hash,
            'type' => $type,
            'uploadable_type' => $uploadableType,
            'uploadable_id' => $uploadableId,
            'small_blob' => null,
            'medium_blob' => null,
            'large_blob' => $blobData,
            'original_width' => $originalWidth,
            'original_height' => $originalHeight,
            'file_size' => strlen($blobData),
            'mime_type' => 'image/webp',
            'is_nsfw' => $isNsfw,
            'user_id' => $userId,
        ]);
    }
}
