<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use App\Models\Sub;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubCommentCreated
{
    use Dispatchable;

    use SerializesModels;

    public function __construct(
        public Sub $sub,
        public Comment $comment,
    ) {}
}
