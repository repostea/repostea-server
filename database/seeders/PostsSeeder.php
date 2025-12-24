<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PostsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('No hay usuarios disponibles para crear posts. Creando un usuario administrador...');

            $admin = User::factory()->create([
                'name' => 'Admin',
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'password' => bcrypt(env('ADMIN_PASSWORD', 'changeme123')),
                'karma_points' => 5000,
                'locale' => 'es',
                'email_verified_at' => now(),
            ]);

            $users = collect([$admin]);
        }

        $this->command->info('Creando posts de ejemplo...');
        $examplePosts = $this->getExamplePosts();

        foreach ($examplePosts as $postData) {
            $user = $users->random();

            // Set content_type based on type
            if (! isset($postData['content_type'])) {
                $postData['content_type'] = ($postData['type'] === 'article') ? 'text' : 'link';
            }

            $post = Post::create(array_merge($postData, [
                'user_id' => $user->id,
                'views' => rand(5, 500),
                'language_code' => 'es',
                'status' => 'published',
                'votes_count' => 0,
                'comment_count' => 0,
            ]));
            $this->command->info("Post creado: {$post->title}");
        }

        $users->each(function ($user): void {
            $numPosts = rand(1, 5);
            for ($i = 0; $i < $numPosts; $i++) {
                $type = (rand(1, 100) <= 60) ? 'article' : 'link';
                $isOriginal = ($type === 'article') ? true : false;
                $contentType = ($type === 'article') ? 'text' : 'link';

                $post = Post::create([
                    'title' => $this->generateTitle($type),
                    'content' => $this->generateContent($type),
                    'url' => ($type === 'link') ? $this->generateUrl() : null,
                    'thumbnail_url' => (rand(1, 100) <= 70) ? $this->generateThumbnailUrl() : null,
                    'user_id' => $user->id,
                    'type' => $type,
                    'content_type' => $contentType,
                    'is_original' => $isOriginal,
                    'status' => 'published',
                    'votes_count' => 0,
                    'comment_count' => 0,
                    'views' => rand(5, 1000),
                    'language_code' => 'es',
                ]);

                $this->assignRandomTags($post);

                $this->command->info("Post aleatorio creado: {$post->title}");
            }
        });
    }

    private function assignRandomTags(Post $post): void
    {
        $tags = Tag::inRandomOrder()->take(rand(1, 3))->get();
        $post->tags()->sync($tags->pluck('id'));
    }

    private function getExamplePosts(): array
    {
        $disclaimer = "[Este contenido es solo un ejemplo. Será eliminado cuando el desarrollo del sitio finalice y pasemos a producción.]\n\n";

        return [
            [
                'title' => 'Bienvenidos a Renegados: La nueva plataforma de contenido en español',
                'content' => $disclaimer . "Nos complace presentar Renegados, una plataforma diseñada para compartir, descubrir y discutir contenido relevante en español...\n\nEn Renegados valoramos la calidad sobre la cantidad...",
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'Guía para principiantes: Cómo acumular karma en Renegados',
                'content' => $disclaimer . "¿Eres nuevo en Renegados? ¡Aprende cómo funciona nuestro sistema de karma!\n\n1. **Publicaciones de calidad**...",
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'La inteligencia artificial revoluciona el desarrollo de software',
                'content' => $disclaimer . 'La integración de la inteligencia artificial en el desarrollo de software está transformando la forma en que programamos...',
                'type' => 'article',
                'is_original' => true,
            ],
            [
                'title' => 'El James Webb descubre atmósfera en exoplaneta potencialmente habitable',
                'url' => 'https://www.example.com/james-webb-exoplaneta',
                'content' => $disclaimer . 'Científicos de la NASA han anunciado un importante descubrimiento gracias al telescopio espacial James Webb.',
                'type' => 'link',
                'is_original' => false,
            ],
            [
                'title' => 'Tutorial: Crea tu primera aplicación web con Laravel 10',
                'url' => 'https://www.example.com/tutorial-laravel',
                'content' => $disclaimer . 'En este tutorial aprenderás a crear una aplicación completa utilizando el framework Laravel 10.',
                'type' => 'link',
                'is_original' => false,
            ],
            [
                'title' => 'Los mejores libros de ciencia ficción de la última década',
                'content' => $disclaimer . 'La última década ha sido extraordinaria para la ciencia ficción literaria. Aquí te presento mi lista personal de los mejores libros...',
                'type' => 'article',
                'is_original' => true,
            ],
        ];
    }

    /**
     * Generar un título aleatorio para un post.
     */
    private function generateTitle(string $type): string
    {
        $articleTitles = [
            'Las 10 tendencias tecnológicas que dominarán el próximo año',
            'Cómo mejorar tu productividad con técnicas de gestión del tiempo',
            'Análisis: El impacto de la inteligencia artificial en nuestra sociedad',
            'Guía completa para aprender programación desde cero',
            'Los mejores destinos para viajar en 2025',
            'La importancia de la educación financiera en tiempos de crisis',
            'Reseña: El último libro de Gabriel García Márquez',
            'La revolución sostenible: Cómo reducir tu huella ecológica',
            'El futuro del trabajo: Tendencias y predicciones',
            'Recetas saludables para una dieta equilibrada',
        ];

        $linkTitles = [
            'Científicos descubren un nuevo método para tratar el cáncer',
            'Apple presenta su nuevo dispositivo revolucionario',
            'El cambio climático alcanza un punto de no retorno, según estudio',
            'Nuevas regulaciones tecnológicas entrarán en vigor el próximo mes',
            'Récord histórico en la bolsa tras anuncio económico',
            'Filtradas las especificaciones del próximo iPhone',
            'La NASA anuncia misión tripulada a Marte para 2030',
            'Gran descubrimiento arqueológico revela antigua civilización',
            'Estudio revela beneficios inesperados del ejercicio moderado',
            'El gobierno aprueba nueva ley de protección de datos',
        ];

        $titles = ($type === 'article') ? $articleTitles : $linkTitles;

        return $titles[array_rand($titles)];
    }

    /**
     * Generar contenido aleatorio para un post.
     */
    private function generateContent(string $type): string
    {
        if ($type === 'article') {
            $paragraphs = rand(3, 7);
            $content = '';

            for ($i = 0; $i < $paragraphs; $i++) {
                $sentences = rand(3, 8);
                $paragraph = '';

                for ($j = 0; $j < $sentences; $j++) {
                    $wordCount = rand(8, 15);
                    $sentence = ucfirst($this->generateRandomWords($wordCount)) . '. ';
                    $paragraph .= $sentence;
                }

                $content .= $paragraph . "\n\n";
            }

            return trim($content);
        }
        // For links, just a short summary
        $sentences = rand(1, 3);
        $content = '';

        for ($i = 0; $i < $sentences; $i++) {
            $wordCount = rand(8, 15);
            $sentence = ucfirst($this->generateRandomWords($wordCount)) . '. ';
            $content .= $sentence;
        }

        return trim($content);

    }

    /**
     * Generar palabras aleatorias.
     */
    private function generateRandomWords(int $count): string
    {
        $words = [
            'tecnología', 'desarrollo', 'innovación', 'ciencia', 'investigación',
            'economía', 'sociedad', 'futuro', 'análisis', 'estudio', 'tiempo',
            'programación', 'inteligencia', 'artificial', 'datos', 'seguridad',
            'aplicación', 'sistema', 'usuario', 'diseño', 'proyecto', 'empresa',
            'industria', 'cambio', 'proceso', 'producto', 'servicio', 'cliente',
            'mercado', 'negocio', 'estrategia', 'crecimiento', 'inversión', 'éxito',
            'global', 'digital', 'moderno', 'eficiente', 'sostenible', 'innovador',
            'creativo', 'profesional', 'experto', 'avanzado', 'importante', 'esencial',
            'fundamental', 'crítico', 'necesario', 'posible', 'probable', 'potencial',
            'actual', 'reciente', 'nuevo', 'último', 'mejor', 'bueno', 'gran', 'alto',
            'los', 'las', 'una', 'unos', 'para', 'con', 'por', 'entre', 'sobre', 'desde',
            'hasta', 'según', 'como', 'cuando', 'donde', 'porque', 'aunque', 'si', 'pero',
        ];

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = $words[array_rand($words)];
        }

        return implode(' ', $result);
    }

    /**
     * Generar una URL de ejemplo.
     */
    private function generateUrl(): string
    {
        $domains = [
            'ejemplo.com', 'noticias.es', 'tecnologia.info', 'ciencia.org',
            'innovacion.net', 'economia.com', 'cultura.es', 'actualidad.info',
        ];

        $paths = [
            'articulo', 'noticia', 'analisis', 'estudio', 'reporte', 'entrevista',
            'opinion', 'resumen', 'guia', 'tutorial', 'investigacion', 'descubrimiento',
        ];

        $domain = $domains[array_rand($domains)];
        $path = $paths[array_rand($paths)];
        $id = rand(1000, 9999);

        return "https://www.{$domain}/{$path}/{$id}";
    }

    /**
     * Generar una URL de imagen de thumbnail.
     */
    private function generateThumbnailUrl(): string
    {
        $width = 480;
        $height = 320;
        $randomId = rand(1, 1000);

        return "https://picsum.photos/id/{$randomId}/{$width}/{$height}";
    }
}
