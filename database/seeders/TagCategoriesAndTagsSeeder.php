<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TagCategoriesAndTagsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name_key' => 'science', 'icon' => 'fas fa-atom'],
            ['name_key' => 'philosophy', 'icon' => 'fas fa-brain'],
            ['name_key' => 'art_culture', 'icon' => 'fas fa-palette'],
            ['name_key' => 'history', 'icon' => 'fas fa-book-open'],
            ['name_key' => 'technology', 'icon' => 'fas fa-microchip'],
            ['name_key' => 'politics_society', 'icon' => 'fas fa-landmark'],
            ['name_key' => 'economy', 'icon' => 'fas fa-chart-line'],
            ['name_key' => 'psychology', 'icon' => 'fas fa-user-graduate'],
        ];

        $categoriesInsert = [];
        foreach ($categories as $category) {
            $categoriesInsert[] = [
                'name_key' => $category['name_key'],
                'slug' => $category['name_key'],
                'icon' => $category['icon'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('tag_categories')->insertOrIgnore($categoriesInsert);

        $tagsByCategory = [
            'science' => [
                'physics', 'astronomy', 'biology', 'chemistry', 'mathematics',
                'neuroscience', 'medicine', 'ecology', 'geology', 'anthropology',
            ],
            'philosophy' => [
                'ethics', 'epistemology', 'metaphysics', 'existentialism',
                'political_philosophy', 'aesthetics', 'analytical_philosophy',
                'continental_philosophy', 'logic', 'philosophy_history',
            ],
            'art_culture' => [
                'literature', 'painting', 'classical_music', 'architecture',
                'art_cinema', 'theater', 'sculpture', 'art_photography',
                'literary_criticism', 'art_history',
            ],
            'history' => [
                'ancient_history', 'medieval_history', 'modern_history',
                'contemporary_history', 'archaeology', 'ancient_civilizations',
                'social_history', 'political_history', 'cultural_history',
            ],
            'technology' => [
                'artificial_intelligence', 'quantum_computing', 'blockchain',
                'biotechnology', 'nanotechnology', 'robotics', 'cybersecurity',
                'virtual_reality', 'genetic_engineering', 'renewable_energy',
            ],
            'politics_society' => [
                'political_theory', 'geopolitics', 'international_relations',
                'public_policy', 'sociology', 'social_anthropology', 'demography',
                'gender_studies', 'human_rights', 'cultural_studies',
            ],
            'economy' => [
                'macroeconomics', 'microeconomics', 'finance', 'behavioral_economics',
                'economic_development', 'political_economy', 'economic_history',
                'international_economics', 'environmental_economics', 'economic_theory',
            ],
            'psychology' => [
                'cognitive_psychology', 'neuropsychology', 'psychoanalysis',
                'evolutionary_psychology', 'social_psychology', 'clinical_psychology',
                'behavioral_psychology', 'positive_psychology', 'humanistic_psychology',
                'psychology_history',
            ],
        ];

        // Insert tags
        $tagsToInsert = [];

        foreach ($tagsByCategory as $categoryNameKey => $tags) {
            $categoryId = DB::table('tag_categories')
                ->where('name_key', $categoryNameKey)
                ->value('id');

            foreach ($tags as $nameKey) {
                $tagsToInsert[] = [
                    'name_key' => $nameKey,
                    'slug' => $nameKey,
                    'tag_category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('tags')->insertOrIgnore($tagsToInsert);
    }
}
