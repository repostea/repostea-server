<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeleteActivityPubPost;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Command to send Delete activity for a post to ActivityPub followers.
 */
final class ActivityPubDeletePost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitypub:delete
        {post : The post ID to delete from ActivityPub}
        {--legacy : Use legacy Note ID format (client URL with slug) for posts sent before the fix}';

    /**
     * The console command description.
     */
    protected $description = 'Send a Delete activity for a post to all ActivityPub followers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $postId = (int) $this->argument('post');
        $legacy = $this->option('legacy');

        $post = Post::withTrashed()->find($postId);

        if ($post === null) {
            $this->error("Post {$postId} not found.");

            return self::FAILURE;
        }

        $mode = $legacy ? 'legacy' : 'standard';
        $this->info("Dispatching Delete activity ({$mode}) for post {$post->id}: {$post->title}");

        DeleteActivityPubPost::dispatch($post->id, $post->slug, $legacy);

        $this->info('Delete activity dispatched. Check the queue worker logs.');

        return self::SUCCESS;
    }
}
