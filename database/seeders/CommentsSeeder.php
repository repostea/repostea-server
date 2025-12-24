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
        // Get posts and users
        $posts = Post::all();
        $users = User::all();

        if ($posts->isEmpty()) {
            $this->command->info('No hay posts disponibles para crear comentarios. Por favor, ejecuta PostsSeeder primero.');

            return;
        }

        if ($users->isEmpty()) {
            $this->command->info('No hay usuarios disponibles para crear comentarios.');

            return;
        }

        $this->command->info('Creando comentarios de ejemplo...');

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
                            $replyContent = "Respuesta a @{$user->username}: " . $commentTemplates[array_rand($commentTemplates)];
                        } else {
                            $replyContent = "Respuesta a @{$user->username}: " . $this->generateRandomComment();
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

            $this->command->info("Añadidos {$actualCommentCount} comentarios al post: {$post->title}");
        }
    }

    /**
     * Get comment templates to use in the seeder.
     */
    private function getCommentTemplates(): array
    {
        return [
            // Positive comments
            'Me encantó este artículo, muy informativo y bien escrito.',
            'Gran aporte a la comunidad, gracias por compartir.',
            'Esto es exactamente lo que estaba buscando, muchas gracias.',
            'Excelente explicación, ahora entiendo mucho mejor el tema.',
            'Muy interesante perspectiva, no lo había visto así antes.',
            'Increíble análisis, me ha abierto los ojos a nuevas posibilidades.',
            'Sin duda uno de los mejores posts que he leído últimamente.',
            'Gracias por compartir tu conocimiento de manera tan clara.',
            'Me ha resultado muy útil esta información, la pondré en práctica.',
            'Contenido de calidad como este es lo que hace grande a esta comunidad.',

            // Comentarios neutros/preguntas
            '¿Alguien tiene más información sobre esto?',
            'Me gustaría saber más sobre este tema, ¿hay alguna fuente adicional?',
            '¿Cuál es tu opinión sobre las implicaciones de esto a largo plazo?',
            'Interesante, aunque creo que hay más aspectos que considerar.',
            'No estoy seguro de entender completamente, ¿podrías explicar mejor la parte X?',
            '¿Existe alguna alternativa a lo que propones?',
            '¿Cómo se relaciona esto con la situación actual en el sector?',
            'Me quedé con una duda sobre la parte Y, ¿alguien podría aclararla?',
            'Es un buen punto de partida, pero ¿qué pasa con los casos excepcionales?',
            '¿Cuál sería el siguiente paso después de implementar esto?',

            // Technology comments
            'He implementado algo similar en mi proyecto y funciona muy bien.',
            'Esta tecnología tiene mucho potencial, especialmente combinada con IA.',
            '¿Alguien ha probado alternativas como X o Y para esto?',
            'El rendimiento es excelente, pero echo de menos algunas características.',
            'La documentación de esta herramienta podría mejorar bastante.',
            'Acabo de probar este método y resolvió un problema que llevaba semanas intentando solucionar.',
            'Interesante enfoque, pero en producción podría dar problemas de escalabilidad.',
            'Por mi experiencia, combinar esto con microservicios da resultados sorprendentes.',
            '¿Hay alguna biblioteca que facilite la implementación de esta técnica?',
            'Este paradigma está ganando mucha tracción en el ecosistema actual.',

            // Scientific comments
            'Este descubrimiento podría cambiar completamente nuestro entendimiento del tema.',
            'Me pregunto cómo afectará esto a las investigaciones actuales en el campo.',
            'Los datos presentados son convincentes, pero me gustaría ver más estudios.',
            '¿Cuáles son las implicaciones éticas de este avance?',
            'Fascinante hallazgo, estaré atento a los desarrollos futuros.',
            'La metodología utilizada es rigurosa, lo que da mucha credibilidad a los resultados.',
            'Me gustaría ver cómo se replica este experimento en diferentes condiciones.',
            'Este estudio contradice lo que se pensaba hasta ahora, muy interesante.',
            'La intersección de estas dos disciplinas está generando avances increíbles.',
            'La muestra del estudio parece pequeña, ¿será suficiente para generalizar?',
        ];
    }

    /**
     * Generate a random comment.
     */
    private function generateRandomComment(): string
    {
        $openings = [
            'Me parece que', 'Creo que', 'En mi opinión,', 'Desde mi punto de vista,',
            'Considero que', 'He observado que', 'He notado que', 'Es interesante que',
            'Coincido en que', 'No estoy seguro si', 'Me pregunto si', 'Es fascinante cómo',
            'Hay que reconocer que', 'No cabe duda de que', 'Está claro que', 'Es evidente que',
            'Me sorprende que', 'Es curioso que', 'Debo decir que', 'Tengo que admitir que',
        ];

        $middles = [
            'este tema es muy relevante', 'esta información es valiosa', 'este enfoque es acertado',
            'este análisis es profundo', 'este artículo aborda bien', 'este concepto es importante',
            'esta perspectiva es interesante', 'esta explicación clarifica muchas dudas',
            'este contenido está bien documentado', 'esta visión aporta mucho al debate',
            'este post genera buenas reflexiones', 'esta discusión es necesaria',
            'estas ideas son innovadoras', 'estos datos son reveladores', 'este caso es un buen ejemplo',
            'este problema afecta a muchos', 'esta solución es práctica', 'este método es eficiente',
            'esta tecnología tiene futuro', 'este campo está evolucionando rápidamente',
        ];

        $closings = [
            'en el contexto actual.', 'para todos los interesados.', 'en el mundo de hoy.',
            'para nuestro futuro.', 'en nuestra sociedad.', 'para profesionales del sector.',
            'en esta era digital.', 'para comprender el panorama completo.',
            'en términos prácticos.', 'para quienes buscan soluciones.',
            'desde una perspectiva global.', 'considerando las tendencias actuales.',
            'si valoramos la innovación.', 'en el entorno competitivo actual.',
            'dadas las circunstancias.', 'con los recursos disponibles.',
            'según mi experiencia.', 'basado en lo que he observado.',
            'a juzgar por los resultados.', 'viendo los datos presentados.',
        ];

        $opening = $openings[array_rand($openings)];
        $middle = $middles[array_rand($middles)];
        $closing = $closings[array_rand($closings)];

        return "{$opening} {$middle} {$closing}";
    }
}
