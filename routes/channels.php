<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

// Private channel for user notifications
Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

// Presence channel for Agora (to know who is online)
Broadcast::channel('agora.presence', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => $user->avatar,
        ];
    }

    return false;
});

// Global presence channel to count connected users (dynamic throttle)
Broadcast::channel('realtime.presence', function ($user) {
    if ($user) {
        return [
            'id' => $user->id,
        ];
    }

    return false;
});

// Public channels for post stats - no authorization required
// posts.frontpage, posts.pending, sub.{id}, post.{id}
// Defined as public in events (Channel instead of PrivateChannel)
