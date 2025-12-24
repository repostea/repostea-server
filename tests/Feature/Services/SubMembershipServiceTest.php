<?php

declare(strict_types=1);

use App\Events\SubMemberJoined;
use App\Models\Sub;
use App\Models\User;
use App\Notifications\MembershipRequestReceived;
use App\Notifications\SubOrphaned;
use App\Services\SubMembershipService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->service = app(SubMembershipService::class);

    $this->creator = User::factory()->create();
    $this->sub = Sub::create([
        'name' => 'test-sub',
        'display_name' => 'Test Sub',
        'created_by' => $this->creator->id,
        'members_count' => 1,
        'icon' => '📝',
        'color' => '#6366F1',
    ]);
    $this->sub->subscribers()->attach($this->creator->id, ['status' => 'active']);
});

test('join adds user to public sub', function (): void {
    Event::fake();
    $user = User::factory()->create();

    $result = $this->service->join($this->sub, $user);

    expect($result['success'])->toBeTrue();
    expect($result['is_member'])->toBeTrue();
    expect($result['request_pending'])->toBeFalse();
    expect($this->sub->subscribers()->where('user_id', $user->id)->exists())->toBeTrue();

    Event::assertDispatched(SubMemberJoined::class);
});

test('join returns already member if user is already a member', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'active']);

    $result = $this->service->join($this->sub, $user);

    expect($result['is_member'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.already_member'));
});

test('join creates pending request for private sub', function (): void {
    Notification::fake();

    $this->sub->update(['is_private' => true]);
    $user = User::factory()->create();

    $result = $this->service->join($this->sub, $user, 'Please let me in');

    expect($result['is_member'])->toBeFalse();
    expect($result['request_pending'])->toBeTrue();

    $subscription = $this->sub->subscribers()
        ->where('user_id', $user->id)
        ->withPivot('status', 'request_message')
        ->first();

    expect($subscription->pivot->status)->toBe('pending');
    expect($subscription->pivot->request_message)->toBe('Please let me in');

    Notification::assertSentTo($this->creator, MembershipRequestReceived::class);
});

test('join returns pending status if request already exists', function (): void {
    $this->sub->update(['is_private' => true]);
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'pending']);

    $result = $this->service->join($this->sub, $user);

    expect($result['is_member'])->toBeFalse();
    expect($result['request_pending'])->toBeTrue();
    expect($result['message'])->toBe(__('subs.request_pending'));
});

test('leave removes user from sub', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'active']);
    $this->sub->update(['members_count' => 2]);

    $result = $this->service->leave($this->sub, $user);

    expect($result['success'])->toBeTrue();
    expect($result['is_member'])->toBeFalse();
    expect($this->sub->subscribers()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('leave returns not member if user is not a member', function (): void {
    $user = User::factory()->create();

    $result = $this->service->leave($this->sub, $user);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.not_member'));
});

test('leave marks sub as orphaned when owner leaves', function (): void {
    Notification::fake();

    $moderator = User::factory()->create();
    $this->sub->subscribers()->attach($moderator->id, ['status' => 'active']);
    $this->sub->moderators()->attach($moderator->id);
    $this->sub->update(['members_count' => 2]);

    $result = $this->service->leave($this->sub, $this->creator);

    expect($result['success'])->toBeTrue();

    $this->sub->refresh();
    expect($this->sub->orphaned_at)->not->toBeNull();

    Notification::assertSentTo($moderator, SubOrphaned::class);
});

test('getMembers returns paginated members', function (): void {
    $users = User::factory()->count(3)->create();
    foreach ($users as $user) {
        $this->sub->subscribers()->attach($user->id, ['status' => 'active']);
    }
    $this->sub->update(['members_count' => 4]);

    $members = $this->service->getMembers($this->sub);

    expect($members->total())->toBe(4);
});

test('isActiveMember returns true for active member', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'active']);

    expect($this->service->isActiveMember($this->sub, $user))->toBeTrue();
});

test('isActiveMember returns false for pending member', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'pending']);

    expect($this->service->isActiveMember($this->sub, $user))->toBeFalse();
});

test('isActiveMember returns false for null user', function (): void {
    expect($this->service->isActiveMember($this->sub, null))->toBeFalse();
});

test('removeMember detaches user from sub', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'active']);
    $this->sub->update(['members_count' => 2]);

    $result = $this->service->removeMember($this->sub, $user->id);

    expect($result['success'])->toBeTrue();
    expect($this->sub->subscribers()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('removeMember fails when trying to remove creator', function (): void {
    $result = $this->service->removeMember($this->sub, $this->creator->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.cannot_remove_creator'));
});

test('getPendingRequests returns pending subscriptions', function (): void {
    $this->sub->update(['is_private' => true]);

    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $this->sub->subscribers()->attach($user->id, [
            'status' => 'pending',
            'request_message' => 'Please accept me',
        ]);
    }

    $requests = $this->service->getPendingRequests($this->sub);

    expect($requests->total())->toBe(2);
});

test('approveMembershipRequest activates pending subscription', function (): void {
    Event::fake();

    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'pending']);

    $result = $this->service->approveMembershipRequest($this->sub, $user->id);

    expect($result['success'])->toBeTrue();

    $subscription = $this->sub->subscribers()
        ->where('user_id', $user->id)
        ->withPivot('status')
        ->first();

    expect($subscription->pivot->status)->toBe('active');

    Event::assertDispatched(SubMemberJoined::class);
});

test('approveMembershipRequest fails for non-existent request', function (): void {
    $user = User::factory()->create();

    $result = $this->service->approveMembershipRequest($this->sub, $user->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.request_not_found'));
});

test('rejectMembershipRequest removes pending subscription', function (): void {
    $user = User::factory()->create();
    $this->sub->subscribers()->attach($user->id, ['status' => 'pending']);

    $result = $this->service->rejectMembershipRequest($this->sub, $user->id);

    expect($result['success'])->toBeTrue();
    expect($this->sub->subscribers()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('rejectMembershipRequest fails for non-existent request', function (): void {
    $user = User::factory()->create();

    $result = $this->service->rejectMembershipRequest($this->sub, $user->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('subs.request_not_found'));
});
