<?php

declare(strict_types=1);

return [
    // Authentication
    'auth' => [
        'failed' => 'Aquestes credencials no coincideixen amb els nostres registres.',
        'logout_success' => 'Sessió tancada exitosament.',
        'login_required' => 'Has d\'iniciar sessió per importar contingut.',
        'magic_link_sent' => 'Enllaç màgic enviat al teu correu electrònic.',
        'user_not_found' => 'Usuari no trobat.',
    ],

    // Passwords
    'passwords' => [
        'updated' => 'Contrasenya actualitzada exitosament.',
        'update_error' => 'Error en actualitzar la contrasenya.',
    ],

    // Comments
    'comments' => [
        'deleted' => 'Comentari eliminat exitosament.',
    ],

    // Agora
    'agora' => [
        'message_not_found' => 'Missatge no trobat.',
        'unauthorized' => 'No autoritzat.',
        'deleted' => 'Missatge eliminat exitosament.',
        'vote_registered' => 'Vot registrat exitosament.',
        'vote_removed' => 'Vot eliminat exitosament.',
    ],

    // External Content
    'external' => [
        'rss_error' => 'Error en obtenir el feed RSS.',
        'data_error' => 'Error en obtenir dades de Mediatize.',
        'source_not_implemented' => 'Font no implementada al proxy.',
        'mediatize_fetch_error' => 'Error en obtenir notícies de Mediatize.',
        'mediatize_processing_error' => 'Error en processar el feed RSS de Mediatize.',
        'techcrunch_fetch_error' => 'Error en obtenir notícies de TechCrunch.',
        'techcrunch_processing_error' => 'Error en processar el feed RSS de TechCrunch.',
        'import_error' => 'Error en importar contingut extern.',
        'no_title' => 'Sense títol',
    ],

    // Media
    'media' => [
        'youtube_id_extraction_error' => 'No s\'ha pogut extreure l\'ID del vídeo de YouTube.',
        'vimeo_id_extraction_error' => 'No s\'ha pogut extreure l\'ID del vídeo de Vimeo.',
    ],

    // Posts
    'posts' => [
        'not_found' => 'Publicació no trobada.',
        'removed_or_not_found' => 'Aquest contingut ha estat eliminat o no existeix.',
        'cannot_change_hidden_status' => 'No pots canviar l\'estat d\'una publicació que ha estat oculta per un moderador.',
        'cannot_delete_with_comments_after_hours' => 'Aquesta publicació té comentaris i ja han passat més de :hours hores des de la seva creació. Si necessites eliminar-la, contacta amb un administrador.',
        'no_permission_to_delete' => 'No tens permís per eliminar aquesta publicació.',
        'deleted' => 'Publicació eliminada exitosament.',
        'imported_successfully' => 'Contingut importat exitosament.',
        'import_error' => 'Error en importar contingut: :error',
        'url_already_imported' => 'Aquesta URL ja ha estat importada.',
    ],

    // Profile
    'profile' => [
        'email_change_disabled' => 'Canvi de correu electrònic temporalment deshabilitat.',
        'email_not_allowed' => 'El canvi de correu electrònic no està permès en aquest moment.',
        'updated' => 'Perfil actualitzat exitosament.',
    ],

    // Validation
    'validation' => [
        'invalid_data' => 'Les dades proporcionades no són vàlides.',
    ],

    // Settings
    'settings' => [
        'updated' => 'Configuració d\'usuari actualitzada exitosament.',
    ],

    // Karma
    'karma' => [
        'streak_updated' => 'Ratxa actualitzada exitosament.',
        'streak_update_failed' => 'Error en actualitzar la ratxa.',
    ],

    // Votes
    'votes' => [
        'invalid_type' => 'Tipus de vot invàlid per a aquest valor.',
        'invalid_type_allowed' => 'El tipus de vot proporcionat no és vàlid. Tipus permesos: :types',
        'already_voted' => 'Ja has votat amb aquest tipus de vot.',
        'updated' => 'Vot actualitzat.',
        'recorded' => 'Vot registrat.',
        'removed' => 'Vot eliminat.',
        'too_old' => 'Aquesta publicació és massa antiga per rebre vots.',
        'cannot_update_others' => 'No pots actualitzar el vot d\'un altre usuari.',
        'cannot_delete_others' => 'No pots eliminar el vot d\'un altre usuari.',
    ],

    // Saved Lists
    'savedlists' => [
        'type_exists' => 'Ja existeix una llista d\'aquest tipus.',
        'cannot_change_special_type' => 'No es pot canviar el tipus de llistes especials.',
        'cannot_delete_special' => 'No es poden eliminar llistes especials del sistema.',
        'deleted' => 'Llista eliminada exitosament.',
        'post_already_in_list' => 'La publicació ja està en aquesta llista.',
        'post_added' => 'Publicació afegida a la llista exitosament.',
        'post_removed' => 'Publicació eliminada de la llista exitosament.',
        'removed_from_favorites' => 'Publicació eliminada de favorits.',
        'added_to_favorites' => 'Publicació afegida a favorits.',
        'removed_from_read_later' => 'Publicació eliminada de llegir més tard.',
        'added_to_read_later' => 'Publicació afegida a llegir més tard.',
        'post_not_in_list' => 'Publicació no trobada en aquesta llista.',
        'notes_updated' => 'Notes actualitzades exitosament.',
        'cannot_clear_special' => 'No es poden netejar llistes especials del sistema.',
        'cleared' => 'Llista netejada exitosament.',
    ],

    // Notifications
    'notifications' => [
        'magic_link' => [
            'invalid_token' => 'Token invàlid o caducat.',
        ],
    ],

    // Admin
    'admin' => [
        'backup_created' => 'Còpia de seguretat creada exitosament.',
        'backup_failed' => 'Error en crear la còpia de seguretat',
    ],

    // Email footer
    'All rights reserved.' => 'Tots els drets reservats.',
    'If you have any questions, contact us at' => 'Si tens cap pregunta, contacta amb nosaltres a',

    // Email template defaults
    'Hello!' => 'Hola!',
    'Whoops!' => 'Ups!',
    'Regards,' => 'Salutacions,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:' => 'Si tens problemes per fer clic al botó ":actionText", copia i enganxa l\'URL següent al teu navegador:',
];
