<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\CommentModerationService;

beforeEach(function (): void {
    $this->service = app(CommentModerationService::class);
    $this->moderator = User::factory()->create();
    $this->user = User::factory()->create();
    $this->post = Post::factory()->create(['user_id' => $this->user->id]);
    $this->comment = Comment::create([
        'content' => 'Test comment content',
        'user_id' => $this->user->id,
        'post_id' => $this->post->id,
        'status' => 'published',
    ]);
});

test('hide marks comment as hidden', function (): void {
    $result = $this->service->hide($this->comment, $this->moderator, 'Spam content');

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.comments.hidden'));
    expect($result['comment']->status)->toBe('hidden');
    expect($result['comment']->moderation_reason)->toBe('Spam content');
    expect($result['comment']->moderated_by)->toBe($this->moderator->id);
    expect($result['comment']->moderated_at)->not->toBeNull();
});

test('hide works without reason', function (): void {
    $result = $this->service->hide($this->comment, $this->moderator);

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('hidden');
    expect($result['comment']->moderation_reason)->toBeNull();
});

test('unhide restores comment to published', function (): void {
    // First hide the comment
    $this->comment->update([
        'status' => 'hidden',
        'moderated_by' => $this->moderator->id,
        'moderated_at' => now(),
    ]);

    $result = $this->service->unhide($this->comment);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.comments.unhidden'));
    expect($result['comment']->status)->toBe('published');
    expect($result['comment']->moderation_reason)->toBeNull();
    expect($result['comment']->moderated_by)->toBeNull();
    expect($result['comment']->moderated_at)->toBeNull();
});

test('deleteByModerator marks comment as deleted and clears content', function (): void {
    $result = $this->service->deleteByModerator($this->comment, $this->moderator, 'Violated rules');

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.comments.deleted_by_moderator'));
    expect($result['comment']->status)->toBe('deleted_by_moderator');
    expect($result['comment']->content)->toBe('[deleted by moderator]');
    expect($result['comment']->moderation_reason)->toBe('Violated rules');
    expect($result['comment']->moderated_by)->toBe($this->moderator->id);
});

test('restore restores hidden comment', function (): void {
    $this->comment->update(['status' => 'hidden']);

    $result = $this->service->restore($this->comment);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe(__('messages.comments.restored'));
    expect($result['comment']->status)->toBe('published');
});

test('restore restores deleted_by_moderator comment', function (): void {
    $this->comment->update(['status' => 'deleted_by_moderator']);

    $result = $this->service->restore($this->comment);

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('published');
});

test('restore fails for published comment', function (): void {
    $result = $this->service->restore($this->comment);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('messages.comments.cannot_restore'));
});

test('restore fails for user-deleted comment', function (): void {
    $this->comment->update(['status' => 'deleted']);

    $result = $this->service->restore($this->comment);

    expect($result['success'])->toBeFalse();
});

test('moderate with hide action hides comment', function (): void {
    $result = $this->service->moderate($this->comment, 'hide', $this->moderator, 'Reason');

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('hidden');
});

test('moderate with unhide action unhides comment', function (): void {
    $this->comment->update(['status' => 'hidden']);

    $result = $this->service->moderate($this->comment, 'unhide', $this->moderator);

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('published');
});

test('moderate with delete action deletes comment', function (): void {
    $result = $this->service->moderate($this->comment, 'delete', $this->moderator);

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('deleted_by_moderator');
});

test('moderate with restore action restores comment', function (): void {
    $this->comment->update(['status' => 'hidden']);

    $result = $this->service->moderate($this->comment, 'restore', $this->moderator);

    expect($result['success'])->toBeTrue();
    expect($result['comment']->status)->toBe('published');
});

test('moderate throws for invalid action', function (): void {
    $this->service->moderate($this->comment, 'invalid', $this->moderator);
})->throws(InvalidArgumentException::class, 'Invalid moderation action');

test('isModerated returns true for hidden comment', function (): void {
    $this->comment->update(['status' => 'hidden']);

    expect($this->service->isModerated($this->comment))->toBeTrue();
});

test('isModerated returns true for deleted_by_moderator comment', function (): void {
    $this->comment->update(['status' => 'deleted_by_moderator']);

    expect($this->service->isModerated($this->comment))->toBeTrue();
});

test('isModerated returns false for published comment', function (): void {
    expect($this->service->isModerated($this->comment))->toBeFalse();
});

test('canRestore returns true for hidden comment', function (): void {
    $this->comment->update(['status' => 'hidden']);

    expect($this->service->canRestore($this->comment))->toBeTrue();
});

test('canRestore returns false for published comment', function (): void {
    expect($this->service->canRestore($this->comment))->toBeFalse();
});

test('getModerationInfo returns correct info for moderated comment', function (): void {
    $this->comment->update([
        'status' => 'hidden',
        'moderation_reason' => 'Test reason',
        'moderated_by' => $this->moderator->id,
        'moderated_at' => now(),
    ]);

    $info = $this->service->getModerationInfo($this->comment->fresh());

    expect($info['is_moderated'])->toBeTrue();
    expect($info['status'])->toBe('hidden');
    expect($info['reason'])->toBe('Test reason');
    expect($info['moderated_by'])->toBe($this->moderator->id);
    expect($info['moderated_at'])->not->toBeNull();
});

test('getModerationInfo returns correct info for published comment', function (): void {
    $info = $this->service->getModerationInfo($this->comment);

    expect($info['is_moderated'])->toBeFalse();
    expect($info['status'])->toBe('published');
    expect($info['reason'])->toBeNull();
    expect($info['moderated_by'])->toBeNull();
    expect($info['moderated_at'])->toBeNull();
});

test('valid actions constant contains all actions', function (): void {
    expect(CommentModerationService::VALID_ACTIONS)->toBe(['hide', 'unhide', 'delete', 'restore']);
});
