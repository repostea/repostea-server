<?php

declare(strict_types=1);

use App\Models\Image;
use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = app(ImageService::class);
    $this->user = User::factory()->create();

    // Clear cache to ensure settings are fresh (migrations seed the data)
    Cache::flush();
});

test('uploadImage creates image from uploaded file', function (): void {
    $file = UploadedFile::fake()->image('test.jpg', 200, 200);

    $image = $this->service->uploadImage(
        $file,
        'avatar',
        $this->user->id,
        'App\\Models\\User',
        $this->user->id,
    );

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->type)->toBe('avatar');
    expect($image->user_id)->toBe($this->user->id);
    expect($image->uploadable_type)->toBe('App\\Models\\User');
    expect($image->uploadable_id)->toBe($this->user->id);
    expect($image->mime_type)->toBe('image/webp');
    expect($image->large_blob)->not->toBeNull();
    expect($image->original_width)->toBe(200);
    expect($image->original_height)->toBe(200);
});

test('uploadImage validates mime type', function (): void {
    $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

    $this->service->uploadImage($file, 'avatar', $this->user->id);
})->throws(InvalidArgumentException::class, 'Invalid image type');

test('uploadImage validates file size', function (): void {
    // Create a file larger than 16MB
    $file = UploadedFile::fake()->create('large.jpg', 17 * 1024, 'image/jpeg');

    $this->service->uploadImage($file, 'avatar', $this->user->id);
})->throws(InvalidArgumentException::class, 'Image too large');

test('uploadImage throws for invalid image type', function (): void {
    $file = UploadedFile::fake()->image('test.jpg', 100, 100);

    $this->service->uploadImage($file, 'invalid_type', $this->user->id);
})->throws(InvalidArgumentException::class, 'Invalid image type');

test('uploadAvatar creates avatar and deletes old one', function (): void {
    $file1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);
    $file2 = UploadedFile::fake()->image('avatar2.jpg', 200, 200);

    $avatar1 = $this->service->uploadAvatar($file1, $this->user->id);
    expect(Image::where('id', $avatar1->id)->exists())->toBeTrue();

    $avatar2 = $this->service->uploadAvatar($file2, $this->user->id);

    // Old avatar should be deleted
    expect(Image::where('id', $avatar1->id)->exists())->toBeFalse();
    expect(Image::where('id', $avatar2->id)->exists())->toBeTrue();
});

test('uploadThumbnail creates thumbnail for post', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);
    $file = UploadedFile::fake()->image('thumb.jpg', 640, 480);

    $thumbnail = $this->service->uploadThumbnail($file, $post->id, $this->user->id);

    expect($thumbnail->type)->toBe('thumbnail');
    expect($thumbnail->uploadable_type)->toBe('App\\Models\\Post');
    expect($thumbnail->uploadable_id)->toBe($post->id);
});

test('uploadInlineImage creates inline image', function (): void {
    $file = UploadedFile::fake()->image('inline.png', 800, 600);

    $image = $this->service->uploadInlineImage($file, $this->user->id);

    expect($image->type)->toBe('inline');
    expect($image->user_id)->toBe($this->user->id);
});

test('uploadInlineImage supports nsfw flag', function (): void {
    $file = UploadedFile::fake()->image('nsfw.jpg', 400, 400);

    $image = $this->service->uploadInlineImage($file, $this->user->id, null, null, true);

    expect($image->is_nsfw)->toBeTrue();
});

test('uploadSubIcon creates sub icon', function (): void {
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'members_count' => 1,
        'icon' => '',
        'color' => '#6366F1',
    ]);
    $file = UploadedFile::fake()->image('icon.png', 200, 200);

    $icon = $this->service->uploadSubIcon($file, $sub->id, $this->user->id);

    expect($icon->type)->toBe('sub_icon');
    expect($icon->uploadable_type)->toBe('App\\Models\\Sub');
    expect($icon->uploadable_id)->toBe($sub->id);
});

test('deleteUserImages removes all images of type for user', function (): void {
    $file1 = UploadedFile::fake()->image('avatar1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('inline1.jpg', 100, 100);

    $avatar = $this->service->uploadAvatar($file1, $this->user->id);
    $inline = $this->service->uploadInlineImage($file2, $this->user->id, 'App\\Models\\User', $this->user->id);

    $this->service->deleteUserImages($this->user->id, 'avatar');

    expect(Image::where('id', $avatar->id)->exists())->toBeFalse();
    expect(Image::where('id', $inline->id)->exists())->toBeTrue();
});

test('deletePostImages removes all images of type for post', function (): void {
    $post = Post::factory()->create(['user_id' => $this->user->id]);
    $file = UploadedFile::fake()->image('thumb.jpg', 640, 480);

    $thumbnail = $this->service->uploadThumbnail($file, $post->id, $this->user->id);

    $this->service->deletePostImages($post->id, 'thumbnail');

    expect(Image::where('id', $thumbnail->id)->exists())->toBeFalse();
});

test('image has correct urls', function (): void {
    $file = UploadedFile::fake()->image('test.jpg', 200, 200);
    $image = $this->service->uploadImage($file, 'avatar', $this->user->id);

    $urls = $image->getUrls();

    expect($urls)->toHaveKeys(['url', 'is_nsfw']);
    expect($urls['url'])->toContain($image->hash);
    expect($urls['is_nsfw'])->toBeFalse();
});

test('image getBlob returns data', function (): void {
    $file = UploadedFile::fake()->image('test.jpg', 200, 200);
    $image = $this->service->uploadImage($file, 'avatar', $this->user->id);

    $blob = $image->getBlob();

    expect($blob)->not->toBeNull();
    expect(strlen($blob))->toBeGreaterThan(0);
});

test('uploadImageFromUrl downloads and processes image', function (): void {
    // Create a valid small PNG (1x1 red pixel)
    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');

    Http::fake([
        'example.com/*' => Http::response($pngData, 200, ['Content-Type' => 'image/png']),
    ]);

    $image = $this->service->uploadImageFromUrl(
        'https://example.com/image.png',
        'thumbnail',
        $this->user->id,
        'App\\Models\\Post',
        1,
    );

    expect($image)->toBeInstanceOf(Image::class);
    expect($image->type)->toBe('thumbnail');
    expect($image->mime_type)->toBe('image/webp');
});

test('uploadImageFromUrl validates url scheme', function (): void {
    $this->service->uploadImageFromUrl(
        'http://example.com/image.jpg',
        'thumbnail',
        $this->user->id,
    );
})->throws(InvalidArgumentException::class, 'Only HTTPS URLs allowed');

test('uploadImageFromUrl handles download failure', function (): void {
    Http::fake([
        '*' => Http::response('Not Found', 404),
    ]);

    $this->service->uploadImageFromUrl(
        'https://example.com/notfound.jpg',
        'thumbnail',
        $this->user->id,
    );
})->throws(RuntimeException::class, 'Failed to download image');

test('allowed mime types constant is defined', function (): void {
    expect(ImageService::ALLOWED_MIME_TYPES)->toContain('image/jpeg');
    expect(ImageService::ALLOWED_MIME_TYPES)->toContain('image/png');
    expect(ImageService::ALLOWED_MIME_TYPES)->toContain('image/gif');
    expect(ImageService::ALLOWED_MIME_TYPES)->toContain('image/webp');
});

test('max file size constant is 16MB', function (): void {
    expect(ImageService::MAX_FILE_SIZE)->toBe(16 * 1024 * 1024);
});

test('webp quality constant is defined', function (): void {
    expect(ImageService::WEBP_QUALITY)->toBe(92);
});
