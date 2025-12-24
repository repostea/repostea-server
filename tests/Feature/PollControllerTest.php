<?php

declare(strict_types=1);

use App\Models\PollVote;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

test('getResults returns poll results', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
            'allow_multiple_options' => false,
        ]),
    ]);

    // Create some votes
    $voters = User::factory()->count(5)->create();
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[0]->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[1]->id, 'option_number' => 1, 'ip_address' => '1.1.1.2']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[2]->id, 'option_number' => 2, 'ip_address' => '1.1.1.3']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[3]->id, 'option_number' => 3, 'ip_address' => '1.1.1.4']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[4]->id, 'option_number' => 3, 'ip_address' => '1.1.1.5']);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'total_votes',
        'options' => [
            '*' => ['id', 'text', 'votes', 'percentage'],
        ],
        'expired',
        'expires_at',
        'allow_multiple_options',
        'user_has_voted',
        'user_votes',
    ]);

    expect($response->json('total_votes'))->toBe(5);
    expect($response->json('success'))->toBeTrue();
});

test('getResults calcula porcentajes correctamente', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción A', 'Opción B'],
        ]),
    ]);

    // 3 votes for option 1, 1 vote for option 2 (total 4)
    $voters = User::factory()->count(4)->create();
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[0]->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[1]->id, 'option_number' => 1, 'ip_address' => '1.1.1.2']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[2]->id, 'option_number' => 1, 'ip_address' => '1.1.1.3']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $voters[3]->id, 'option_number' => 2, 'ip_address' => '1.1.1.4']);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    $options = $response->json('options');

    expect($options[0]['votes'])->toBe(3);
    expect($options[0]['percentage'])->toBeGreaterThanOrEqual(74.9);
    expect($options[0]['percentage'])->toBeLessThanOrEqual(75.1);
    expect($options[1]['votes'])->toBe(1);
    expect($options[1]['percentage'])->toBeGreaterThanOrEqual(24.9);
    expect($options[1]['percentage'])->toBeLessThanOrEqual(25.1);
});

test('getResults returns error for non-poll post', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'text',
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.not_a_poll'));
    $response->assertJsonPath('success', false);
});

test('getResults returns error if no options', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([]),
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.options_not_found'));
});

test('getResults detects if poll is expired', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
            'expires_at' => now()->subDays(1)->toIso8601String(),
        ]),
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    expect($response->json('expired'))->toBeTrue();
});

test('getResults detects if poll is not expired', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]),
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    expect($response->json('expired'))->toBeFalse();
});

test('getResults detects if authenticated user has voted', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    expect($response->json('user_has_voted'))->toBeTrue();
    expect($response->json('user_votes'))->toBe([1]);
});

test('getResults returns empty votes for unauthenticated user', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    expect($response->json('user_has_voted'))->toBeFalse();
    expect($response->json('user_votes'))->toBe([]);
});

test('vote registra voto correctamente', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
        ]),
    ]);

    Sanctum::actingAs($user);

    $response = postJson("/api/v1/polls/{$post->id}/vote/2");

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', __('messages.polls.vote_recorded'));

    // Verify vote was created in database
    expect(PollVote::where('post_id', $post->id)->where('user_id', $user->id)->count())->toBe(1);
    $vote = PollVote::where('post_id', $post->id)->where('user_id', $user->id)->first();
    expect($vote->option_number)->toBe(2);
});

test('vote requires authentication', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    $response = postJson("/api/v1/polls/{$post->id}/vote/1");

    $response->assertStatus(401);
    $response->assertJsonPath('message', __('messages.polls.login_required_vote'));
});

test('vote returns error for non-poll post', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['content_type' => 'text']);

    Sanctum::actingAs($user);

    $response = postJson("/api/v1/polls/{$post->id}/vote/1");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.not_a_poll'));
});

test('vote returns error for invalid option', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    Sanctum::actingAs($user);

    $response = postJson("/api/v1/polls/{$post->id}/vote/5");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.invalid_option'));
});

test('vote returns error for expired poll', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
            'expires_at' => now()->subDays(1)->toIso8601String(),
        ]),
    ]);

    Sanctum::actingAs($user);

    $response = postJson("/api/v1/polls/{$post->id}/vote/1");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.expired'));
});

test('vote returns error if user already voted for that option', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    $response = postJson("/api/v1/polls/{$post->id}/vote/1");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.already_voted'));
});

test('vote removes previous vote if multiple options not allowed', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
            'allow_multiple_options' => false,
        ]),
    ]);

    // First vote
    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    // Vote for different option
    $response = postJson("/api/v1/polls/{$post->id}/vote/2");

    $response->assertStatus(200);

    // Verify only one vote exists and it's for option 2
    expect(PollVote::where('post_id', $post->id)->where('user_id', $user->id)->count())->toBe(1);
    $vote = PollVote::where('post_id', $post->id)->where('user_id', $user->id)->first();
    expect($vote->option_number)->toBe(2);
});

test('vote allows multiple votes if allow_multiple_options is true', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
            'allow_multiple_options' => true,
        ]),
    ]);

    // First vote
    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    // Vote for different option
    $response = postJson("/api/v1/polls/{$post->id}/vote/2");

    $response->assertStatus(200);

    // Verify both votes exist
    expect(PollVote::where('post_id', $post->id)->where('user_id', $user->id)->count())->toBe(2);
});

test('removeVote deletes vote correctamente', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    $response = deleteJson("/api/v1/polls/{$post->id}/vote");

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', __('messages.polls.vote_removed'));

    // Verify vote was deleted
    expect(PollVote::where('post_id', $post->id)->where('user_id', $user->id)->count())->toBe(0);
});

test('removeVote removes all user votes', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
            'allow_multiple_options' => true,
        ]),
    ]);

    // Create multiple votes
    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 1, 'ip_address' => '1.1.1.1']);
    PollVote::create(['post_id' => $post->id, 'user_id' => $user->id, 'option_number' => 2, 'ip_address' => '1.1.1.1']);

    Sanctum::actingAs($user);

    $response = deleteJson("/api/v1/polls/{$post->id}/vote");

    $response->assertStatus(200);

    // Verify all votes were deleted
    expect(PollVote::where('post_id', $post->id)->where('user_id', $user->id)->count())->toBe(0);
});

test('removeVote requires authentication', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    $response = deleteJson("/api/v1/polls/{$post->id}/vote");

    $response->assertStatus(401);
    $response->assertJsonPath('message', __('messages.polls.login_required_remove'));
});

test('removeVote returns error if no votes to remove', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2'],
        ]),
    ]);

    Sanctum::actingAs($user);

    $response = deleteJson("/api/v1/polls/{$post->id}/vote");

    $response->assertStatus(404);
    $response->assertJsonPath('message', __('messages.polls.no_votes_to_remove'));
});

test('removeVote returns error for non-poll post', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->create(['content_type' => 'text']);

    Sanctum::actingAs($user);

    $response = deleteJson("/api/v1/polls/{$post->id}/vote");

    $response->assertStatus(400);
    $response->assertJsonPath('message', __('messages.polls.not_a_poll'));
});

test('getResults handles poll without votes correctly', function (): void {
    $post = Post::factory()->create([
        'content_type' => 'poll',
        'media_metadata' => json_encode([
            'poll_options' => ['Opción 1', 'Opción 2', 'Opción 3'],
        ]),
    ]);

    $response = getJson("/api/v1/polls/{$post->id}/results");

    $response->assertStatus(200);
    expect($response->json('total_votes'))->toBe(0);

    $options = $response->json('options');
    foreach ($options as $option) {
        expect($option['votes'])->toBe(0);
        expect($option['percentage'])->toBe(0);
    }
});
