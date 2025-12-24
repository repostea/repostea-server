<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\RemoteUser;
use Illuminate\Database\Seeder;

/**
 * Seeds test data for federation features visualization.
 * Run with: php artisan db:seed --class=FederationTestSeeder.
 */
final class FederationTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating remote users from various fediverse instances...');

        // Create diverse remote users from different platforms
        $remoteUsers = [
            [
                'actor_uri' => 'https://mastodon.social/users/techfan42',
                'username' => 'techfan42',
                'domain' => 'mastodon.social',
                'display_name' => 'Tech Enthusiast',
                'avatar_url' => 'https://files.mastodon.social/accounts/avatars/000/000/001/original/avatar.png',
                'profile_url' => 'https://mastodon.social/@techfan42',
                'software' => 'mastodon',
            ],
            [
                'actor_uri' => 'https://lemmy.ml/u/rustacean',
                'username' => 'rustacean',
                'domain' => 'lemmy.ml',
                'display_name' => 'Rust Developer',
                'avatar_url' => null,
                'profile_url' => 'https://lemmy.ml/u/rustacean',
                'software' => 'lemmy',
            ],
            [
                'actor_uri' => 'https://pleroma.site/users/opensourcefan',
                'username' => 'opensourcefan',
                'domain' => 'pleroma.site',
                'display_name' => 'Open Source Advocate',
                'avatar_url' => 'https://pleroma.site/media/avatars/opensourcefan.jpg',
                'profile_url' => 'https://pleroma.site/users/opensourcefan',
                'software' => 'pleroma',
            ],
            [
                'actor_uri' => 'https://kbin.social/u/newsgatherer',
                'username' => 'newsgatherer',
                'domain' => 'kbin.social',
                'display_name' => 'News Collector',
                'avatar_url' => null,
                'profile_url' => 'https://kbin.social/u/newsgatherer',
                'software' => 'kbin',
            ],
            [
                'actor_uri' => 'https://misskey.io/users/artlover',
                'username' => 'artlover',
                'domain' => 'misskey.io',
                'display_name' => 'Digital Artist',
                'avatar_url' => 'https://misskey.io/files/artlover_avatar.webp',
                'profile_url' => 'https://misskey.io/@artlover',
                'software' => 'misskey',
            ],
            [
                'actor_uri' => 'https://hachyderm.io/users/devops_guru',
                'username' => 'devops_guru',
                'domain' => 'hachyderm.io',
                'display_name' => 'DevOps Engineer',
                'avatar_url' => 'https://hachyderm.io/avatars/devops_guru.png',
                'profile_url' => 'https://hachyderm.io/@devops_guru',
                'software' => 'mastodon',
            ],
            [
                'actor_uri' => 'https://fosstodon.org/users/linuxuser',
                'username' => 'linuxuser',
                'domain' => 'fosstodon.org',
                'display_name' => 'Linux Enthusiast',
                'avatar_url' => 'https://fosstodon.org/avatars/linuxuser.png',
                'profile_url' => 'https://fosstodon.org/@linuxuser',
                'software' => 'mastodon',
            ],
        ];

        $createdRemoteUsers = [];
        foreach ($remoteUsers as $userData) {
            $createdRemoteUsers[] = RemoteUser::updateOrCreate(
                ['actor_uri' => $userData['actor_uri']],
                $userData,
            );
        }

        $this->command->info('Created ' . count($createdRemoteUsers) . ' remote users');

        // Update some existing posts with federation stats
        $posts = Post::where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($posts->isEmpty()) {
            $this->command->warn('No published posts found to update with federation stats');

            return;
        }

        $this->command->info('Updating ' . $posts->count() . ' posts with federation stats...');

        foreach ($posts as $index => $post) {
            // Varying federation engagement based on post position
            $multiplier = max(1, 10 - $index);

            $post->update([
                'federation_likes_count' => rand(0, 50) * $multiplier,
                'federation_shares_count' => rand(0, 20) * $multiplier,
                'federation_replies_count' => rand(0, 10),
            ]);

            // Add some federated comments to the first few posts
            if ($index < 5) {
                $numComments = rand(1, 3);
                for ($i = 0; $i < $numComments; $i++) {
                    $remoteUser = $createdRemoteUsers[array_rand($createdRemoteUsers)];

                    Comment::create([
                        'content' => $this->getRandomFederatedComment(),
                        'user_id' => null,
                        'remote_user_id' => $remoteUser->id,
                        'post_id' => $post->id,
                        'parent_id' => null,
                        'votes_count' => 0,
                        'is_anonymous' => false,
                        'status' => 'published',
                        'source' => $remoteUser->software,
                        'source_uri' => $remoteUser->actor_uri . '/statuses/' . uniqid(),
                    ]);
                }
            }
        }

        $this->command->info('Federation test data seeded successfully!');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->table(
            ['Metric', 'Count'],
            [
                ['Remote Users', count($createdRemoteUsers)],
                ['Posts Updated', $posts->count()],
                ['Federated Comments', Comment::whereNotNull('remote_user_id')->count()],
            ],
        );
    }

    private function getRandomFederatedComment(): string
    {
        $comments = [
            'Great article! Thanks for sharing this with the fediverse.',
            'Interesting perspective. I shared this with my followers.',
            'This is exactly what I was looking for. Boosted!',
            'Nice find! The fediverse appreciates quality content like this.',
            'Came here from Mastodon, glad to see this getting attention.',
            'Cross-posting from Lemmy - this deserves more visibility.',
            'Saw this in my feed, had to comment. Great stuff!',
            'The discussion on this is much better here than on Mastodon.',
            'Finally, a platform that gets federation right!',
            'Retweeted from the fediverse. Keep up the good work!',
            'This is why I love the open web. Quality content finds its way.',
            'Adding my 2 cents from kbin - this is spot on.',
            'The open source community needs more content like this.',
            'Federated from Pleroma. This is peak content aggregation.',
        ];

        return $comments[array_rand($comments)];
    }
}
