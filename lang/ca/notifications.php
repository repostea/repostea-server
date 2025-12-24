<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Notificacions de Correu Electrònic
    |--------------------------------------------------------------------------
    |
    | Les següents línies d'idioma s'utilitzen a les notificacions
    | enviades per correu electrònic per a diversos propòsits
    |
    */

    // Notificaciones generales
    'greeting' => 'Hola!',
    'whoops' => 'Ups!',
    'salutation' => 'Salutacions!',
    'regards' => 'Atentament,',
    'trouble_clicking' => 'Si tens problemes per fer clic al botó ":actionText", copia i enganxa la següent URL al teu navegador:',
    'all_rights_reserved' => 'Tots els drets reservats.',
    'footer_contact_us' => 'Contacte',
    'footer_legal_info' => 'Informació legal',
    'footer_privacy_policy' => 'Política de privacitat',
    'footer_legal_notice' => 'Aquest correu ha estat enviat com a part del servei. Si tens cap pregunta sobre el processament de les teves dades personals, consulta la nostra',
    'footer_text' => 'Si no has sol·licitat aquest missatge, pots ignorar-lo amb seguretat.',

    // Password reset
    'password_reset' => [
        'subject' => 'Restabliment de contrasenya',
        'intro' => 'Estàs rebent aquest correu perquè hem rebut una sol·licitud de restabliment de contrasenya per al teu compte.',
        'action' => 'Restablir contrasenya',
        'expiration' => 'Aquest enllaç de restabliment de contrasenya caducarà en :count minuts.',
        'no_request' => 'Si no has sol·licitat un restabliment de contrasenya, no cal fer cap acció.',
        'success' => 'La teva contrasenya ha estat restablerta correctament.',
        'failed' => 'No s\'ha pogut restablir la teva contrasenya. Si us plau, intenta-ho de nou.',
    ],

    // Magic Links
    'magic_link' => [
        'subject' => 'Enllaç d\'accés al teu compte',
        'intro' => 'Has sol·licitat un enllaç màgic per iniciar sessió al teu compte.',
        'action' => 'Iniciar sessió',
        'expiration' => 'Aquest enllaç caducarà en 15 minuts.',
        'no_request' => 'Si no has sol·licitat aquest enllaç, pots ignorar aquest missatge.',
        'sent' => 'T\'hem enviat un enllaç d\'accés per correu electrònic.',
        'failed' => 'No s\'ha pogut enviar l\'enllaç d\'accés. Si us plau, intenta-ho de nou.',
        'invalid_token' => 'L\'enllaç d\'accés no és vàlid o ha expirat.',
        'success' => 'Has iniciat sessió correctament.',
    ],

    // Email verification
    'email_verification' => [
        'subject' => 'Verifica la teva adreça de correu electrònic',
        'intro' => 'Gràcies per registrar-te. Si us plau, verifica la teva adreça de correu electrònic fent clic al botó següent.',
        'action' => 'Verificar correu electrònic',
        'no_request' => 'Si no has creat un compte, no cal fer cap acció.',
        'verified' => 'El teu correu electrònic ha estat verificat correctament.',
        'already_verified' => 'El teu correu electrònic ja ha estat verificat.',
        'sent' => 'S\'ha enviat un nou enllaç de verificació al teu correu electrònic.',
        'verification_link_sent' => 'S\'ha enviat un nou enllaç de verificació a la teva adreça de correu electrònic.',
    ],

    // Authentication
    'auth' => [
        'failed' => 'Les credencials proporcionades són incorrectes.',
        'password' => 'La contrasenya proporcionada és incorrecta.',
        'throttle' => 'Massa intents d\'inici de sessió. Si us plau intenta de nou en :seconds segons.',
        'logout_success' => 'Sessió tancada correctament',
        'login_success' => 'Has iniciat sessió correctament.',
        'invalid_token' => 'El token d\'autenticació no és vàlid.',
        'expired_token' => 'El token d\'autenticació ha expirat.',
        'user_not_found' => 'No s\'ha trobat l\'usuari.',
        'password_updated' => 'Contrasenya actualitzada correctament.',
    ],

    // Notificaciones de cuenta
    'account' => [
        'created' => 'El teu compte ha estat creat correctament.',
        'updated' => 'El teu compte ha estat actualitzat correctament.',
        'deleted' => 'El teu compte ha estat eliminat correctament.',
        'profile_updated' => 'El teu perfil ha estat actualitzat correctament.',
    ],

    // Cambios de seguridad
    'security' => [
        'password_changed' => 'La teva contrasenya ha estat canviada. Si no has estat tu, si us plau contacta\'ns immediatament.',
        'email_changed' => 'La teva adreça de correu electrònic ha estat canviada. Si no has estat tu, si us plau contacta\'ns immediatament.',
        'suspicious_activity' => 'Hem detectat activitat sospitosa al teu compte. Si no has estat tu, si us plau contacta\'ns immediatament.',
    ],

    // 2FA
    'two_factor' => [
        'enabled' => 'L\'autenticació de dos factors ha estat habilitada correctament.',
        'disabled' => 'L\'autenticació de dos factors ha estat deshabilitada correctament.',
        'code_sent' => 'S\'ha enviat un codi de verificació al teu dispositiu.',
        'recovery_codes' => 'Aquí estan els teus codis de recuperació. Guarda\'ls en un lloc segur.',
    ],

    // Notificaciones del sistema
    'system' => [
        'maintenance' => 'El lloc estarà en manteniment el :date durant :duration hores.',
        'update' => 'El lloc ha estat actualitzat amb noves funcionalitats.',
        'welcome' => 'Benvingut a Renegats. Gràcies per unir-te a la nostra comunitat!',
    ],

    // Account approval
    'account_approval' => [
        'approved' => [
            'subject' => 'El teu compte ha estat aprovat',
            'intro' => 'Bones notícies! El teu registre ha estat aprovat pels nostres administradors.',
            'next_steps' => 'Ja pots iniciar sessió i començar a participar a la nostra comunitat.',
            'action' => 'Iniciar sessió',
            'welcome' => 'Benvingut a Renegats! Estem encantats de tenir-te a la nostra comunitat.',
        ],
        'rejected' => [
            'subject' => 'Actualització sobre el teu registre',
            'intro' => 'Lamentem informar-te que el teu registre de compte no ha estat aprovat.',
            'reason_label' => 'Motiu:',
            'contact' => 'Si tens cap pregunta o vols discutir aquesta decisió, no dubtis a contactar amb el nostre equip de suport.',
        ],
    ],

    // Notificaciones de Karma y Logros
    'karma_level_up_title' => 'Nou nivell de Karma!',
    'karma_level_up_body' => 'Has assolit el nivell: :level.',
    'benefits' => 'Beneficis',
    'total_karma' => 'Karma total: :karma punts',
    'achievement_unlocked_title' => 'Assoliment desbloquejat!',
    'achievement_unlocked_congrats' => 'Enhorabona!',
    'achievement_unlocked_body' => 'Has desbloquejat: :achievement.',
    'karma_bonus' => 'Bonus de karma',
    'karma_earned' => 'Has guanyat :karma punts de karma',

    // Mensajes motivadores de logros
    'achievement_motivation_welcome' => 'Benvingut a la comunitat! Segueix participant per desbloquejar més assoliments.',
    'achievement_motivation_first' => 'Gran començament! Aquest és el primer de molts assoliments. Segueix així.',
    'achievement_motivation_posts' => 'Excel·lent! El teu contingut enriqueix la comunitat. Segueix compartint.',
    'achievement_motivation_comments' => 'Fantàstic! Els teus comentaris generen conversa. Segueix participant.',
    'achievement_motivation_votes' => 'Genial! La teva participació ajuda a destacar el millor contingut. Segueix votant.',
    'achievement_motivation_streak' => 'Impressionant constància! La teva dedicació diària és admirable. Mantén la ratxa.',
    'achievement_motivation_karma' => 'Increïble! El teu karma segueix creixent. Segueix contribuint a la comunitat.',
    'achievement_motivation_community' => 'Ets un pilar de la comunitat! La teva contribució marca la diferència.',

    // Notificaciones de Reportes
    'report_resolved_subject' => 'Actualització sobre el teu informe',
    'report_resolved_title' => 'Informe atès',
    'report_resolved_body' => 'El teu informe ha estat revisat i s\'han pres les mesures oportunes.',
    'report_resolved_thanks' => 'Gràcies per ajudar-nos a mantenir la comunitat segura.',
    'report_dismissed_subject' => 'Actualització sobre el teu informe',
    'report_dismissed_title' => 'Informe revisat',
    'report_dismissed_body' => 'El teu informe ha estat revisat i desestimat.',
    'report_dismissed_explanation' => 'Després de revisar el contingut, hem determinat que no viola les nostres normes comunitàries.',
    'report_generic_message' => 'Si tens cap pregunta, no dubtis a contactar amb l\'equip de moderació.',

    // Notificaciones de Admin
    'new_user_registration_title' => 'Nou registre pendent',
    'new_user_registration_body' => 'L\'usuari :username s\'ha registrat i està pendent d\'aprovació',

    // Legal reports
    'legal_report' => [
        'new_title' => 'Nou informe legal',
        'new_body' => 'S\'ha rebut un informe :type (Ref: :reference)',
        'received_subject' => 'Hem rebut el teu informe',
        'received_intro' => 'El teu informe s\'ha registrat amb el número de referència :reference.',
        'received_details' => 'Tipus d\'informe: :type',
        'received_timeline' => 'El nostre equip legal el revisarà en un termini de 24-48 hores.',
    ],
];
