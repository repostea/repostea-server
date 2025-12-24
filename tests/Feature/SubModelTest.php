<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 0,
        'posts_count' => 0,
    ]);
});

test('sub has relationship with creator', function (): void {
    expect($this->sub->creator)->toBeInstanceOf(User::class);
    expect($this->sub->creator->id)->toBe($this->user->id);
});

test('sub can have multiple subscribers', function (): void {
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $this->sub->subscribers()->attach($user->id);
    }

    expect($this->sub->subscribers()->count())->toBe(3);
});

test('sub has hasMany relationship with posts', function (): void {
    Post::factory()->count(5)->create([
        'sub_id' => $this->sub->id,
    ]);

    expect($this->sub->posts)->toHaveCount(5);
    expect($this->sub->posts->first())->toBeInstanceOf(Post::class);
});

test('isSubscribedBy checks if user is subscribed', function (): void {
    $newUser = User::factory()->create();

    expect($this->sub->isSubscribedBy($newUser))->toBeFalse();

    $this->sub->subscribers()->attach($newUser->id);

    expect($this->sub->isSubscribedBy($newUser))->toBeTrue();
});

test('sub fillable fields are correctly defined', function (): void {
    $fillable = [
        'name',
        'display_name',
        'description',
        'rules',
        'icon',
        'color',
        'members_count',
        'posts_count',
        'is_private',
        'is_adult',
        'is_featured',
        'require_approval',
        'hide_owner',
        'hide_moderators',
        'allowed_content_types',
        'visibility',
        'created_by',
        'orphaned_at',
    ];

    expect($this->sub->getFillable())->toBe($fillable);
});

test('sub boolean fields are cast correctly', function (): void {
    $sub = Sub::create([
        'name' => 'private-sub',
        'display_name' => 'Private Sub',
        'created_by' => $this->user->id,
        'is_private' => '1',
        'is_adult' => '0',
        'icon' => '🔒',
        'color' => '#FF0000',
    ]);

    expect($sub->is_private)->toBeTrue();
    expect($sub->is_adult)->toBeFalse();
});

test('sub numeric fields are correctly cast', function (): void {
    $sub = Sub::create([
        'name' => 'numeric-sub',
        'display_name' => 'Numeric Sub',
        'created_by' => $this->user->id,
        'members_count' => '10',
        'posts_count' => '25',
        'icon' => '📊',
        'color' => '#00FF00',
    ]);

    expect($sub->members_count)->toBe(10);
    expect($sub->posts_count)->toBe(25);
    expect($sub->members_count)->toBeInt();
    expect($sub->posts_count)->toBeInt();
});

test('sub uses soft deletes', function (): void {
    $sub = Sub::create([
        'name' => 'deletable-sub',
        'display_name' => 'Deletable Sub',
        'created_by' => $this->user->id,
        'icon' => '🗑️',
        'color' => '#999999',
    ]);

    $subId = $sub->id;
    $sub->delete();

    expect(Sub::find($subId))->toBeNull();
    expect(Sub::withTrashed()->find($subId))->not->toBeNull();
    expect(Sub::withTrashed()->find($subId)->deleted_at)->not->toBeNull();
});
