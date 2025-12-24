<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'karma_points' => 5000,
        'highest_level_id' => 4,
        'created_at' => now()->subDays(40),
    ]);
});

test('index returns paginated list of subs', function (): void {
    for ($i = 0; $i < 15; $i++) {
        Sub::create([
            'name' => "sub-{$i}",
            'display_name' => "Sub {$i}",
            'created_by' => $this->user->id,
            'icon' => '💻',
            'color' => '#3B82F6',
        ]);
    }

    $response = getJson('/api/v1/subs?per_page=10');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'name', 'display_name', 'icon', 'color', 'members_count', 'posts_count'],
        ],
        'meta' => ['current_page', 'last_page', 'total', 'per_page'],
    ]);
    expect(count($response->json('data')))->toBe(10);
});

test('index allows searching subs by name', function (): void {
    Sub::create([
        'name' => 'programming',
        'display_name' => 'Programming',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sub::create([
        'name' => 'gaming',
        'display_name' => 'Gaming',
        'created_by' => $this->user->id,
        'icon' => '🎮',
        'color' => '#EC4899',
    ]);

    $response = getJson('/api/v1/subs?search=programming');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'programming');
});

test('show returns sub details by name', function (): void {
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'description' => 'A test subcommunity',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response = getJson('/api/v1/subs/test-sub');

    $response->assertStatus(200);
    $response->assertJsonPath('data.name', 'test-sub');
    $response->assertJsonPath('data.display_name', 'Test Sub');
    $response->assertJsonPath('data.description', 'A test subcommunity');
});

test('show returns sub details by ID', function (): void {
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('data.id', $sub->id);
});

test('show returns 404 if sub does not exist', function (): void {
    $response = getJson('/api/v1/subs/non-existent-sub');

    $response->assertStatus(404);
    $response->assertJsonPath('error', 'Sub not found');
});

test('show includes is_member for authenticated users', function (): void {
    $sub = Sub::create([
        'name' => 'member-test',
        'display_name' => 'Member Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($this->user);
    $sub->subscribers()->attach($this->user->id);

    $response = getJson('/api/v1/subs/member-test');

    $response->assertStatus(200);
    $response->assertJsonPath('data.is_member', true);
});

test('show includes is_moderator for sub creator', function (): void {
    $sub = Sub::create([
        'name' => 'mod-test',
        'display_name' => 'Mod Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($this->user);

    $response = getJson('/api/v1/subs/mod-test');

    $response->assertStatus(200);
    $response->assertJsonPath('data.is_moderator', true);
});

test('posts endpoint returns sub posts', function (): void {
    $sub = Sub::create([
        'name' => 'posts-test',
        'display_name' => 'Posts Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Post::factory()->count(5)->create([
        'sub_id' => $sub->id,
        'user_id' => $this->user->id,
    ]);

    $response = getJson('/api/v1/subs/posts-test/posts');

    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
});

test('posts endpoint supports sorting by new', function (): void {
    $sub = Sub::create([
        'name' => 'sort-test',
        'display_name' => 'Sort Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    // Create old post
    $oldPost = new Post([
        'title' => 'Old Post',
        'content' => 'Old content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
    ]);
    $oldPost->created_at = now()->subDays(5);
    $oldPost->save();

    // Create new post
    $newPost = new Post([
        'title' => 'New Post',
        'content' => 'New content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
    ]);
    $newPost->created_at = now();
    $newPost->save();

    $response = getJson('/api/v1/subs/sort-test/posts?sort=new');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.title', 'New Post');
});

test('store creates new sub with valid data', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/subs', [
        'name' => 'new-sub',
        'display_name' => 'New Sub',
        'description' => 'A new subcommunity',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.name', 'new-sub');
    $response->assertJsonPath('data.display_name', 'New Sub');

    expect(Sub::where('name', 'new-sub')->exists())->toBeTrue();
});

test('store requires authentication', function (): void {
    $response = postJson('/api/v1/subs', [
        'name' => 'auth-test',
        'display_name' => 'Auth Test',
    ]);

    $response->assertStatus(401);
});

test('join endpoint adds user as subscriber', function (): void {
    $sub = Sub::create([
        'name' => 'join-test',
        'display_name' => 'Join Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 0,
    ]);

    $newUser = User::factory()->create();
    Sanctum::actingAs($newUser);

    $response = postJson("/api/v1/subs/{$sub->id}/join");

    $response->assertStatus(200);
    $response->assertJsonPath('is_member', true);

    expect($sub->subscribers()->where('user_id', $newUser->id)->exists())->toBeTrue();
});

test('join endpoint does not allow joining twice', function (): void {
    $sub = Sub::create([
        'name' => 'double-join',
        'display_name' => 'Double Join',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 1,
    ]);

    Sanctum::actingAs($this->user);
    $sub->subscribers()->attach($this->user->id);

    $response = postJson("/api/v1/subs/{$sub->id}/join");

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('subs.already_member'));
});

test('leave endpoint removes user as subscriber', function (): void {
    $sub = Sub::create([
        'name' => 'leave-test',
        'display_name' => 'Leave Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 1,
    ]);

    Sanctum::actingAs($this->user);
    $sub->subscribers()->attach($this->user->id);

    $response = postJson("/api/v1/subs/{$sub->id}/leave");

    $response->assertStatus(200);
    $response->assertJsonPath('is_member', false);

    expect($sub->subscribers()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

test('index with my_subs filters user subs', function (): void {
    $otherUser = User::factory()->create();

    $mySub = Sub::create([
        'name' => 'my-sub',
        'display_name' => 'My Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $otherSub = Sub::create([
        'name' => 'other-sub',
        'display_name' => 'Other Sub',
        'created_by' => $otherUser->id,
        'icon' => '🎨',
        'color' => '#EC4899',
    ]);

    Sanctum::actingAs($this->user);
    $mySub->subscribers()->attach($this->user->id);

    $response = getJson('/api/v1/subs?my_subs=true');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'my-sub');
});

test('index with category featured limits to 10 results', function (): void {
    for ($i = 0; $i < 15; $i++) {
        Sub::create([
            'name' => "featured-{$i}",
            'display_name' => "Featured {$i}",
            'created_by' => $this->user->id,
            'icon' => '💻',
            'color' => '#3B82F6',
        ]);
    }

    $response = getJson('/api/v1/subs?category=featured');

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBeLessThanOrEqual(10);
});

test('index calcula score correctamente (members * 100 + posts)', function (): void {
    $sub1 = Sub::create([
        'name' => 'high-score',
        'display_name' => 'High Score',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 10,
        'posts_count' => 5,
    ]);

    $sub2 = Sub::create([
        'name' => 'low-score',
        'display_name' => 'Low Score',
        'created_by' => $this->user->id,
        'icon' => '🎮',
        'color' => '#EC4899',
        'members_count' => 1,
        'posts_count' => 2,
    ]);

    $response = getJson('/api/v1/subs?category=all');

    $response->assertStatus(200);
    // high-score should be first (10*100+5=1005 > 1*100+2=102)
    $response->assertJsonPath('data.0.name', 'high-score');
});

test('index includes is_member field for authenticated users', function (): void {
    $sub = Sub::create([
        'name' => 'member-field-test',
        'display_name' => 'Member Field Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($this->user);
    $sub->subscribers()->attach($this->user->id);

    $response = getJson('/api/v1/subs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.is_member', true);
});

test('index includes is_member false for unauthenticated users', function (): void {
    Sub::create([
        'name' => 'guest-test',
        'display_name' => 'Guest Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response = getJson('/api/v1/subs');

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.is_member', false);
});

test('posts endpoint supports sorting by hot (score)', function (): void {
    $sub = Sub::create([
        'name' => 'hot-sort',
        'display_name' => 'Hot Sort',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $hotPost = Post::create([
        'title' => 'Hot Post',
        'content' => 'Hot content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
        'score' => 100,
    ]);

    $coldPost = Post::create([
        'title' => 'Cold Post',
        'content' => 'Cold content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
        'score' => 10,
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}/posts?sort=hot");

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.title', 'Hot Post');
});

test('posts endpoint supports sorting by top (upvotes)', function (): void {
    $sub = Sub::create([
        'name' => 'top-sort',
        'display_name' => 'Top Sort',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $topPost = Post::create([
        'title' => 'Top Post',
        'content' => 'Top content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
        'upvotes' => 50,
    ]);

    $lowPost = Post::create([
        'title' => 'Low Post',
        'content' => 'Low content',
        'user_id' => $this->user->id,
        'sub_id' => $sub->id,
        'upvotes' => 5,
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}/posts?sort=top");

    $response->assertStatus(200);
    $response->assertJsonPath('data.0.title', 'Top Post');
});

test('posts endpoint returns 404 if sub does not exist', function (): void {
    $response = getJson('/api/v1/subs/999999/posts');

    $response->assertStatus(404);
});

test('posts endpoint respects custom pagination', function (): void {
    $sub = Sub::create([
        'name' => 'pagination-test',
        'display_name' => 'Pagination Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Post::factory()->count(20)->create([
        'sub_id' => $sub->id,
        'user_id' => $this->user->id,
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}/posts?per_page=5");

    $response->assertStatus(200);
    expect(count($response->json('data')))->toBe(5);
    $response->assertJsonPath('meta.per_page', 5);
});

test('store automatically subscribes creator', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/subs', [
        'name' => 'auto-subscribe',
        'display_name' => 'Auto Subscribe',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);

    $sub = Sub::where('name', 'auto-subscribe')->first();
    expect($sub->subscribers()->where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('store sets members_count to 1', function (): void {
    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/subs', [
        'name' => 'member-count-test',
        'display_name' => 'Member Count Test',
        'icon' => '🚀',
        'color' => '#10B981',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.members_count', 1);
});

test('store validates unique name', function (): void {
    Sub::create([
        'name' => 'existing-sub',
        'display_name' => 'Existing Sub',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson('/api/v1/subs', [
        'name' => 'existing-sub',
        'display_name' => 'Duplicate Sub',
    ]);

    $response->assertStatus(422);
});

test('join updates members_count correctamente', function (): void {
    $sub = Sub::create([
        'name' => 'count-test',
        'display_name' => 'Count Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 5,
    ]);

    $newUser = User::factory()->create();
    Sanctum::actingAs($newUser);

    $response = postJson("/api/v1/subs/{$sub->id}/join");

    $response->assertStatus(200);

    $sub->refresh();
    expect($sub->members_count)->toBe(1); // Should be real count
});

test('join requires authentication', function (): void {
    $sub = Sub::create([
        'name' => 'auth-join',
        'display_name' => 'Auth Join',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response = postJson("/api/v1/subs/{$sub->id}/join");

    $response->assertStatus(401);
});

test('leave updates members_count correctamente', function (): void {
    $sub = Sub::create([
        'name' => 'leave-count',
        'display_name' => 'Leave Count',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'members_count' => 2,
    ]);

    Sanctum::actingAs($this->user);
    $sub->subscribers()->attach($this->user->id);

    $response = postJson("/api/v1/subs/{$sub->id}/leave");

    $response->assertStatus(200);

    $sub->refresh();
    expect($sub->members_count)->toBe(0);
});

test('leave returns message if not a member', function (): void {
    $sub = Sub::create([
        'name' => 'not-member-leave',
        'display_name' => 'Not Member Leave',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/subs/{$sub->id}/leave");

    $response->assertStatus(200);
    $response->assertJsonPath('message', __('subs.not_member'));
});

test('leave requires authentication', function (): void {
    $sub = Sub::create([
        'name' => 'auth-leave',
        'display_name' => 'Auth Leave',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
    ]);

    $response = postJson("/api/v1/subs/{$sub->id}/leave");

    $response->assertStatus(401);
});

test('rules endpoint returns sub rules', function (): void {
    $rules = json_encode([
        ['title' => 'Be respectful', 'description' => 'Treat others well'],
        ['title' => 'No spam', 'description' => 'Avoid promotional content'],
    ]);

    $sub = Sub::create([
        'name' => 'rules-test',
        'display_name' => 'Rules Test',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'rules' => $rules,
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}/rules");

    $response->assertStatus(200);
    $response->assertJsonPath('sub_id', $sub->id);
    expect($response->json('data'))->toBe($rules);
});

test('createMembershipRequest creates membership request', function (): void {
    $owner = User::factory()->create();
    $sub = Sub::create([
        'name' => 'private-sub',
        'display_name' => 'Private Sub',
        'created_by' => $owner->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'is_private' => true,
        'require_approval' => true,
    ]);

    Sanctum::actingAs($this->user);

    $response = postJson("/api/v1/subs/{$sub->id}/membership-requests", [
        'message' => 'Please let me join!',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'sub_id',
        'request_pending',
    ]);
    $response->assertJsonPath('request_pending', true);

    // Verify the subscription was created with pending status
    $this->assertDatabaseHas('sub_subscriptions', [
        'sub_id' => $sub->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);
});

test('show parsea rules JSON correctamente', function (): void {
    $sub = Sub::create([
        'name' => 'json-rules',
        'display_name' => 'JSON Rules',
        'created_by' => $this->user->id,
        'icon' => '💻',
        'color' => '#3B82F6',
        'rules' => json_encode([
            ['title' => 'Rule 1', 'description' => 'First rule'],
            ['title' => 'Rule 2', 'description' => 'Second rule'],
        ]),
    ]);

    $response = getJson("/api/v1/subs/{$sub->id}");

    $response->assertStatus(200);
    expect($response->json('data.rules'))->toBeArray();
    expect(count($response->json('data.rules')))->toBe(2);
});
