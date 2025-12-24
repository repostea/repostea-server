<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

final class FaqController extends Controller
{
    public function index()
    {
        $faqs = [
            [
                'category' => 'karma',
                'question' => 'faq.karma.what_is.question',
                'answer' => 'faq.karma.what_is.answer',
                'order' => 1,
            ],
            [
                'category' => 'karma',
                'question' => 'faq.karma.how_to_earn.question',
                'answer' => 'faq.karma.how_to_earn.answer',
                'order' => 2,
            ],
            [
                'category' => 'seals',
                'question' => 'faq.seals.what_are.question',
                'answer' => 'faq.seals.what_are.answer',
                'order' => 1,
            ],
            [
                'category' => 'seals',
                'question' => 'faq.seals.how_to_use.question',
                'answer' => 'faq.seals.how_to_use.answer',
                'order' => 2,
            ],
            [
                'category' => 'voting',
                'question' => 'faq.voting.types.question',
                'answer' => 'faq.voting.types.answer',
                'order' => 1,
            ],
            [
                'category' => 'voting',
                'question' => 'faq.voting.comments.question',
                'answer' => 'faq.voting.comments.answer',
                'order' => 2,
            ],
            [
                'category' => 'posts',
                'question' => 'faq.posts.types.question',
                'answer' => 'faq.posts.types.answer',
                'order' => 1,
            ],
            [
                'category' => 'posts',
                'question' => 'faq.posts.anonymous.question',
                'answer' => 'faq.posts.anonymous.answer',
                'order' => 2,
            ],
            [
                'category' => 'lists',
                'question' => 'faq.lists.favorites.question',
                'answer' => 'faq.lists.favorites.answer',
                'order' => 1,
            ],
            [
                'category' => 'lists',
                'question' => 'faq.lists.custom.question',
                'answer' => 'faq.lists.custom.answer',
                'order' => 2,
            ],
            [
                'category' => 'lists',
                'question' => 'faq.lists.public.question',
                'answer' => 'faq.lists.public.answer',
                'order' => 3,
            ],
            [
                'category' => 'community',
                'question' => 'faq.community.subs.question',
                'answer' => 'faq.community.subs.answer',
                'order' => 1,
            ],
            [
                'category' => 'community',
                'question' => 'faq.community.moderation.question',
                'answer' => 'faq.community.moderation.answer',
                'order' => 2,
            ],
            [
                'category' => 'community',
                'question' => 'faq.community.rules.question',
                'answer' => 'faq.community.rules.answer',
                'order' => 3,
            ],
            [
                'category' => 'account',
                'question' => 'faq.account.privacy.question',
                'answer' => 'faq.account.privacy.answer',
                'order' => 1,
            ],
            [
                'category' => 'account',
                'question' => 'faq.account.delete.question',
                'answer' => 'faq.account.delete.answer',
                'order' => 2,
            ],
            [
                'category' => 'account',
                'question' => 'faq.account.notifications.question',
                'answer' => 'faq.account.notifications.answer',
                'order' => 3,
            ],
            [
                'category' => 'relationships',
                'question' => 'faq.relationships.what_are.question',
                'answer' => 'faq.relationships.what_are.answer',
                'order' => 1,
            ],
            [
                'category' => 'relationships',
                'question' => 'faq.relationships.types.question',
                'answer' => 'faq.relationships.types.answer',
                'order' => 2,
            ],
            [
                'category' => 'search',
                'question' => 'faq.search.how_to.question',
                'answer' => 'faq.search.how_to.answer',
                'order' => 1,
            ],
            [
                'category' => 'search',
                'question' => 'faq.search.filters.question',
                'answer' => 'faq.search.filters.answer',
                'order' => 2,
            ],
        ];

        // Translate all questions and answers
        $translatedFaqs = array_map(fn ($faq) => [
            'category' => $faq['category'],
            'question' => __($faq['question']),
            'answer' => __($faq['answer']),
            'order' => $faq['order'],
        ], $faqs);

        // Group by category
        $grouped = [];
        foreach ($translatedFaqs as $faq) {
            $category = $faq['category'];
            if (! isset($grouped[$category])) {
                $grouped[$category] = [
                    'category' => $category,
                    'name' => __("faq.categories.{$category}"),
                    'items' => [],
                ];
            }
            $grouped[$category]['items'][] = [
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'order' => $faq['order'],
            ];
        }

        // Sort items within each category by order
        foreach ($grouped as &$category) {
            usort($category['items'], fn ($a, $b) => $a['order'] <=> $b['order']);
        }

        return response()->json([
            'data' => array_values($grouped),
        ]);
    }
}
