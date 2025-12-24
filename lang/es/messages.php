<?php

declare(strict_types=1);

return [
    // Authentication
    'auth' => [
        'failed' => 'Estas credenciales no coinciden con nuestros registros.',
        'logout_success' => 'Sesión cerrada exitosamente.',
        'login_required' => 'Debes iniciar sesión para importar contenido.',
        'magic_link_sent' => 'Enlace mágico enviado a tu correo electrónico.',
        'user_not_found' => 'Usuario no encontrado.',
    ],

    // Passwords
    'passwords' => [
        'updated' => 'Contraseña actualizada exitosamente.',
        'update_error' => 'Error al actualizar la contraseña.',
    ],

    // Comments
    'comments' => [
        'deleted' => 'Comentario eliminado exitosamente.',
        'too_old' => 'Esta publicación es demasiado antigua para recibir comentarios.',
        'hidden_by_user' => 'Este usuario ha elegido ocultar su lista de comentarios.',
    ],

    // Agora
    'agora' => [
        'message_not_found' => 'Mensaje no encontrado.',
        'unauthorized' => 'No autorizado.',
        'deleted' => 'Mensaje eliminado exitosamente.',
        'vote_registered' => 'Voto registrado exitosamente.',
        'vote_removed' => 'Voto eliminado exitosamente.',
        'expiry_too_soon' => 'La duración elegida haría que el mensaje expire inmediatamente. Elige una duración mayor.',
    ],

    // Content
    'content' => [
        'attached_image' => '[imagen adjunta]',
    ],

    // External Content
    'external' => [
        'rss_error' => 'Error al obtener el feed RSS.',
        'data_error' => 'Error al obtener datos de Mediatize.',
        'source_not_implemented' => 'Fuente no implementada en el proxy.',
        'mediatize_fetch_error' => 'Error al obtener noticias de Mediatize.',
        'mediatize_processing_error' => 'Error al procesar el feed RSS de Mediatize.',
        'reddit_fetch_error' => 'Error al obtener noticias de Reddit.',
        'reddit_processing_error' => 'Error al procesar el feed de Reddit.',
        'techcrunch_fetch_error' => 'Error al obtener noticias de TechCrunch.',
        'techcrunch_processing_error' => 'Error al procesar el feed RSS de TechCrunch.',
        'import_error' => 'Error al importar contenido externo.',
        'no_title' => 'Sin título',
    ],

    // Media
    'media' => [
        'youtube_id_extraction_error' => 'No se pudo extraer el ID del video de YouTube.',
        'vimeo_id_extraction_error' => 'No se pudo extraer el ID del video de Vimeo.',
        'avatar_uploaded' => 'Avatar subido correctamente.',
        'avatar_deleted' => 'Avatar eliminado correctamente.',
        'avatar_upload_failed' => 'Error al subir el avatar.',
        'avatar_validation_error' => 'Imagen de avatar inválida.',
        'thumbnail_uploaded' => 'Miniatura subida correctamente.',
        'thumbnail_deleted' => 'Miniatura eliminada correctamente.',
        'thumbnail_upload_failed' => 'Error al subir la miniatura.',
        'thumbnail_download_failed' => 'Error al descargar y subir la miniatura.',
        'thumbnail_validation_error' => 'Imagen de miniatura inválida.',
        'image_uploaded' => 'Imagen subida correctamente.',
        'image_upload_failed' => 'Error al subir la imagen.',
        'image_validation_error' => 'Imagen inválida.',
    ],

    // Posts
    'posts' => [
        'not_found' => 'Publicación no encontrada.',
        'removed_or_not_found' => 'Este contenido ha sido eliminado o no existe.',
        'cannot_change_hidden_status' => 'No puedes cambiar el estado de un post que ha sido ocultado por un moderador.',
        'cannot_delete_with_comments_after_hours' => 'Esta publicación tiene comentarios y ya han pasado más de :hours horas desde su creación. Si necesitas eliminarla, contacta con un administrador.',
        'no_permission_to_delete' => 'No tienes permiso para eliminar esta publicación.',
        'deleted' => 'Publicación eliminada exitosamente.',
        'imported_successfully' => 'Contenido importado exitosamente.',
        'import_error' => 'Error al importar contenido.',
        'update_error' => 'Error al actualizar la publicación.',
        'url_already_imported' => 'Esta URL ya ha sido importada.',
        'view_registered' => 'Vista registrada correctamente.',
        'view_already_registered' => 'Vista ya registrada recientemente.',
        'no_post_ids' => 'No se proporcionaron IDs de publicación.',
        'invalid_post_ids' => 'Formato de IDs de publicación inválido.',
    ],

    // Users
    'users' => [
        'not_found' => 'Usuario no encontrado.',
        'account_deleted' => 'Esta cuenta de usuario ha sido eliminada.',
        'not_found_or_deleted' => 'Usuario no encontrado o eliminado.',
    ],

    // Profile
    'profile' => [
        'email_change_disabled' => 'Cambio de correo electrónico temporalmente deshabilitado.',
        'email_not_allowed' => 'El cambio de correo electrónico no está permitido en este momento.',
        'updated' => 'Perfil actualizado exitosamente.',
    ],

    // Email Change
    'email_change' => [
        'same_email' => 'El nuevo correo es el mismo que el actual.',
        'verification_sent' => 'Se ha enviado un enlace de verificación al nuevo correo electrónico.',
        'request_error' => 'Error al solicitar el cambio de correo electrónico.',
        'invalid_token' => 'El enlace de verificación no es válido.',
        'token_expired' => 'El enlace de verificación ha expirado. Por favor, solicita un nuevo cambio.',
        'success' => 'Tu correo electrónico ha sido cambiado exitosamente.',
        'confirm_error' => 'Error al confirmar el cambio de correo electrónico.',
        'no_pending_change' => 'No hay ningún cambio de correo pendiente.',
        'cancelled' => 'El cambio de correo electrónico ha sido cancelado.',
        'cancel_error' => 'Error al cancelar el cambio de correo electrónico.',
    ],

    // Validation
    'validation' => [
        'invalid_data' => 'Los datos proporcionados no son válidos.',
    ],

    // Settings
    'settings' => [
        'updated' => 'Configuración de usuario actualizada exitosamente.',
    ],

    // Karma
    'karma' => [
        'streak_updated' => 'Racha actualizada exitosamente.',
        'streak_update_failed' => 'Error al actualizar la racha.',
        'event_created' => 'Evento de karma creado correctamente.',
        'event_updated' => 'Evento de karma actualizado correctamente.',
        'event_deleted' => 'Evento de karma eliminado correctamente.',
        'event_already_started' => 'No se pueden enviar notificaciones para un evento que ya ha comenzado.',
        'notifications_sent' => 'Notificaciones enviadas correctamente.',
        'notifications_failed' => 'Error al enviar las notificaciones.',
    ],

    // Polls
    'polls' => [
        'not_a_poll' => 'Esta publicación no es una encuesta.',
        'options_not_found' => 'Opciones de encuesta no encontradas.',
        'invalid_option' => 'Opción de encuesta inválida.',
        'expired' => 'Esta encuesta ha expirado.',
        'already_voted' => 'Ya has votado por esta opción.',
        'vote_recorded' => 'Voto registrado correctamente.',
        'vote_removed' => 'Voto eliminado correctamente.',
        'no_votes_to_remove' => 'No se encontraron votos para eliminar.',
        'login_required_vote' => 'Debes iniciar sesión para votar.',
        'login_required_remove' => 'Debes iniciar sesión para eliminar votos.',
        'error_loading' => 'Error al cargar los resultados de la encuesta.',
        'error_voting' => 'Error al registrar el voto.',
        'error_removing' => 'Error al eliminar el voto.',
    ],

    // Votes
    'votes' => [
        'invalid_type' => 'Tipo de voto inválido para este valor.',
        'invalid_type_allowed' => 'El tipo de voto proporcionado no es válido. Tipos permitidos: :types',
        'already_voted' => 'Ya has votado con este tipo de voto.',
        'updated' => 'Voto actualizado.',
        'recorded' => 'Voto registrado.',
        'removed' => 'Voto eliminado.',
        'too_old' => 'Esta publicación es demasiado antigua para recibir votos.',
        'cannot_update_others' => 'No puedes actualizar el voto de otro usuario.',
        'cannot_delete_others' => 'No puedes eliminar el voto de otro usuario.',
    ],

    // Saved Lists
    'savedlists' => [
        'type_exists' => 'Ya existe una lista de este tipo.',
        'cannot_change_special_type' => 'No se puede cambiar el tipo de listas especiales.',
        'cannot_delete_special' => 'No se pueden eliminar listas especiales del sistema.',
        'deleted' => 'Lista eliminada exitosamente.',
        'post_already_in_list' => 'La publicación ya está en esta lista.',
        'post_added' => 'Publicación añadida a la lista exitosamente.',
        'post_removed' => 'Publicación eliminada de la lista exitosamente.',
        'removed_from_favorites' => 'Publicación eliminada de favoritos.',
        'added_to_favorites' => 'Publicación añadida a favoritos.',
        'removed_from_read_later' => 'Publicación eliminada de leer más tarde.',
        'added_to_read_later' => 'Publicación añadida a leer más tarde.',
        'post_not_in_list' => 'Publicación no encontrada en esta lista.',
        'notes_updated' => 'Notas actualizadas exitosamente.',
        'cannot_clear_special' => 'No se pueden limpiar listas especiales del sistema.',
        'cleared' => 'Lista limpiada exitosamente.',
    ],

    // URL Validation
    'url_validation' => [
        'not_allowed' => 'La URL proporcionada no está permitida.',
    ],

    // Errors
    'errors' => [
        'generic' => 'Ha ocurrido un error. Por favor, inténtalo de nuevo.',
    ],

    // Notifications
    'notifications' => [
        'magic_link' => [
            'invalid_token' => 'Token inválido o expirado.',
        ],
    ],

    // Admin
    'admin' => [
        'backup_created' => 'Copia de seguridad creada exitosamente.',
        'backup_failed' => 'Error al crear la copia de seguridad.',
        'invalid_database' => 'Base de datos especificada inválida.',
        'cache_clear_error' => 'Error al limpiar la caché.',
        'command_error' => 'Error al ejecutar el comando.',
        'notification_error' => 'Error al enviar el correo de notificación.',
    ],

    // Sessions
    'sessions' => [
        'revoked' => 'Sesión revocada exitosamente.',
        'all_revoked' => ':count sesiones revocadas exitosamente.',
        'not_found' => 'Sesión no encontrada.',
        'cannot_revoke_current' => 'No puedes revocar la sesión actual.',
    ],

    // Email footer
    'All rights reserved.' => 'Todos los derechos reservados.',
    'If you have any questions, contact us at' => 'Si tienes alguna pregunta, contacta con nosotros en',

    // Email template defaults
    'Hello!' => '¡Hola!',
    'Whoops!' => '¡Ups!',
    'Regards,' => 'Saludos,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:' => 'Si tienes problemas para hacer clic en el botón ":actionText", copia y pega la siguiente URL en tu navegador:',
];
