<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CommentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = Post::all();
        $users = User::all();

        if ($posts->isEmpty()) {
            $this->command->info('No posts available to create comments. Please run PostsSeeder first.');

            return;
        }

        if ($users->isEmpty()) {
            $this->command->info('No users available to create comments.');

            return;
        }

        $this->command->info('Creating sample comments...');

        $commentTemplates = $this->getCommentTemplates();

        foreach ($posts as $post) {

            $commentCount = rand(3, 15);
            $actualCommentCount = 0;

            for ($i = 0; $i < $commentCount; $i++) {
                $user = $users->random();

                while ($user->id === $post->user_id) {
                    $user = $users->random();
                }

                if (rand(0, 100) > 30) {
                    $content = $commentTemplates[array_rand($commentTemplates)];
                } else {
                    $content = $this->generateRandomComment();
                }

                $comment = Comment::create([
                    'content' => $content,
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                    'votes_count' => 0,
                ]);

                $actualCommentCount++;

                if (rand(0, 100) > 50) {
                    $replyCount = rand(1, 5);

                    for ($j = 0; $j < $replyCount; $j++) {
                        $replyUser = $users->random();

                        while ($replyUser->id === $user->id) {
                            $replyUser = $users->random();
                        }

                        if (rand(0, 100) > 50) {
                            $replyContent = "Reply to @{$user->username}: " . $commentTemplates[array_rand($commentTemplates)];
                        } else {
                            $replyContent = "Reply to @{$user->username}: " . $this->generateRandomComment();
                        }

                        Comment::create([
                            'content' => $replyContent,
                            'user_id' => $replyUser->id,
                            'post_id' => $post->id,
                            'parent_id' => $comment->id,
                            'votes_count' => 0,
                        ]);

                        $actualCommentCount++;
                    }
                }
            }

            $post->comment_count = $actualCommentCount;
            $post->save();

            $this->command->info("Added {$actualCommentCount} comments to post: {$post->title}");
        }
    }

    /**
     * Get comment templates to use in the seeder.
     */
    private function getCommentTemplates(): array
    {
        return [
            // Positive comments
            'I loved this article, very informative and well written.',
            'Great contribution to the community, thanks for sharing.',
            'This is exactly what I was looking for, thank you so much.',
            'Excellent explanation, I now understand the topic much better.',
            'Very interesting perspective, I hadn\'t seen it that way before.',
            'Incredible analysis, it has opened my eyes to new possibilities.',
            'Definitely one of the best posts I\'ve read lately.',
            'Thanks for sharing your knowledge so clearly.',
            'I found this information very useful, I\'ll put it into practice.',
            'Quality content like this is what makes this community great.',

            // Neutral comments/questions
            'Does anyone have more information on this?',
            'I\'d like to know more about this topic, are there any additional sources?',
            'What\'s your opinion on the long-term implications of this?',
            'Interesting, although I think there are more aspects to consider.',
            'I\'m not sure I fully understand, could you explain part X better?',
            'Is there an alternative to what you propose?',
            'How does this relate to the current situation in the sector?',
            'I have a question about part Y, could someone clarify?',
            'It\'s a good starting point, but what about edge cases?',
            'What would be the next step after implementing this?',

            // Technology comments
            'I\'ve implemented something similar in my project and it works great.',
            'This technology has a lot of potential, especially combined with AI.',
            'Has anyone tried alternatives like X or Y for this?',
            'The performance is excellent, but I miss some features.',
            'The documentation for this tool could use some improvement.',
            'I just tried this method and it solved a problem I\'d been trying to fix for weeks.',
            'Interesting approach, but in production it could have scalability issues.',
            'In my experience, combining this with microservices gives surprising results.',
            'Is there a library that facilitates implementing this technique?',
            'This paradigm is gaining a lot of traction in the current ecosystem.',

            // Scientific comments
            'This discovery could completely change our understanding of the topic.',
            'I wonder how this will affect current research in the field.',
            'The data presented is convincing, but I\'d like to see more studies.',
            'What are the ethical implications of this advancement?',
            'Fascinating finding, I\'ll be watching for future developments.',
            'The methodology used is rigorous, which gives the results a lot of credibility.',
            'I\'d like to see how this experiment replicates under different conditions.',
            'This study contradicts what was previously thought, very interesting.',
            'The intersection of these two disciplines is generating incredible advances.',
            'The study sample seems small, will it be enough to generalize?',
        ];
    }

    /**
     * Generate a random comment.
     */
    private function generateRandomComment(): string
    {
        $openings = [
            'I think that', 'I believe that', 'In my opinion,', 'From my point of view,',
            'I consider that', 'I\'ve observed that', 'I\'ve noticed that', 'It\'s interesting that',
            'I agree that', 'I\'m not sure if', 'I wonder if', 'It\'s fascinating how',
            'We must acknowledge that', 'There\'s no doubt that', 'It\'s clear that', 'It\'s evident that',
            'I\'m surprised that', 'It\'s curious that', 'I must say that', 'I have to admit that',
        ];

        $middles = [
            'this topic is very relevant', 'this information is valuable', 'this approach is appropriate',
            'this analysis is insightful', 'this article addresses well', 'this concept is important',
            'this perspective is interesting', 'this explanation clarifies many doubts',
            'this content is well documented', 'this view contributes a lot to the debate',
            'this post generates good reflections', 'this discussion is necessary',
            'these ideas are innovative', 'this data is revealing', 'this case is a good example',
            'this problem affects many', 'this solution is practical', 'this method is efficient',
            'this technology has a future', 'this field is evolving rapidly',
        ];

        $closings = [
            'in the current context.', 'for all those interested.', 'in today\'s world.',
            'for our future.', 'in our society.', 'for professionals in the sector.',
            'in this digital era.', 'to understand the full picture.',
            'in practical terms.', 'for those seeking solutions.',
            'from a global perspective.', 'considering current trends.',
            'if we value innovation.', 'in today\'s competitive environment.',
            'given the circumstances.', 'with the available resources.',
            'in my experience.', 'based on what I\'ve observed.',
            'judging by the results.', 'looking at the data presented.',
        ];

        $opening = $openings[array_rand($openings)];
        $middle = $middles[array_rand($middles)];
        $closing = $closings[array_rand($closings)];

        return "{$opening} {$middle} {$closing}";
    }
}
