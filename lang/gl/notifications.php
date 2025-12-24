<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notificacións de Correo Electrónico
    |--------------------------------------------------------------------------
    |
    | As seguintes liñas de idioma utilízanse nas notificacións
    | enviadas por correo electrónico para diversos propósitos
    |
    */

    // General notifications
    'greeting' => 'Ola!',
    'whoops' => 'Vaites!',
    'salutation' => 'Saúdos!',
    'regards' => 'Atentamente,',
    'trouble_clicking' => 'Se tes problemas para facer clic no botón ":actionText", copia e pega a seguinte URL no teu navegador:',
    'all_rights_reserved' => 'Todos os dereitos reservados.',
    'footer_contact_us' => 'Contacto',
    'footer_legal_info' => 'Información legal',
    'footer_privacy_policy' => 'Política de privacidade',
    'footer_legal_notice' => 'Este correo foi enviado como parte do servizo. Se tes algunha pregunta sobre o procesamento dos teus datos persoais, consulta a nosa',
    'footer_text' => 'Se non solicitaches esta mensaxe, podes ignorala con seguridade.',

    // Password reset
    'password_reset' => [
        'subject' => 'Restablecemento de contrasinal',
        'intro' => 'Estás recibindo este correo porque recibimos unha solicitude de restablecemento de contrasinal para a túa conta.',
        'action' => 'Restablecer contrasinal',
        'expiration' => 'Esta ligazón de restablecemento de contrasinal caducará en :count minutos.',
        'no_request' => 'Se non solicitaches un restablecemento de contrasinal, non é necesario realizar ningunha acción.',
        'success' => 'O teu contrasinal foi restablecido correctamente.',
        'failed' => 'Non se puido restablecer o teu contrasinal. Por favor, intenta de novo.',
    ],

    // Magic Links
    'magic_link' => [
        'subject' => 'Ligazón de acceso á túa conta',
        'intro' => 'Solicitaches unha ligazón máxica para iniciar sesión na túa conta.',
        'action' => 'Iniciar sesión',
        'expiration' => 'Esta ligazón caducará en 15 minutos.',
        'no_request' => 'Se non solicitaches esta ligazón, podes ignorar esta mensaxe.',
        'sent' => 'Enviámosche unha ligazón de acceso por correo electrónico.',
        'failed' => 'Non se puido enviar a ligazón de acceso. Por favor, intenta de novo.',
        'invalid_token' => 'A ligazón de acceso non é válida ou caducou.',
        'success' => 'Iniciaches sesión correctamente.',
    ],

    // Email verification
    'email_verification' => [
        'subject' => 'Verifica o teu enderezo de correo electrónico',
        'intro' => 'Grazas por rexistrarte. Por favor, verifica o teu enderezo de correo electrónico facendo clic no botón a continuación.',
        'action' => 'Verificar correo electrónico',
        'no_request' => 'Se non creaches unha conta, non é necesario realizar ningunha acción.',
        'verified' => 'O teu correo electrónico foi verificado correctamente.',
        'already_verified' => 'O teu correo electrónico xa foi verificado.',
        'sent' => 'Enviouse unha nova ligazón de verificación ao teu correo electrónico.',
        'verification_link_sent' => 'Enviouse unha nova ligazón de verificación ao teu enderezo de correo electrónico.',
    ],

    // Authentication
    'auth' => [
        'failed' => 'As credenciais proporcionadas son incorrectas.',
        'password' => 'O contrasinal proporcionado é incorrecto.',
        'throttle' => 'Demasiados intentos de inicio de sesión. Por favor intenta de novo en :seconds segundos.',
        'logout_success' => 'Sesión pechada correctamente',
        'login_success' => 'Iniciaches sesión correctamente.',
        'invalid_token' => 'O token de autenticación non é válido.',
        'expired_token' => 'O token de autenticación caducou.',
        'user_not_found' => 'Non se atopou o usuario.',
        'password_updated' => 'Contrasinal actualizado correctamente.',
    ],

    // Account notifications
    'account' => [
        'created' => 'A túa conta foi creada correctamente.',
        'updated' => 'A túa conta foi actualizada correctamente.',
        'deleted' => 'A túa conta foi eliminada correctamente.',
        'profile_updated' => 'O teu perfil foi actualizado correctamente.',
    ],

    // Security changes
    'security' => [
        'password_changed' => 'O teu contrasinal foi cambiado. Se non foches ti, por favor contáctanos inmediatamente.',
        'email_changed' => 'O teu enderezo de correo electrónico foi cambiado. Se non foches ti, por favor contáctanos inmediatamente.',
        'suspicious_activity' => 'Detectamos actividade sospeitosa na túa conta. Se non foches ti, por favor contáctanos inmediatamente.',
    ],

    // 2FA
    'two_factor' => [
        'enabled' => 'A autenticación de dous factores foi habilitada correctamente.',
        'disabled' => 'A autenticación de dous factores foi deshabilitada correctamente.',
        'code_sent' => 'Enviouse un código de verificación ao teu dispositivo.',
        'recovery_codes' => 'Aquí están os teus códigos de recuperación. Gárdaos nun lugar seguro.',
    ],

    // System notifications
    'system' => [
        'maintenance' => 'O sitio estará en mantemento o :date durante :duration horas.',
        'update' => 'O sitio foi actualizado con novas funcionalidades.',
        'welcome' => 'Benvido a Renegados. Grazas por unirte á nosa comunidade!',
    ],

    // Account approval
    'account_approval' => [
        'approved' => [
            'subject' => 'A túa conta foi aprobada',
            'intro' => 'Boas novas! O teu rexistro foi aprobado polos nosos administradores.',
            'next_steps' => 'Xa podes iniciar sesión e comezar a participar na nosa comunidade.',
            'action' => 'Iniciar sesión',
            'welcome' => 'Benvido a Renegados! Estamos encantados de terte na nosa comunidade.',
        ],
        'rejected' => [
            'subject' => 'Actualización sobre o teu rexistro',
            'intro' => 'Lamentamos informarte de que o teu rexistro de conta non foi aprobado.',
            'reason_label' => 'Motivo:',
            'contact' => 'Se tes algunha pregunta ou desexas discutir esta decisión, non dubides en contactar co noso equipo de soporte.',
        ],
    ],

    // Notificaciones de Karma y Logros
    'karma_level_up_title' => 'Novo nivel de Karma!',
    'karma_level_up_body' => 'Alcanzaches o nivel: :level.',
    'benefits' => 'Beneficios',
    'total_karma' => 'Karma total: :karma puntos',
    'achievement_unlocked_title' => 'Logro desbloqueado!',
    'achievement_unlocked_congrats' => 'Parabéns!',
    'achievement_unlocked_body' => 'Desbloqueaches: :achievement.',
    'karma_bonus' => 'Bonus de karma',
    'karma_earned' => 'Gañaches :karma puntos de karma',

    // Mensajes motivadores de logros
    'achievement_motivation_welcome' => 'Benvido á comunidade! Segue participando para desbloquear máis logros.',
    'achievement_motivation_first' => 'Gran comezo! Este é o primeiro de moitos logros. Segue así.',
    'achievement_motivation_posts' => 'Excelente! O teu contido enriquece a comunidade. Segue compartindo.',
    'achievement_motivation_comments' => 'Fantástico! Os teus comentarios xeran conversa. Segue participando.',
    'achievement_motivation_votes' => 'Xenial! A túa participación axuda a destacar o mellor contido. Segue votando.',
    'achievement_motivation_streak' => 'Impresionante constancia! A túa dedicación diaria é admirable. Mantén a racha.',
    'achievement_motivation_karma' => 'Incrible! O teu karma segue crecendo. Segue contribuíndo á comunidade.',
    'achievement_motivation_community' => 'Es un pilar da comunidade! A túa contribución marca a diferenza.',

    // Notificaciones de Reportes
    'report_resolved_subject' => 'Actualización sobre o teu informe',
    'report_resolved_title' => 'Informe atendido',
    'report_resolved_body' => 'O teu informe foi revisado e tomáronse as medidas oportunas.',
    'report_resolved_thanks' => 'Grazas por axudarnos a manter a comunidade segura.',
    'report_dismissed_subject' => 'Actualización sobre o teu informe',
    'report_dismissed_title' => 'Informe revisado',
    'report_dismissed_body' => 'O teu informe foi revisado e desestimado.',
    'report_dismissed_explanation' => 'Despois de revisar o contido, determinamos que non viola as nosas normas comunitarias.',
    'report_generic_message' => 'Se tes algunha pregunta, non dubides en contactar co equipo de moderación.',

    // Notificaciones de Admin
    'new_user_registration_title' => 'Novo rexistro pendente',
    'new_user_registration_body' => 'O usuario :username rexistrouse e está pendente de aprobación',

    // Legal reports
    'legal_report' => [
        'new_title' => 'Novo informe legal',
        'new_body' => 'Recibiuse un informe :type (Ref: :reference)',
        'received_subject' => 'Recibimos o teu informe',
        'received_intro' => 'O teu informe foi rexistrado co número de referencia :reference.',
        'received_details' => 'Tipo de informe: :type',
        'received_timeline' => 'O noso equipo legal revisarao nun prazo de 24-48 horas.',
    ],
];
