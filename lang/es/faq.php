<?php

declare(strict_types=1);

return [
    'categories' => [
        'karma' => 'Karma',
        'seals' => 'Sellos',
        'voting' => 'Votaciones',
        'posts' => 'Envíos',
        'lists' => 'Listas',
        'community' => 'Comunidad',
        'account' => 'Cuenta',
        'relationships' => 'Relaciones entre Envíos',
        'search' => 'Búsqueda',
    ],

    'karma' => [
        'what_is' => [
            'question' => '¿Qué es el karma?',
            'answer' => '<p class="mb-3">El karma es un sistema de puntos que refleja tu participación y contribución a la comunidad.</p><p class="mb-3">Ganas karma cuando otros usuarios votan positivamente tus envíos y comentarios.</p><p>El karma te ayuda a <strong>desbloquear logros</strong> y demuestra tu reputación en la plataforma.</p>',
        ],
        'how_to_earn' => [
            'question' => '¿Cómo puedo ganar karma?',
            'answer' => '<p class="mb-3">Puedes ganar karma de varias formas:</p><ul class="list-disc list-inside space-y-2 ml-2"><li>Publicando <strong>contenido de calidad</strong> que otros usuarios voten positivamente</li><li>Escribiendo <strong>comentarios útiles</strong> o interesantes</li><li>Participando <strong>activamente</strong> en la comunidad</li></ul><p class="mt-3">Cada voto positivo en tus envíos y comentarios suma puntos a tu karma.</p>',
        ],
    ],

    'seals' => [
        'what_are' => [
            'question' => '¿Qué son los sellos?',
            'answer' => '<p class="mb-3">Los sellos son marcas especiales que puedes otorgar a envíos y comentarios para llamar la atención sobre ellos.</p><p class="mb-3">Existen <strong>dos tipos de sellos</strong>:</p><ul class="list-disc list-inside space-y-2 ml-2"><li><strong>Recomendar:</strong> Para destacar contenido que consideras muy valioso</li><li><strong>Desaconsejar:</strong> Para señalar contenido problemático o de baja calidad</li></ul><p class="mt-3 text-sm"><strong>Importante:</strong> Los sellos NO afectan al karma ni a la posición en portada. Son solo una forma de expresar tu opinión sobre el contenido.</p>',
        ],
        'how_to_use' => [
            'question' => '¿Cómo uso los sellos?',
            'answer' => '<p class="mb-3">Para usar un sello, haz clic en el botón de sellos que aparece en cada envío o comentario, luego selecciona el tipo de sello que deseas otorgar.</p><p class="mb-3">Puedes quitar un sello haciendo clic en él nuevamente.</p><p class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⚠️ Los sellos son limitados:</strong> Solo dispones de una cierta cantidad de sellos a la semana. Úsalos con cuidado en el contenido que realmente lo merezca. Los sellos expiran automáticamente después de un tiempo.</p>',
        ],
    ],

    'voting' => [
        'types' => [
            'question' => '¿Qué tipos de votos existen?',
            'answer' => '<p class="mb-3">El sistema de votos funciona diferente para envíos y comentarios:</p><div class="space-y-4"><div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 pl-4 p-3"><p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📰 Envíos:</p><p class="text-sm">Voto simple. Solo votas a favor.</p></div><div class="bg-gray-50 dark:bg-gray-800 border-l-4 border-gray-500 pl-4 p-3"><p class="font-semibold text-gray-800 dark:text-gray-300 mb-2">💬 Comentarios:</p><p class="text-sm">Sistema detallado con tipos específicos de votos positivos y negativos.</p></div></div><p class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⚠️ Importante:</strong> Todos los votos solo están disponibles para usuarios registrados.</p>',
        ],
        'comments' => [
            'question' => '¿Los votos en comentarios son diferentes?',
            'answer' => '<p class="mb-3">Sí, a diferencia de los envíos, los comentarios tienen un <strong>sistema de votos más complejo</strong>.</p><p class="mb-3">Cuando votas un comentario debes elegir un <strong>tipo específico</strong> que describe por qué lo votas:</p><div class="space-y-3 ml-2"><div><p class="font-semibold text-green-700 dark:text-green-400 text-sm mb-2">Votos Positivos:</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Didáctico:</strong> Educativo</li><li><strong>Interesante:</strong> Llamativo</li><li><strong>Elaborado:</strong> Bien trabajado</li><li><strong>Divertido:</strong> Entretenido</li></ul></div><div><p class="font-semibold text-red-700 dark:text-red-400 text-sm mb-2">Votos Negativos:</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Incompleto:</strong> Falta información</li><li><strong>Irrelevante:</strong> No aporta</li><li><strong>Falso:</strong> Información incorrecta</li><li><strong>Fuera de lugar:</strong> No corresponde</li></ul></div></div><p class="mt-3 text-sm bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 pl-3 py-2">El tipo de voto ayuda al autor a entender mejor la valoración y fomenta la calidad en los debates.</p>',
        ],
    ],

    'posts' => [
        'types' => [
            'question' => '¿Qué tipos de envíos puedo hacer?',
            'answer' => 'Puedes crear varios tipos de envíos: enlaces (URLs), texto, imágenes, videos, audio, y encuestas. Cada tipo tiene sus propias características. Por ejemplo, los envíos de texto permiten formato markdown, mientras que las encuestas permiten que otros usuarios voten entre varias opciones.',
        ],
        'anonymous' => [
            'question' => '¿Puedo enviar de forma anónima?',
            'answer' => 'Sí, puedes marcar un envío como anónimo al crearlo. Cuando envías de forma anónima, tu nombre de usuario no aparecerá asociado al envío. Sin embargo, ten en cuenta que el contenido debe seguir cumpliendo las normas de la comunidad.',
        ],
        'frontpage' => [
            'question' => '¿Cómo llega un envío a portada?',
            'answer' => '<p class="mb-3">Un envío llega a portada mediante un <strong>sistema competitivo</strong> basado en votos.</p><div class="space-y-3"><div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-3"><p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">📋 Requisitos para Portada:</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Mínimo:</strong> 2 votos positivos</li><li><strong>Edad máxima:</strong> Menos de 48 horas desde publicación</li><li><strong>Estado:</strong> Publicada (no borrador)</li><li><strong>Competencia:</strong> Máximo 24 envíos en portada (últimas 24h)</li></ul></div><div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-3"><p class="font-semibold text-green-800 dark:text-green-300 mb-2">🏆 Sistema Competitivo:</p><p class="text-sm mb-2">Si ya hay 24 envíos en portada, solo entran los que tengan <strong>más votos</strong> que los actuales.</p><p class="text-xs text-green-700 dark:text-green-400">Ejemplo: Si todos tienen 3+ votos, necesitas 4+ votos para entrar.</p></div></div><p class="mt-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>⏰ Importante:</strong> Los envíos salen automáticamente de portada pasadas 24 horas. Solo los votos positivos cuentan.</p>',
        ],
        'time_limits' => [
            'question' => '¿Hay límites de tiempo para votar o comentar?',
            'answer' => '<p class="mb-3">Sí, existen límites de tiempo para mantener la plataforma dinámica y centrada en contenido actual:</p><div class="space-y-3"><div class="border-l-4 border-orange-500 pl-4 bg-orange-50 dark:bg-orange-900/20 p-3"><p class="font-semibold text-orange-800 dark:text-orange-300 mb-2">🗳️ Votos:</p><p class="text-sm">Puedes votar durante los primeros <strong>7 días</strong> desde que se publicó. Pasado ese tiempo, el contenido ya no acepta votos.</p></div><div class="border-l-4 border-purple-500 pl-4 bg-purple-50 dark:bg-purple-900/20 p-3"><p class="font-semibold text-purple-800 dark:text-purple-300 mb-2">💬 Comentarios:</p><p class="text-sm">Puedes comentar durante el primer <strong>mes (30 días)</strong> desde el envío. Después, la conversación se cierra.</p></div></div><p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Estos límites ayudan a mantener debates activos y evitan que contenido antiguo sea manipulado.</p>',
        ],
    ],

    'lists' => [
        'favorites' => [
            'question' => '¿Qué son las listas de favoritos?',
            'answer' => 'La lista de favoritos es una colección personal donde puedes guardar envíos que te gustan o quieres volver a ver más tarde. También existe la lista "Leer más tarde" para envíos que quieres revisar cuando tengas tiempo. Estas listas son privadas por defecto.',
        ],
        'custom' => [
            'question' => '¿Puedo crear mis propias listas?',
            'answer' => 'Sí, puedes crear listas personalizadas para organizar envíos por temas o categorías que elijas. Por ejemplo, puedes crear listas como "Tutoriales", "Noticias importantes", etc. Puedes añadir notas a cada envío dentro de una lista.',
        ],
        'public' => [
            'question' => '¿Puedo hacer mis listas públicas?',
            'answer' => 'Sí, cuando creas una lista personalizada puedes marcarla como pública. Las listas públicas pueden ser vistas por otros usuarios, lo que te permite compartir colecciones de contenido interesante. Solo tú puedes añadir o quitar envíos de tus listas, incluso si son públicas.',
        ],
    ],

    'community' => [
        'subs' => [
            'question' => '¿Qué son los subs?',
            'answer' => 'Los subs son subcomunidades temáticas donde puedes enviar contenido específico. Cada sub tiene su propio tema, reglas y moderadores. Puedes unirte a los subs que te interesen para ver su contenido en tu feed personalizado.',
        ],
        'moderation' => [
            'question' => '¿Cómo funciona la moderación?',
            'answer' => '<p class="mb-3">Cada sub tiene moderadores encargados de revisar contenido reportado y asegurar el cumplimiento de las normas.</p><p class="mb-3 font-semibold">⚖️ Principio fundamental:</p><p class="mb-3"><strong>Solo se modera contenido que viola la ley.</strong> Nunca se moderará por razones ideológicas. Todas las ideas y debates son bienvenidos en la plataforma.</p><p class="mb-2 font-semibold">📋 Proceso de moderación:</p><ol class="list-decimal list-inside space-y-1 mb-3 ml-2"><li>Los usuarios reportan contenido ilegal (es un derecho y deber de todos)</li><li>Los moderadores revisan el reporte</li><li>Si se confirma la infracción, se elimina el contenido</li><li>Se impone una sanción según la gravedad y reincidencia</li></ol><p class="mb-2 font-semibold">⚠️ Sanciones disponibles:</p><ul class="list-disc list-inside space-y-1 mb-3 ml-2"><li><strong>Ocultar contenido:</strong> El contenido se elimina de la vista pública</li><li><strong>Strike (advertencia):</strong> Se registra una advertencia en el historial del usuario</li><li><strong>Ban temporal:</strong> Suspensión de la cuenta por tiempo determinado</li><li><strong>Ban permanente:</strong> Solo en casos de reincidencia grave</li></ul><p class="text-sm text-gray-600 dark:text-gray-400">La sanción depende de la gravedad de la infracción y el historial del usuario. Las reincidencias resultan en sanciones más severas.</p>',
        ],
        'reports' => [
            'question' => '¿Qué tipos de reportes puedo hacer?',
            'answer' => '<p class="mb-3">Existen <strong>dos sistemas de reporte</strong> según el tipo de contenido:</p><div class="space-y-3"><div class="bg-purple-50 dark:bg-purple-900/20 border-l-4 border-purple-500 p-3"><p class="font-semibold text-purple-800 dark:text-purple-300 mb-2">🚨 Reportes de moderación (contenido ilegal):</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Spam:</strong> Contenido repetitivo o publicitario</li><li><strong>Acoso:</strong> Hostigamiento o intimidación</li><li><strong>Contenido inapropiado:</strong> Material inadecuado</li><li><strong>Desinformación:</strong> Información falsa deliberada</li><li><strong>Discurso de odio:</strong> Incitación al odio</li><li><strong>Violencia:</strong> Amenazas o contenido violento</li><li><strong>Contenido ilegal:</strong> Cualquier violación de la ley</li></ul><p class="text-xs mt-2">Los moderadores revisan y toman acción inmediata.</p></div><div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-3"><p class="font-semibold text-green-800 dark:text-green-300 mb-2">⚖️ Reportes legales (equipo legal):</p><ul class="list-disc list-inside space-y-1 text-sm ml-2"><li><strong>Derechos de autor (DMCA):</strong> Violación de copyright</li><li><strong>Privacidad:</strong> Violación de datos personales</li><li><strong>Acoso grave:</strong> Casos que requieren intervención legal</li><li><strong>Contenido ilegal grave:</strong> Infracciones penales</li></ul><p class="text-xs mt-2">El equipo legal revisa en 24-48 horas. Para reportes DMCA debes estar autorizado por el titular de los derechos.</p></div></div><p class="mt-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm"><strong>💡 Importante:</strong> Reportar contenido que viola la ley es un derecho y deber de todos los usuarios. Ayudas a mantener la plataforma segura y legal.</p>',
        ],
        'rules' => [
            'question' => '¿Dónde puedo ver las reglas?',
            'answer' => 'Cada sub tiene sus propias reglas que puedes ver en su página principal. También existen reglas generales de la plataforma que aplican a todos los usuarios. Es importante leer las reglas antes de enviar para evitar que tu contenido sea rechazado.',
        ],
    ],

    'account' => [
        'privacy' => [
            'question' => '¿Cómo protejo mi privacidad?',
            'answer' => '<p class="mb-3">Puedes controlar tu privacidad de varias formas:</p><ul class="list-disc list-inside space-y-2 ml-2"><li>Publicar de forma <strong>anónima</strong></li><li>Hacer tus listas <strong>privadas</strong></li><li>Gestionar qué información compartes en tu perfil</li></ul><p class="mt-3 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-3"><strong>Opciones de privacidad disponibles:</strong></p><ul class="list-disc list-inside space-y-2 ml-2 mt-2"><li><strong>Ocultar logros del perfil:</strong> La lista de tus logros no será visible en tu perfil público</li><li><strong>Ocultar lista de comentarios del perfil:</strong> Tu lista de comentarios no será visible en tu perfil público (los comentarios individuales en posts siguen visibles)</li></ul><p class="mt-3 text-sm text-gray-600 dark:text-gray-400"><strong>Nota importante:</strong> Tu karma siempre es visible. Tu dirección de email nunca es visible públicamente. Estas opciones se pueden configurar en la sección de preferencias de tu perfil.</p>',
        ],
        'delete' => [
            'question' => '¿Puedo eliminar mi cuenta?',
            'answer' => 'Sí, puedes eliminar tu cuenta desde la configuración de tu perfil. Al eliminar tu cuenta, se borrarán todos tus datos personales. Ten en cuenta que tus envíos y comentarios pueden permanecer pero aparecerán como de usuario eliminado.',
        ],
        'notifications' => [
            'question' => '¿Cómo gestiono las notificaciones?',
            'answer' => 'Puedes configurar tus preferencias de notificaciones desde tu perfil. Puedes elegir recibir notificaciones por nuevos comentarios en tus envíos, respuestas a tus comentarios, menciones, y mensajes del sistema. También puedes desactivar todas las notificaciones si lo prefieres.',
        ],
    ],

    'relationships' => [
        'what_are' => [
            'question' => '¿Qué son las relaciones entre envíos?',
            'answer' => 'Las relaciones entre envíos te permiten vincular contenido relacionado. Por ejemplo, puedes marcar un envío como "continuación" de otro, o como "relacionado con" otro tema. Esto ayuda a crear hilos de conversación y permite seguir temas que se desarrollan en múltiples envíos.',
        ],
        'types' => [
            'question' => '¿Qué tipos de relaciones existen?',
            'answer' => 'Existen varios tipos de relaciones: "Continuación" para envíos que siguen una historia, "Relacionado con" para temas similares, "Actualización" para nuevas versiones, "Responde a" para réplicas, y más. Los usuarios pueden votar las relaciones para validar si son apropiadas.',
        ],
    ],

    'search' => [
        'how_to' => [
            'question' => '¿Cómo busco contenido?',
            'answer' => 'Puedes usar la barra de búsqueda en la parte superior para encontrar envíos por título, contenido o autor. La búsqueda soporta palabras clave y frases. También puedes filtrar los resultados por tipo de contenido, fecha, sub, y más.',
        ],
        'filters' => [
            'question' => '¿Qué filtros de búsqueda hay disponibles?',
            'answer' => 'Puedes filtrar por tipo de contenido (texto, imagen, video, audio), por sub, por rango de fechas, por puntuación mínima, y por autor. También puedes ordenar los resultados por relevancia, fecha, o popularidad. Los filtros te ayudan a encontrar exactamente lo que buscas.',
        ],
    ],
];
