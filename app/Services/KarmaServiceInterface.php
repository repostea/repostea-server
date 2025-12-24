<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Vote;

interface KarmaServiceInterface
{
    public function recordActivity(User $user);

    public function updateUserKarma(User $user);

    public function processVoteKarma(Vote $vote);

    public function addKarma(User $user, int $amount, string $source, $sourceId = null, $description = null);

    public function applyEventMultipliers(int $karma);
}
