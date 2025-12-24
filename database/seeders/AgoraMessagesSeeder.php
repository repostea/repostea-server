<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AgoraMessage;
use App\Models\AgoraVote;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AgoraMessagesSeeder extends Seeder
{
    private array $users;

    private int $totalMessages = 0;

    private int $totalVotes = 0;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->users = User::all()->toArray();

        if (empty($this->users)) {
            $this->command->info('No hay usuarios disponibles. Por favor, crea usuarios primero.');

            return;
        }

        $this->command->info('🏛️  Creando mensajes en el Ágora...');
        $this->command->info('Usuarios disponibles: ' . count($this->users));

        // Create main messages (threads)
        $topLevelCount = 200; // 200 main threads
        $bar = $this->command->getOutput()->createProgressBar($topLevelCount);
        $bar->start();

        for ($i = 0; $i < $topLevelCount; $i++) {
            $this->createThread();
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine(2);

        $this->command->info("✅ Creados {$this->totalMessages} mensajes en el Ágora");
        $this->command->info("✅ Creados {$this->totalVotes} votos");
    }

    /**
     * Create a complete thread with main message and replies.
     */
    private function createThread(): void
    {
        $user = $this->getRandomUser();
        $isAnonymous = rand(0, 100) < 15; // 15% anonymous

        // Main message
        $message = AgoraMessage::create([
            'user_id' => $user['id'],
            'parent_id' => null,
            'content' => $this->generateMainMessage(),
            'votes_count' => 0,
            'replies_count' => 0,
            'is_anonymous' => $isAnonymous,
            'language_code' => $this->getRandomLanguage(),
            'created_at' => $this->getRandomDate(),
        ]);
        $this->totalMessages++;

        // Add votes to main message
        $this->addVotes($message, rand(0, 50));

        // Create replies (between 0 and 30 replies per thread)
        $replyCount = $this->getWeightedReplyCount();
        $this->createReplies($message, $replyCount, 0);

        // Update replies count
        $message->updateRepliesCount();
    }

    /**
     * Create replies recursively (up to 3 levels deep).
     */
    private function createReplies(AgoraMessage $parent, int $count, int $depth): void
    {
        if ($depth > 3 || $count === 0) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getRandomUser();
            $isAnonymous = rand(0, 100) < 10; // 10% anonymous in replies

            // Avoid user replying to themselves (sometimes)
            if (! $isAnonymous && rand(0, 100) > 20) {
                while ($user['id'] === $parent->user_id) {
                    $user = $this->getRandomUser();
                }
            }

            $reply = AgoraMessage::create([
                'user_id' => $user['id'],
                'parent_id' => $parent->id,
                'content' => $this->generateReply($parent, $depth),
                'votes_count' => 0,
                'replies_count' => 0,
                'is_anonymous' => $isAnonymous,
                'language_code' => $parent->language_code,
                'created_at' => $this->getRandomDateAfter($parent->created_at),
            ]);
            $this->totalMessages++;

            // Add votes
            $this->addVotes($reply, rand(0, 20));

            // Sub-replies (probability decreases with depth)
            if (rand(0, 100) < (50 - ($depth * 15))) {
                $subReplyCount = rand(0, max(1, 5 - $depth));
                $this->createReplies($reply, $subReplyCount, $depth + 1);
            }

            $reply->updateRepliesCount();
        }
    }

    /**
     * Add votes to a message.
     */
    private function addVotes(AgoraMessage $message, int $count): void
    {
        $votedUserIds = [];
        $voteTypes = ['informative', 'funny', 'interesting', 'agree', 'disagree', 'offtopic'];

        for ($i = 0; $i < $count; $i++) {
            $user = $this->getRandomUser();

            // Avoid duplicate votes from same user
            if (in_array($user['id'], $votedUserIds)) {
                continue;
            }
            $votedUserIds[] = $user['id'];

            // Determine vote value (80% positive, 20% negative)
            $value = rand(0, 100) < 80 ? 1 : -1;

            // Vote type based on value
            if ($value === 1) {
                $type = $voteTypes[rand(0, 3)]; // informative, funny, interesting, agree
            } else {
                $type = $voteTypes[rand(4, 5)]; // disagree, offtopic
            }

            AgoraVote::create([
                'agora_message_id' => $message->id,
                'user_id' => $user['id'],
                'value' => $value,
                'vote_type' => $type,
            ]);
            $this->totalVotes++;
        }

        $message->updateVotesCount();
    }

    /**
     * Get random user.
     */
    private function getRandomUser(): array
    {
        return $this->users[array_rand($this->users)];
    }

    /**
     * Get random language (prioritizing Spanish).
     */
    private function getRandomLanguage(): string
    {
        $languages = ['es', 'es', 'es', 'es', 'ca', 'eu', 'gl', 'en'];

        return $languages[array_rand($languages)];
    }

    /**
     * Get weighted reply count distribution.
     */
    private function getWeightedReplyCount(): int
    {
        $rand = rand(0, 100);
        if ($rand < 20) {
            return 0;
        }        // 20% no replies
        if ($rand < 50) {
            return rand(1, 3);
        }  // 30% few replies
        if ($rand < 80) {
            return rand(4, 10);
        } // 30% moderate replies
        if ($rand < 95) {
            return rand(11, 20);
        } // 15% many replies

        return rand(21, 40);              // 5% very active threads
    }

    /**
     * Generate random date within the last 6 months.
     */
    private function getRandomDate(): string
    {
        $start = strtotime('-6 months');
        $end = time();
        $timestamp = rand($start, $end);

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Generate date after a given date.
     */
    private function getRandomDateAfter($after): string
    {
        // Convert Carbon to timestamp if needed
        if ($after instanceof \Illuminate\Support\Carbon) {
            $afterTimestamp = $after->timestamp;
        } else {
            $afterTimestamp = strtotime($after);
        }

        $start = $afterTimestamp + rand(60, 86400 * 7); // Between 1 min and 1 week later
        $end = min($start + (86400 * 30), time()); // Maximum 1 month later or now
        if ($start > $end) {
            $start = $end - 3600;
        }
        $timestamp = rand($start, $end);

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Generate main message.
     */
    private function generateMainMessage(): string
    {
        $templates = $this->getMainMessageTemplates();
        $template = $templates[array_rand($templates)];

        // Sometimes add markdown formatting
        if (rand(0, 100) < 30) {
            $template = $this->addMarkdownFormatting($template);
        }

        return $template;
    }

    /**
     * Generate reply.
     */
    private function generateReply(AgoraMessage $parent, int $depth): string
    {
        $templates = $this->getReplyTemplates();
        $template = $templates[array_rand($templates)];

        // Mention parent message author (sometimes)
        if (! $parent->is_anonymous && rand(0, 100) < 40) {
            $parentUser = collect($this->users)->firstWhere('id', $parent->user_id);
            if ($parentUser) {
                $template = "@{$parentUser['username']} " . $template;
            }
        }

        return $template;
    }

    /**
     * Add random markdown formatting.
     */
    private function addMarkdownFormatting(string $text): string
    {
        $formats = [
            fn ($t) => "**{$t}**", // Bold
            fn ($t) => "*{$t}*",   // Italic
            fn ($t) => "> {$t}",   // Quote
            fn ($t) => "- {$t}",   // List
            fn ($t) => $t . "\n\n¿Qué opináis?",
            fn ($t) => "## Reflexión\n\n{$t}",
        ];

        $format = $formats[array_rand($formats)];

        return $format($text);
    }

    /**
     * Main message templates.
     */
    private function getMainMessageTemplates(): array
    {
        return [
            // Opinions and reflections
            'Últimamente he estado pensando mucho sobre cómo las redes sociales afectan nuestra forma de comunicarnos. ¿Creéis que hemos perdido la capacidad de tener conversaciones profundas?',
            'Me parece fascinante cómo la tecnología ha cambiado la forma en que trabajamos. Hace 10 años no podríamos haber imaginado el teletrabajo como lo conocemos hoy.',
            '¿Alguien más piensa que estamos viviendo una época de cambios sin precedentes? Entre la IA, el cambio climático y los movimientos sociales, parece que todo está en transformación.',
            'Acabo de terminar de leer un libro increíble sobre filosofía estoica. Me ha hecho reflexionar sobre cómo gestionamos nuestras emociones en el día a día.',
            'La educación necesita una reforma urgente. Los métodos de hace 50 años ya no sirven para preparar a los jóvenes para el mundo actual.',
            'He notado que cada vez hay más polarización en las discusiones online. ¿Qué podemos hacer para fomentar un debate más constructivo?',
            "El concepto de 'éxito' ha cambiado mucho para las nuevas generaciones. Ya no se trata solo de dinero y estatus, sino de bienestar y propósito.",
            'Me pregunto si las ciudades del futuro serán más sostenibles o si seguiremos por el mismo camino de consumo desmedido.',
            'La privacidad digital es un tema que me preocupa cada vez más. ¿Cuánto estamos dispuestos a sacrificar por comodidad?',
            'Creo que la creatividad es una de las habilidades más importantes para el futuro. La IA puede automatizar muchas cosas, pero no la originalidad humana.',

            // Open questions
            '¿Cuál creéis que será el próximo gran avance tecnológico que cambiará nuestras vidas?',
            '¿Qué libro os ha marcado más en vuestra vida y por qué?',
            '¿Creéis que es posible encontrar un equilibrio entre vida laboral y personal en la sociedad actual?',
            '¿Qué tradición o costumbre de vuestra cultura os gustaría que perdurara en el tiempo?',
            'Si pudierais cambiar una cosa de cómo funciona internet, ¿qué sería?',
            '¿Qué opináis sobre la idea de una renta básica universal?',
            '¿Cuál es vuestro mayor aprendizaje de los últimos años?',
            '¿Creéis que la democracia necesita reinventarse para el siglo XXI?',
            '¿Qué habilidad os gustaría aprender y nunca habéis tenido tiempo?',
            '¿Cómo imagináis el mundo en 2050?',

            // Current affairs topics
            'La inteligencia artificial está avanzando a pasos agigantados. ¿Deberíamos estar preocupados o emocionados?',
            'El cambio climático ya no es una amenaza futura, es una realidad presente. ¿Qué cambios habéis hecho en vuestra vida para reducir vuestro impacto?',
            'Los precios de la vivienda siguen siendo un problema grave. ¿Qué soluciones creéis que podrían funcionar?',
            'La salud mental está por fin recibiendo la atención que merece. ¿Qué recursos o prácticas os han ayudado personalmente?',
            'El trabajo remoto ha llegado para quedarse, pero ¿es realmente mejor para todos?',
            'Las criptomonedas y blockchain siguen generando debate. ¿Son el futuro de las finanzas o una burbuja especulativa?',
            'La desinformación online es un problema creciente. ¿Cómo podemos combatirla sin caer en la censura?',
            'Los videojuegos ya no son solo entretenimiento, son arte, deporte y herramienta educativa. ¿Cuál ha sido vuestra experiencia?',
            'El activismo digital: ¿es efectivo o solo nos hace sentir bien sin generar cambio real?',
            'La longevidad humana está aumentando. ¿Estamos preparados como sociedad para vivir más años?',

            // Personal experiences
            'Hoy he tenido una conversación muy interesante con un desconocido en el metro. Me recordó lo importante que es salir de nuestra burbuja social.',
            'Después de años trabajando en lo mismo, finalmente me he animado a cambiar de carrera. Es aterrador pero emocionante.',
            'He empezado a practicar meditación hace unos meses y el cambio en mi bienestar ha sido notable. ¿Alguien más medita aquí?',
            'Acabo de volver de un viaje que me ha cambiado la perspectiva sobre muchas cosas. Viajar es la mejor educación.',
            'Hoy me he dado cuenta de que paso demasiado tiempo en el móvil. Voy a intentar hacer un detox digital este fin de semana.',
            'He recuperado el contacto con un viejo amigo después de años. Es increíble cómo algunas conexiones perduran a pesar del tiempo.',
            'Estoy intentando ser más consciente de mis hábitos de consumo. Pequeños cambios pueden tener un gran impacto.',
            'He empezado un proyecto personal que llevaba años postergando. A veces solo hay que dar el primer paso.',
            'La pandemia me enseñó a valorar las pequeñas cosas. ¿Qué aprendisteis vosotros de esa experiencia?',
            'He descubierto un nuevo hobby que me apasiona. Nunca es tarde para encontrar nuevas pasiones.',

            // Humor and lightness
            '¿Soy el único que piensa que los lunes deberían ser opcionales? 😅',
            'Propongo que el café sea considerado un derecho fundamental.',
            'Acabo de descubrir que he estado pronunciando mal una palabra toda mi vida. ¿Os ha pasado?',
            'Mi gato tiene más seguidores en Instagram que yo. No sé cómo sentirme al respecto.',
            'El WiFi de mi casa tiene más personalidad que algunos de mis conocidos.',
            '¿Por qué las mejores ideas siempre llegan justo antes de dormir?',
            "He intentado seguir una receta 'fácil' de internet. Spoiler: no era fácil.",
            'Mi relación con el despertador es... complicada.',
            '¿Alguien más habla solo cuando está concentrado o soy raro?',
            'El autocorrector es mi mayor enemigo. Me ha puesto en situaciones muy incómodas.',

            // Science and technology
            'Los últimos descubrimientos sobre el universo me dejan sin palabras. Somos tan pequeños en la inmensidad del cosmos.',
            'La computación cuántica podría revolucionar todo lo que conocemos. ¿Alguien entiende realmente cómo funciona?',
            'Los avances en medicina regenerativa son prometedores. El futuro de la salud va a ser muy diferente.',
            'Me fascina cómo la neurociencia está revelando los secretos del cerebro. Aún hay tanto por descubrir.',
            'Las energías renovables están avanzando más rápido de lo esperado. Hay razones para el optimismo.',
            'La exploración espacial está viviendo una nueva edad dorada. ¿Veremos humanos en Marte en nuestra vida?',
            'Los robots y la automatización transformarán el mercado laboral. ¿Cómo nos preparamos?',
            'La edición genética plantea dilemas éticos fascinantes. ¿Dónde está el límite?',
            'Internet de las cosas: ¿comodidad o invasión de privacidad?',
            'La realidad virtual y aumentada van a cambiar cómo interactuamos con el mundo. ¿Estáis preparados?',

            // Culture and society
            'El arte siempre ha sido un reflejo de la sociedad. ¿Qué dice el arte contemporáneo sobre nosotros?',
            'La música tiene el poder de evocar emociones como ninguna otra forma de expresión. ¿Cuál es vuestra canción del momento?',
            'Las series y películas están abordando temas cada vez más complejos. La narrativa audiovisual ha madurado mucho.',
            'Los museos y la cultura deberían ser accesibles para todos. ¿Qué barreras habéis encontrado?',
            'La gastronomía es parte de nuestra identidad cultural. ¿Qué plato tradicional os conecta con vuestras raíces?',
            'El feminismo ha logrado avances importantes, pero queda camino por recorrer. ¿Cuáles son los próximos retos?',
            'La diversidad enriquece a las sociedades. ¿Cómo podemos fomentar más inclusión?',
            'Las tradiciones evolucionan con el tiempo. ¿Cuáles deberíamos preservar y cuáles dejar atrás?',
            'El deporte une a las personas de una manera única. ¿Qué os apasiona?',
            'La moda es una forma de expresión personal. ¿Os preocupa cómo os perciben los demás?',
        ];
    }

    /**
     * Reply templates.
     */
    private function getReplyTemplates(): array
    {
        return [
            // Agreements
            'Totalmente de acuerdo. Es algo que también he pensado mucho últimamente.',
            'Exacto, has dado en el clavo. Esto es algo que necesita más atención.',
            'Me alegra ver que alguien más piensa así. A veces me sentía solo en esta opinión.',
            'Muy bien expresado. Comparto completamente tu punto de vista.',
            'Esto es oro. Gracias por compartirlo.',
            'No podría haberlo dicho mejor. Suscribo cada palabra.',
            'Este comentario merece más visibilidad. Muy acertado.',
            'Coincido al 100%. Es algo que deberíamos discutir más.',
            '+1. Experiencia similar aquí.',
            'Esto resume perfectamente lo que muchos pensamos pero no sabemos expresar.',

            // Respectful disagreements
            'Entiendo tu punto, pero creo que hay otros factores a considerar.',
            'Interesante perspectiva, aunque no estoy del todo de acuerdo. ¿Has considerado...?',
            'Respeto tu opinión, pero mi experiencia ha sido diferente.',
            'Creo que el tema es más complejo de lo que parece. Hay matices importantes.',
            'No estoy seguro de que sea tan simple. ¿Qué hay de...?',
            'Veo tu punto, pero ¿no crees que también influye...?',
            'Discrepo parcialmente. En mi experiencia...',
            'Interesante, pero creo que falta considerar el contexto de...',
            'Entiendo de dónde viene esa opinión, pero los datos sugieren otra cosa.',
            'Buen argumento, aunque yo lo veo desde otro ángulo.',

            // Questions and curiosity
            'Interesante... ¿podrías elaborar más sobre eso?',
            'Me has dejado pensando. ¿Tienes alguna fuente sobre el tema?',
            '¿Cómo llegaste a esa conclusión?',
            'Nunca lo había visto así. ¿Qué te llevó a esa reflexión?',
            'Esto me genera curiosidad. ¿Alguien tiene más información?',
            '¿Y qué opinas sobre el aspecto de...?',
            'Me pregunto cómo aplicar esto en la práctica...',
            '¿Has tenido experiencias personales relacionadas con esto?',
            'Interesante enfoque. ¿Cómo lo relacionas con...?',
            '¿Cuál crees que sería el primer paso para...?',

            // Additional contributions
            'Añado a lo que dices: también es importante considerar...',
            'Esto me recuerda a algo que leí sobre...',
            'Para complementar, os recomiendo investigar sobre...',
            'En la misma línea, creo que también deberíamos hablar de...',
            'Buen punto. Y si lo llevamos más allá...',
            'Relacionado con esto, hay un concepto llamado...',
            'Me gustaría añadir otra perspectiva...',
            'Además de lo mencionado, también hay que tener en cuenta...',
            'Esto conecta con otro tema que me parece relevante...',
            'Para profundizar en esto, os sugiero...',

            // Personal experiences
            'Me pasó algo similar hace poco. Es más común de lo que pensamos.',
            'Puedo confirmar esto desde mi experiencia personal.',
            'Esto me recuerda a cuando yo...',
            'He vivido algo parecido y puedo decir que...',
            'Mi experiencia con esto ha sido...',
            'Justo ayer tuve una situación relacionada con esto.',
            'Como alguien que ha pasado por algo similar...',
            'En mi caso particular...',
            'Trabajé en este campo y puedo aportar que...',
            'Tengo un familiar/amigo que vivió algo así y...',

            // Humor and empathy
            'Jaja, me siento muy identificado con esto.',
            '¡Por fin alguien lo dice! 😄',
            'Esto necesitaba ser dicho. Gracias.',
            'Me has sacado una sonrisa con esto.',
            'Te entiendo perfectamente. Todos hemos estado ahí.',
            'Esto es demasiado real 😅',
            'No sabía que necesitaba leer esto hoy.',
            'Me alegra saber que no soy el único.',
            'Esto debería estar enmarcado en algún sitio.',
            'Has verbalizado lo que muchos sentimos.',

            // Reflections
            'Esto me ha dado mucho que pensar...',
            'Voy a reflexionar sobre esto. Gracias por compartir.',
            'Me has cambiado un poco la perspectiva.',
            'Nunca lo había considerado desde ese ángulo.',
            'Interesante punto de vista. Lo tendré en cuenta.',
            'Esto plantea preguntas importantes.',
            'Me quedo con esta reflexión para el día de hoy.',
            'Has abierto un debate muy necesario.',
            'Creo que todos deberíamos reflexionar más sobre esto.',
            'Gracias por hacernos pensar.',

            // Acknowledgements
            'Gracias por compartir esto. Muy valioso.',
            'Excelente aporte a la discusión.',
            'Esto es exactamente lo que buscaba. Gracias.',
            'Agradezco que alguien hable de esto.',
            'Gran contribución al debate.',
            'Muy útil esta información. Gracias.',
            'Esto aporta mucho a la conversación.',
            'Gracias por tomarte el tiempo de explicarlo.',
            'Aprecio mucho esta perspectiva.',
            'Contenido de calidad. Gracias por compartir.',
        ];
    }
}
