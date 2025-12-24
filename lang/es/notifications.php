<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notificaciones de Correo Electrónico
    |--------------------------------------------------------------------------
    |
    | Las siguientes líneas de idioma se utilizan en las notificaciones
    | enviadas por correo electrónico para diversos propósitos
    |
    */

    // Notificaciones generales
    'greeting' => '¡Hola!',
    'view' => 'Ver',

    // Títulos de notificaciones en la app
    'new_reply' => 'Nueva respuesta',
    'new_comment' => 'Nuevo comentario',
    'new_mention' => 'Nueva mención',
    'new_membership_request' => 'Nueva solicitud de membresía',

    // Cuerpos de notificaciones en la app
    'replied_to_your_comment_in' => 'respondió a tu comentario en:',
    'commented_on' => 'comentó en:',
    'mentioned_you_in' => 'te mencionó en:',
    'requested_to_join' => 'ha solicitado unirse a',
    'manage_requests' => 'Gestionar solicitudes',
    'whoops' => '¡Ups!',
    'salutation' => '¡Saludos!',
    'regards' => 'Atentamente,',
    'trouble_clicking' => 'Si tienes problemas para hacer clic en el botón ":actionText", copia y pega la siguiente URL en tu navegador:',
    'all_rights_reserved' => 'Todos los derechos reservados.',
    'footer_contact_us' => 'Contacto',
    'footer_legal_info' => 'Información legal',
    'footer_privacy_policy' => 'Política de privacidad',
    'footer_legal_notice' => 'Este correo ha sido enviado como parte del servicio. Si tienes alguna pregunta sobre el procesamiento de tus datos personales, consulta nuestra',
    'footer_text' => 'Si no has solicitado este mensaje, puedes ignorarlo con seguridad.',

    // Password reset
    'password_reset' => [
        'subject' => 'Restablecimiento de contraseña',
        'intro' => 'Estás recibiendo este correo porque hemos recibido una solicitud de restablecimiento de contraseña para tu cuenta.',
        'action' => 'Restablecer contraseña',
        'expiration' => 'Este enlace de restablecimiento de contraseña caducará en :count minutos.',
        'no_request' => 'Si no solicitaste un restablecimiento de contraseña, no es necesario realizar ninguna acción.',
        'success' => 'Tu contraseña ha sido restablecida correctamente.',
        'failed' => 'No se ha podido restablecer tu contraseña. Por favor, intenta de nuevo.',
    ],

    // Magic Links
    'magic_link' => [
        'subject' => 'Enlace de acceso a tu cuenta',
        'intro' => 'Has solicitado un enlace mágico para iniciar sesión en tu cuenta.',
        'action' => 'Iniciar sesión',
        'expiration' => 'Este enlace caducará en 15 minutos.',
        'no_request' => 'Si no has solicitado este enlace, puedes ignorar este mensaje.',
        'sent' => 'Te hemos enviado un enlace de acceso por correo electrónico.',
        'failed' => 'No se ha podido enviar el enlace de acceso. Por favor, intenta de nuevo.',
        'invalid_token' => 'El enlace de acceso no es válido o ha expirado.',
        'success' => 'Has iniciado sesión correctamente.',
    ],

    // Email verification
    'email_verification' => [
        'subject' => 'Verifica tu dirección de correo electrónico',
        'intro' => 'Gracias por registrarte. Por favor, verifica tu dirección de correo electrónico haciendo clic en el botón a continuación.',
        'action' => 'Verificar correo electrónico',
        'no_request' => 'Si no has creado una cuenta, no es necesario realizar ninguna acción.',
        'verified' => 'Tu correo electrónico ha sido verificado correctamente.',
        'already_verified' => 'Tu correo electrónico ya ha sido verificado.',
        'sent' => 'Se ha enviado un nuevo enlace de verificación a tu correo electrónico.',
        'verification_link_sent' => 'Se ha enviado un nuevo enlace de verificación a tu dirección de correo electrónico.',
    ],

    // Email change
    'email_change_requested' => [
        'subject' => 'Solicitud de cambio de correo electrónico',
        'intro' => 'Hemos recibido una solicitud para cambiar el correo electrónico asociado a tu cuenta.',
        'new_email' => 'El nuevo correo electrónico solicitado es: :email',
        'warning' => 'Si realizaste esta solicitud, recibirás un correo de confirmación en la nueva dirección.',
        'not_you' => 'Si no has solicitado este cambio, te recomendamos cambiar tu contraseña inmediatamente y contactar con soporte.',
    ],

    'email_change_confirmation' => [
        'subject' => 'Confirma tu nuevo correo electrónico',
        'intro' => 'Has solicitado cambiar tu correo electrónico a esta dirección.',
        'instructions' => 'Haz clic en el botón a continuación para confirmar el cambio.',
        'action' => 'Confirmar nuevo correo',
        'expires' => 'Este enlace expirará en 24 horas.',
        'not_you' => 'Si no has solicitado este cambio, puedes ignorar este mensaje.',
    ],

    // Authentication
    'auth' => [
        'failed' => 'Las credenciales proporcionadas son incorrectas.',
        'password' => 'La contraseña proporcionada es incorrecta.',
        'throttle' => 'Demasiados intentos de inicio de sesión. Por favor intenta de nuevo en :seconds segundos.',
        'logout_success' => 'Sesión cerrada correctamente',
        'login_success' => 'Has iniciado sesión correctamente.',
        'invalid_token' => 'El token de autenticación no es válido.',
        'expired_token' => 'El token de autenticación ha expirado.',
        'user_not_found' => 'No se ha encontrado el usuario.',
        'password_updated' => 'Contraseña actualizada correctamente.',
    ],

    // Notificaciones de cuenta
    'account' => [
        'created' => 'Tu cuenta ha sido creada correctamente.',
        'updated' => 'Tu cuenta ha sido actualizada correctamente.',
        'deleted' => 'Tu cuenta ha sido eliminada correctamente.',
        'profile_updated' => 'Tu perfil ha sido actualizado correctamente.',
    ],

    // Cambios de seguridad
    'security' => [
        'password_changed' => 'Tu contraseña ha sido cambiada. Si no has sido tú, por favor contáctanos inmediatamente.',
        'email_changed' => 'Tu dirección de correo electrónico ha sido cambiada. Si no has sido tú, por favor contáctanos inmediatamente.',
        'suspicious_activity' => 'Hemos detectado actividad sospechosa en tu cuenta. Si no has sido tú, por favor contáctanos inmediatamente.',
    ],

    // 2FA
    'two_factor' => [
        'enabled' => 'La autenticación de dos factores ha sido habilitada correctamente.',
        'disabled' => 'La autenticación de dos factores ha sido deshabilitada correctamente.',
        'code_sent' => 'Se ha enviado un código de verificación a tu dispositivo.',
        'recovery_codes' => 'Aquí están tus códigos de recuperación. Guárdalos en un lugar seguro.',
    ],

    // Notificaciones del sistema
    'system' => [
        'maintenance' => 'El sitio estará en mantenimiento el :date durante :duration horas.',
        'update' => 'El sitio ha sido actualizado con nuevas funcionalidades.',
        'welcome' => 'Bienvenido a Renegados. ¡Gracias por unirte a nuestra comunidad!',
    ],

    // Account approval
    'account_approval' => [
        'approved' => [
            'subject' => 'Tu cuenta ha sido aprobada',
            'intro' => '¡Buenas noticias! Tu registro ha sido aprobado por nuestros administradores.',
            'next_steps' => 'Ya puedes iniciar sesión y comenzar a participar en nuestra comunidad.',
            'action' => 'Iniciar sesión',
            'welcome' => '¡Bienvenido a Renegados! Estamos encantados de tenerte en nuestra comunidad.',
        ],
        'rejected' => [
            'subject' => 'Actualización sobre tu registro',
            'intro' => 'Lamentamos informarte que tu registro de cuenta no ha sido aprobado.',
            'reason_label' => 'Motivo:',
            'contact' => 'Si tienes alguna pregunta o deseas discutir esta decisión, no dudes en contactar con nuestro equipo de soporte.',
        ],
    ],

    // Notificaciones de Karma y Logros
    'karma_level_up_title' => '¡Nuevo nivel de Karma!',
    'karma_level_up_body' => 'Has alcanzado el nivel: :level.',
    'benefits' => 'Beneficios',
    'total_karma' => 'Karma total: :karma puntos',
    'achievement_unlocked_title' => '¡Logro desbloqueado!',
    'achievement_unlocked_congrats' => '¡Enhorabuena!',
    'achievement_unlocked_body' => 'Has desbloqueado: :achievement.',
    'karma_bonus' => 'Bonus de karma',
    'karma_earned' => 'Has ganado :karma puntos de karma',
    'view_profile' => 'Ver tu perfil',
    'keep_participating' => '¡Sigue participando en la comunidad!',
    'view_achievements' => 'Ver tus logros',
    'anonymous_user' => 'Un usuario anónimo',

    // Karma events
    'karma_event_types' => [
        'tide' => 'Marea Alta',
        'boost' => 'Impulso',
        'surge' => 'Oleada',
        'wave' => 'Ola',
    ],
    'karma_event_starting_subject' => '¡:event de Karma comienza pronto!',
    'karma_event_starting_intro' => 'Un evento especial de karma está a punto de comenzar.',
    'karma_event_multiplier' => 'Durante este evento, todo el karma que ganes será multiplicado por :multiplierx.',
    'karma_event_time' => 'El evento comienza el :date a las :start y termina a las :end.',
    'karma_event_opportunity' => '¡No pierdas esta oportunidad de aumentar tu karma!',
    'karma_event_title' => '¡:event de Karma!',
    'karma_event_body' => 'Todo el karma que ganes será multiplicado por :multiplierx. Comienza el :time. ¡Aprovecha para participar!',
    'participate_now' => 'Participa Ahora',

    // Agora notifications
    'agora_new_reply' => 'Nueva respuesta en el Ágora',
    'agora_new_mention' => 'Nueva mención en el Ágora',
    'agora_replied_to_message' => 'respondió a tu mensaje en el Ágora',
    'agora_mentioned_you' => 'te mencionó en el Ágora',

    // Mensajes motivadores de logros
    'achievement_motivation_welcome' => '¡Bienvenido a la comunidad! Sigue participando para desbloquear más logros.',
    'achievement_motivation_first' => '¡Gran comienzo! Este es el primero de muchos logros. Sigue así.',
    'achievement_motivation_posts' => '¡Excelente! Tu contenido enriquece la comunidad. Sigue compartiendo.',
    'achievement_motivation_comments' => '¡Fantástico! Tus comentarios generan conversación. Sigue participando.',
    'achievement_motivation_votes' => '¡Genial! Tu participación ayuda a destacar el mejor contenido. Sigue votando.',
    'achievement_motivation_streak' => '¡Impresionante constancia! Tu dedicación diaria es admirable. Mantén la racha.',
    'achievement_motivation_karma' => '¡Increíble! Tu karma sigue creciendo. Sigue contribuyendo a la comunidad.',
    'achievement_motivation_community' => '¡Eres un pilar de la comunidad! Tu contribución marca la diferencia.',

    // Notificaciones de Reportes
    'report_resolved_subject' => 'Actualización sobre tu reporte',
    'report_resolved_title' => 'Reporte atendido',
    'report_resolved_body' => 'Tu reporte ha sido revisado y se han tomado las medidas oportunas.',
    'report_resolved_thanks' => 'Gracias por ayudarnos a mantener la comunidad segura.',
    'report_dismissed_subject' => 'Actualización sobre tu reporte',
    'report_dismissed_title' => 'Reporte revisado',
    'report_dismissed_body' => 'Tu reporte ha sido revisado y desestimado.',
    'report_dismissed_explanation' => 'Tras revisar el contenido, hemos determinado que no viola nuestras normas comunitarias.',
    'report_generic_message' => 'Si tienes alguna pregunta, no dudes en contactar con el equipo de moderación.',

    // Notificaciones de Admin
    'new_user_registration_title' => 'Nuevo registro pendiente',
    'new_user_registration_body' => 'El usuario :username se ha registrado y está pendiente de aprobación',

    // Legal reports
    'legal_report' => [
        'new_title' => 'Nuevo reporte legal',
        'new_body' => 'Se ha recibido un reporte :type (Ref: :reference)',
        'received_subject' => 'Hemos recibido tu reporte',
        'received_intro' => 'Tu reporte ha sido registrado con el número de referencia :reference.',
        'received_details' => 'Tipo de reporte: :type',
        'received_timeline' => 'Nuestro equipo legal lo revisará en un plazo de 24-48 horas.',
    ],

    // Push notifications
    'push_subscription_saved' => 'Suscripción de notificaciones push guardada',
    'push_subscription_removed' => 'Suscripción de notificaciones push eliminada',
    'preferences_updated' => 'Preferencias de notificación actualizadas',
    'snooze_activated' => 'Notificaciones silenciadas',
    'snooze_cancelled' => 'Silencio cancelado',
    'test_sent' => 'Notificación de prueba enviada',
    'no_subscriptions' => 'No hay suscripciones push activas',

    // Test notification
    'test' => [
        'title' => '¡Notificación de prueba!',
        'body' => 'Las notificaciones push están funcionando correctamente.',
    ],

    // Push notification messages
    'push' => [
        'comment_reply_title' => 'Nueva respuesta',
        'comment_reply_body' => '@:user respondió: ":preview"',
        'post_comment_title' => 'Nuevo comentario',
        'post_comment_body' => '@:user comentó en ":post"',
        'mention_title' => 'Te han mencionado',
        'mention_body' => '@:user te mencionó: ":preview"',
        'agora_reply_title' => 'Respuesta en Agora',
        'agora_reply_body' => '@:user respondió a tu mensaje',
        'agora_mention_title' => 'Mención en Agora',
        'agora_mention_body' => '@:user te mencionó en Agora',
        'achievement_title' => '¡Logro desbloqueado!',
        'achievement_body' => 'Has desbloqueado: :achievement',
    ],

    // Quiet hours summary
    'quiet_hours_summary' => [
        'title' => 'Resumen de horas de silencio',
        'body' => '{1} Tienes :count notificación nueva|[2,*] Tienes :count notificaciones nuevas',
    ],
];
