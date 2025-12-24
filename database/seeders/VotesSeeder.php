<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Seeder;

final class VotesSeeder extends Seeder
{
    public function run(): void
    {
        Vote::truncate();
        $users = User::all();
        $posts = Post::all();
        $comments = Comment::all();

        if ($users->isEmpty()) {
            $this->command->info('No hay usuarios disponibles para crear votos.');

            return;
        }

        if ($posts->isEmpty() && $comments->isEmpty()) {
            $this->command->info('No hay posts ni comentarios disponibles para votar.');

            return;
        }

        $this->command->info('Creando votos de ejemplo...');

        if (! $posts->isEmpty()) {
            $totalPostVotes = 0;

            foreach ($posts as $post) {
                $voterCount = rand(
                    (int) ($users->count() * 0.4),
                    (int) ($users->count() * 0.8),
                );

                $voters = $users->random(min($voterCount, $users->count()));
                $upvotes = 0;
                $downvotes = 0;

                foreach ($voters as $user) {
                    if ($user->id === $post->user_id) {
                        continue;
                    }

                    $voteValue = (rand(1, 100) <= 85) ? 1 : -1;

                    if ($voteValue > 0) {
                        $voteType = Vote::getValidPositiveTypes()[array_rand(Vote::getValidPositiveTypes())];
                    } else {
                        $voteType = Vote::getValidNegativeTypes()[array_rand(Vote::getValidNegativeTypes())];
                    }

                    Vote::create([
                        'user_id' => $user->id,
                        'votable_id' => $post->id,
                        'votable_type' => Post::class,
                        'value' => $voteValue,
                        'type' => $voteType,
                    ]);

                    if ($voteValue === 1) {
                        $upvotes++;
                    } else {
                        $downvotes++;
                    }

                    $totalPostVotes++;
                }

                $post->votes_count = $upvotes;
                $post->save();

                $this->command->info("Añadidos {$upvotes} votos positivos y {$downvotes} votos negativos al post: {$post->title}");
            }

            $this->command->info("Total de votos en posts: {$totalPostVotes}");
        }

        if (! $comments->isEmpty()) {
            $totalCommentVotes = 0;
            $typeCounts = array_fill_keys(
                array_merge(Vote::getValidPositiveTypes(), Vote::getValidNegativeTypes()),
                0,
            );

            foreach ($comments as $comment) {
                $voterCount = rand(
                    (int) ($users->count() * 0.2),
                    (int) ($users->count() * 0.5),
                );

                $voters = $users->random(min($voterCount, $users->count()));
                $upvotes = 0;
                $downvotes = 0;

                foreach ($voters as $user) {
                    if ($user->id === $comment->user_id) {
                        continue;
                    }

                    $voteValue = (rand(1, 100) <= 80) ? 1 : -1;

                    // Assign vote type with more realistic distribution
                    $voteType = null;
                    if ($voteValue > 0) {
                        // Custom distribution for positive types
                        $weights = [
                            'interesting' => 40,
                            'didactic' => 25,
                            'elaborate' => 20,
                            'funny' => 15,
                        ];
                        $voteType = $this->getWeightedRandomType($weights);
                    } else {
                        // Custom distribution for negative types
                        $weights = [
                            'irrelevant' => 40,
                            'incomplete' => 25,
                            'false' => 20,
                            'outofplace' => 15,
                        ];
                        $voteType = $this->getWeightedRandomType($weights);
                    }

                    Vote::create([
                        'user_id' => $user->id,
                        'votable_id' => $comment->id,
                        'votable_type' => Comment::class,
                        'value' => $voteValue,
                        'type' => $voteType,
                    ]);

                    // Count by type for statistics
                    $typeCounts[$voteType]++;

                    if ($voteValue === 1) {
                        $upvotes++;
                    } else {
                        $downvotes++;
                    }

                    $totalCommentVotes++;
                }

                $comment->votes_count = $upvotes;
                $comment->save();
            }

            $this->command->info("Total comment votes: {$totalCommentVotes}");
            $this->command->info('Vote type distribution:');
            foreach ($typeCounts as $type => $count) {
                $percentage = $totalCommentVotes > 0 ? round(($count / $totalCommentVotes) * 100, 2) : 0;
                $this->command->info("- {$type}: {$count} ({$percentage}%)");
            }
        }

        $this->command->info('Voting process completed successfully');
    }

    private function getWeightedRandomType(array $weights): string
    {
        $sum = array_sum($weights);
        $rand = mt_rand(1, $sum);

        foreach ($weights as $type => $weight) {
            $rand -= $weight;
            if ($rand <= 0) {
                return $type;
            }
        }

        // Fallback just in case
        return array_key_first($weights);
    }
}
