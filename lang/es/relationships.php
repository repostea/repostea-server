<?php

declare(strict_types=1);

return [
    'types' => [
        'continuation' => 'Continuación',
        'correction' => 'Corrección',
        'update' => 'Actualización',
        'reply' => 'Respuesta',
        'related' => 'Relacionado',
        'duplicate' => 'Duplicado',
    ],
    'descriptions' => [
        'continuation' => 'Esta publicación es una continuación de otra publicación',
        'correction' => 'Esta publicación corrige información de otra publicación',
        'update' => 'Esta publicación actualiza o proporciona nueva información sobre otra publicación',
        'reply' => 'Esta publicación es una respuesta a otra publicación',
        'related' => 'Esta publicación está relacionada con otra publicación',
        'duplicate' => 'Esta publicación es un duplicado de otra publicación',
    ],
    'errors' => [
        'self_relation' => 'Una publicación no puede estar relacionada consigo misma',
        'only_author_can_create' => 'Solo el autor de esta publicación puede marcarla como continuación o corrección',
        'cannot_reply_own_post' => 'No puedes responder a tu propia publicación',
        'already_exists' => 'Esta relación ya existe',
        'create_failed' => 'Error al crear la relación',
        'delete_failed' => 'Error al eliminar la relación',
        'not_found' => 'Relación no encontrada para esta publicación',
        'no_permission' => 'No tienes permiso para eliminar esta relación',
    ],
    'success' => [
        'created' => 'Relación creada correctamente',
        'deleted' => 'Relación eliminada correctamente',
    ],
];
