<?php

declare(strict_types=1);

return [
    // Authentication
    'auth' => [
        'failed' => 'Estas credenciais non coinciden cos nosos rexistros.',
        'logout_success' => 'Sesión pechada exitosamente.',
        'login_required' => 'Debes iniciar sesión para importar contido.',
        'magic_link_sent' => 'Ligazón máxica enviada ao teu correo electrónico.',
        'user_not_found' => 'Usuario non atopado.',
    ],

    // Passwords
    'passwords' => [
        'updated' => 'Contrasinal actualizado exitosamente.',
        'update_error' => 'Erro ao actualizar o contrasinal.',
    ],

    // Comments
    'comments' => [
        'deleted' => 'Comentario eliminado exitosamente.',
    ],

    // Agora
    'agora' => [
        'message_not_found' => 'Mensaxe non atopada.',
        'unauthorized' => 'Non autorizado.',
        'deleted' => 'Mensaxe eliminada exitosamente.',
        'vote_registered' => 'Voto rexistrado exitosamente.',
        'vote_removed' => 'Voto eliminado exitosamente.',
    ],

    // External Content
    'external' => [
        'rss_error' => 'Erro ao obter o feed RSS.',
        'data_error' => 'Erro ao obter datos de Mediatize.',
        'source_not_implemented' => 'Fonte non implementada no proxy.',
        'mediatize_fetch_error' => 'Erro ao obter noticias de Mediatize.',
        'mediatize_processing_error' => 'Erro ao procesar o feed RSS de Mediatize.',
        'techcrunch_fetch_error' => 'Erro ao obter noticias de TechCrunch.',
        'techcrunch_processing_error' => 'Erro ao procesar o feed RSS de TechCrunch.',
        'import_error' => 'Erro ao importar contido externo.',
        'no_title' => 'Sen título',
    ],

    // Media
    'media' => [
        'youtube_id_extraction_error' => 'Non se puido extraer o ID do vídeo de YouTube.',
        'vimeo_id_extraction_error' => 'Non se puido extraer o ID do vídeo de Vimeo.',
    ],

    // Posts
    'posts' => [
        'not_found' => 'Publicación non atopada.',
        'removed_or_not_found' => 'Este contido foi eliminado ou non existe.',
        'cannot_change_hidden_status' => 'Non podes cambiar o estado dunha publicación que foi ocultada por un moderador.',
        'cannot_delete_with_comments_after_hours' => 'Esta publicación ten comentarios e xa pasaron máis de :hours horas desde a súa creación. Se necesitas eliminala, contacta cun administrador.',
        'no_permission_to_delete' => 'Non tes permiso para eliminar esta publicación.',
        'deleted' => 'Publicación eliminada exitosamente.',
        'imported_successfully' => 'Contido importado exitosamente.',
        'import_error' => 'Erro ao importar contido: :error',
        'url_already_imported' => 'Esta URL xa foi importada.',
    ],

    // Profile
    'profile' => [
        'email_change_disabled' => 'Cambio de correo electrónico temporalmente deshabilitado.',
        'email_not_allowed' => 'O cambio de correo electrónico non está permitido neste momento.',
        'updated' => 'Perfil actualizado exitosamente.',
    ],

    // Validation
    'validation' => [
        'invalid_data' => 'Os datos proporcionados non son válidos.',
    ],

    // Settings
    'settings' => [
        'updated' => 'Configuración de usuario actualizada exitosamente.',
    ],

    // Karma
    'karma' => [
        'streak_updated' => 'Racha actualizada exitosamente.',
        'streak_update_failed' => 'Erro ao actualizar a racha.',
    ],

    // Votes
    'votes' => [
        'invalid_type' => 'Tipo de voto inválido para este valor.',
        'invalid_type_allowed' => 'O tipo de voto proporcionado non é válido. Tipos permitidos: :types',
        'already_voted' => 'Xa votaches con este tipo de voto.',
        'updated' => 'Voto actualizado.',
        'recorded' => 'Voto rexistrado.',
        'removed' => 'Voto eliminado.',
        'too_old' => 'Esta publicación é demasiado antiga para recibir votos.',
        'cannot_update_others' => 'Non podes actualizar o voto doutro usuario.',
        'cannot_delete_others' => 'Non podes eliminar o voto doutro usuario.',
    ],

    // Saved Lists
    'savedlists' => [
        'type_exists' => 'Xa existe unha lista deste tipo.',
        'cannot_change_special_type' => 'Non se pode cambiar o tipo de listas especiais.',
        'cannot_delete_special' => 'Non se poden eliminar listas especiais do sistema.',
        'deleted' => 'Lista eliminada exitosamente.',
        'post_already_in_list' => 'A publicación xa está nesta lista.',
        'post_added' => 'Publicación engadida á lista exitosamente.',
        'post_removed' => 'Publicación eliminada da lista exitosamente.',
        'removed_from_favorites' => 'Publicación eliminada de favoritos.',
        'added_to_favorites' => 'Publicación engadida a favoritos.',
        'removed_from_read_later' => 'Publicación eliminada de ler máis tarde.',
        'added_to_read_later' => 'Publicación engadida a ler máis tarde.',
        'post_not_in_list' => 'Publicación non atopada nesta lista.',
        'notes_updated' => 'Notas actualizadas exitosamente.',
        'cannot_clear_special' => 'Non se poden limpar listas especiais do sistema.',
        'cleared' => 'Lista limpada exitosamente.',
    ],

    // Notifications
    'notifications' => [
        'magic_link' => [
            'invalid_token' => 'Token inválido ou caducado.',
        ],
    ],

    // Admin
    'admin' => [
        'backup_created' => 'Copia de seguridade creada exitosamente.',
        'backup_failed' => 'Erro ao crear a copia de seguridade',
    ],

    // Email footer
    'All rights reserved.' => 'Todos os dereitos reservados.',
    'If you have any questions, contact us at' => 'Se tes algunha pregunta, contacta connosco en',

    // Email template defaults
    'Hello!' => 'Ola!',
    'Whoops!' => 'Vaites!',
    'Regards,' => 'Saúdos,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:' => 'Se tes problemas para facer clic no botón ":actionText", copia e pega a seguinte URL no teu navegador:',
];
