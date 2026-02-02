<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Sub;
use App\Models\User;
use App\Services\RealtimeBroadcastService;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->service = app(RealtimeBroadcastService::class);
    Cache::flush();
});

test('queueUpdate stores updates in cache', function (): void {
    $this->service->queueUpdate(1, ['votes' => 10], ['posts.frontpage']);

    $pending = Cache::get('realtime:pending:posts.frontpage');

    expect($pending)->toBeArray();
    expect($pending[1])->toBe(['id' => 1, 'votes' => 10]);
});

test('queueUpdate merges updates for same post', function (): void {
    $this->service->queueUpdate(1, ['votes' => 10], ['posts.frontpage']);
    $this->service->queueUpdate(1, ['comments_count' => 5], ['posts.frontpage']);

    $pending = Cache::get('realtime:pending:posts.frontpage');

    expect($pending[1]['votes'])->toBe(10);
    expect($pending[1]['comments_count'])->toBe(5);
});

test('queueUpdate stores updates in multiple channels', function (): void {
    $this->service->queueUpdate(1, ['votes' => 10], ['posts.frontpage', 'sub.5']);

    $frontpage = Cache::get('realtime:pending:posts.frontpage');
    $sub = Cache::get('realtime:pending:sub.5');

    expect($frontpage[1]['votes'])->toBe(10);
    expect($sub[1]['votes'])->toBe(10);
});

test('getChannelsForPost returns post channel', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

    $channels = $this->service->getChannelsForPost($post);

    expect($channels)->toContain('post.' . $post->id);
});

test('getChannelsForPost includes frontpage channel for published posts', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id, 'status' => 'published']);

    $channels = $this->service->getChannelsForPost($post);

    expect($channels)->toContain('posts.frontpage');
    expect($channels)->not->toContain('posts.pending');
});

test('getChannelsForPost includes pending channel for pending posts', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

    $channels = $this->service->getChannelsForPost($post);

    expect($channels)->toContain('posts.pending');
    expect($channels)->not->toContain('posts.frontpage');
});

test('getChannelsForPost includes sub channel when post has sub', function (): void {
    $user = User::factory()->create();
    $sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $user->id,
        'members_count' => 1,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);
    $post = Post::factory()->create(['user_id' => $user->id, 'sub_id' => $sub->id]);

    $channels = $this->service->getChannelsForPost($post);

    expect($channels)->toContain('sub.' . $sub->id);
});

test('updateConnectionsCount stores count in cache', function (): void {
    $this->service->updateConnectionsCount(150);

    expect($this->service->getConnectionsCount())->toBe(150);
});

test('getConnectionsCount returns 0 when not set', function (): void {
    expect($this->service->getConnectionsCount())->toBe(0);
});

test('getThrottleInterval returns correct interval based on connections', function (): void {
    // Very few users
    $this->service->updateConnectionsCount(25);
    expect($this->service->getThrottleInterval())->toBe(2);

    // Low traffic
    $this->service->updateConnectionsCount(75);
    expect($this->service->getThrottleInterval())->toBe(3);

    // Medium traffic
    $this->service->updateConnectionsCount(200);
    expect($this->service->getThrottleInterval())->toBe(5);

    // High traffic
    $this->service->updateConnectionsCount(400);
    expect($this->service->getThrottleInterval())->toBe(8);

    // Very high traffic
    $this->service->updateConnectionsCount(750);
    expect($this->service->getThrottleInterval())->toBe(12);

    // Extreme traffic
    $this->service->updateConnectionsCount(1500);
    expect($this->service->getThrottleInterval())->toBe(15);
});

test('shouldFlush returns true when interval has passed', function (): void {
    // First call should return true (no previous flush)
    expect($this->service->shouldFlush())->toBeTrue();

    // Immediate second call should return false
    expect($this->service->shouldFlush())->toBeFalse();
});

test('queueVoteChange queues vote update', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'votes_count' => 15,
    ]);

    $this->service->queueVoteChange($post, 1);

    $pending = Cache::get('realtime:pending:posts.frontpage');
    expect($pending[$post->id]['votes'])->toBe($post->votes_count);
});

test('queueVoteChange broadcasts integer not collection', function (): void {
    $user = User::factory()->create();
    $voter = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'votes_count' => 0,
    ]);

    // Create actual votes
    $post->votes()->create(['user_id' => $voter->id, 'value' => 1, 'type' => 'default']);
    $post->updateVotesCount();
    $post->refresh();

    $this->service->queueVoteChange($post, 1);

    $pending = Cache::get('realtime:pending:posts.frontpage');
    $broadcastedVotes = $pending[$post->id]['votes'];

    // Must be an integer, not a collection
    expect($broadcastedVotes)->toBeInt();
    // Must match the actual vote count
    expect($broadcastedVotes)->toBe(1);
    // Double-check it matches the DB field
    expect($broadcastedVotes)->toBe($post->votes_count);
});

test('queueCommentChange queues comment count update', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'comment_count' => 8,
    ]);

    $this->service->queueCommentChange($post, 1);

    $pending = Cache::get('realtime:pending:posts.frontpage');
    expect($pending[$post->id]['comments_count'])->toBe($post->comment_count);
});

test('queueCommentChange broadcasts integer not null', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'comment_count' => 5,
    ]);

    $this->service->queueCommentChange($post, 1);

    $pending = Cache::get('realtime:pending:posts.frontpage');
    $broadcastedCount = $pending[$post->id]['comments_count'];

    // Must be an integer, not null (from wrong field name)
    expect($broadcastedCount)->toBeInt();
    expect($broadcastedCount)->toBe(5);
    expect($broadcastedCount)->toBe($post->comment_count);
});

test('queueViewsChange queues views update', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'views' => 100,
        'total_views' => 150,
    ]);

    $this->service->queueViewsChange($post);

    $pending = Cache::get('realtime:pending:posts.frontpage');
    expect($pending[$post->id]['views'])->toBe(100);
    expect($pending[$post->id]['total_views'])->toBe(150);
});

test('queueViewsChange broadcasts integers not collections', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => 'published',
        'views' => 42,
        'total_views' => 100,
    ]);

    $this->service->queueViewsChange($post);

    $pending = Cache::get('realtime:pending:posts.frontpage');

    // Must be integers, not collections from views() relation
    expect($pending[$post->id]['views'])->toBeInt();
    expect($pending[$post->id]['total_views'])->toBeInt();
    expect($pending[$post->id]['views'])->toBe(42);
    expect($pending[$post->id]['total_views'])->toBe(100);
});
