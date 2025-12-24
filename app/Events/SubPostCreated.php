<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use App\Models\Sub;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubPostCreated
{
    use Dispatchable;

    use SerializesModels;

    public function __construct(
        public Sub $sub,
        public Post $post,
    ) {}
}
