<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AggregationSource;
use Illuminate\Database\Seeder;

final class AggregationSourcesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Configuration for Spanish aggregation sources
        $spanishSources = [
            [
                'name' => 'Menéame',
                'url' => 'https://www.meneame.net',
                'type' => 'api',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.meneame.net/api/list.php?status=published',
                    'language' => 'es',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Mediavida',
                'url' => 'https://www.mediavida.com',
                'type' => 'scrape',
                'config' => json_encode([
                    'api_endpoint' => null, // Would require scraping
                    'language' => 'es',
                    'update_frequency' => 60,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'El País',
                'url' => 'https://elpais.com',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://feeds.elpais.com/mrss-s/pages/ep/site/elpais.com/portada',
                    'language' => 'es',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'El Diario',
                'url' => 'https://www.eldiario.es',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.eldiario.es/rss/',
                    'language' => 'es',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
        ];

        // Configuration for English aggregation sources
        $englishSources = [
            [
                'name' => 'Hacker News',
                'url' => 'https://news.ycombinator.com',
                'type' => 'api',
                'config' => json_encode([
                    'api_endpoint' => 'https://hacker-news.firebaseio.com/v0/topstories.json',
                    'language' => 'en',
                    'update_frequency' => 15,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Reddit',
                'url' => 'https://www.reddit.com',
                'type' => 'api',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.reddit.com/r/all/top.json?limit=25',
                    'language' => 'en',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'BBC News',
                'url' => 'https://www.bbc.com/news',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'http://feeds.bbci.co.uk/news/rss.xml',
                    'language' => 'en',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
        ];

        // Configuration for French aggregation sources
        $frenchSources = [
            [
                'name' => 'Le Monde',
                'url' => 'https://www.lemonde.fr',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.lemonde.fr/rss/une.xml',
                    'language' => 'fr',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Framablog',
                'url' => 'https://framablog.org',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://framablog.org/feed/',
                    'language' => 'fr',
                    'update_frequency' => 60,
                ]),
                'is_active' => true,
            ],
        ];

        // Configuration for German aggregation sources
        $germanSources = [
            [
                'name' => 'Der Spiegel',
                'url' => 'https://www.spiegel.de',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.spiegel.de/schlagzeilen/index.rss',
                    'language' => 'de',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Heise Online',
                'url' => 'https://www.heise.de',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://www.heise.de/rss/heise.rdf',
                    'language' => 'de',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
        ];

        // Configuration for Portuguese aggregation sources
        $portugueseSources = [
            [
                'name' => 'Público',
                'url' => 'https://www.publico.pt',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://feeds.publico.pt/noticia/rss',
                    'language' => 'pt',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
            [
                'name' => 'Folha de São Paulo',
                'url' => 'https://www.folha.uol.com.br',
                'type' => 'rss',
                'config' => json_encode([
                    'api_endpoint' => 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml',
                    'language' => 'pt',
                    'update_frequency' => 30,
                ]),
                'is_active' => true,
            ],
        ];

        // Combine all sources
        $allSources = array_merge(
            $spanishSources,
            $englishSources,
            $frenchSources,
            $germanSources,
            $portugueseSources,
        );

        // Create records in database
        foreach ($allSources as $source) {
            AggregationSource::create($source);
        }
    }
}
