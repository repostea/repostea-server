<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Posta Elektroniko Jakinarazpenak
    |--------------------------------------------------------------------------
    |
    | Hizkuntza lerro hauek posta elektronikoz bidaltzen diren
    | jakinarazpenetan erabiltzen dira helburu ezberdinerako
    |
    */

    // General notifications
    'greeting' => 'Kaixo!',
    'whoops' => 'Ai ene!',
    'salutation' => 'Agurrak!',
    'regards' => 'Begirunez,',
    'trouble_clicking' => '":actionText" botoian klik egiteko arazoak badituzu, kopiatu eta itsatsi beheko URLa zure nabigatzailean:',
    'all_rights_reserved' => 'Eskubide guztiak erreserbatuak.',
    'footer_contact_us' => 'Kontaktua',
    'footer_legal_info' => 'Informazio legala',
    'footer_privacy_policy' => 'Pribatutasun politika',
    'footer_legal_notice' => 'Mezu hau zerbitzuaren zati gisa bidali da. Zure datu pertsonalen prozesamenduari buruz galderarik baduzu, kontsultatu gure',
    'footer_text' => 'Ez baduzu mezu hau eskatu, segurtasunez ezikusi dezakezu.',

    // Password reset
    'password_reset' => [
        'subject' => 'Pasahitza berrezartzea',
        'intro' => 'Mezu hau jasotzen ari zara zure kontuarentzako pasahitza berrezartzeko eskaera jaso dugulako.',
        'action' => 'Pasahitza berrezarri',
        'expiration' => 'Pasahitza berrezartzeko esteka honek :count minutu barru iraungiko du.',
        'no_request' => 'Ez baduzu pasahitza berrezartzeko eskaera egin, ez da ekintzarik egin behar.',
        'success' => 'Zure pasahitza zuzen berrezarri da.',
        'failed' => 'Ezin izan da zure pasahitza berrezarri. Mesedez, saiatu berriro.',
    ],

    // Magic Links
    'magic_link' => [
        'subject' => 'Zure konturako sarbide esteka',
        'intro' => 'Esteka magiko bat eskatu duzu zure kontuan saioa hasteko.',
        'action' => 'Saioa hasi',
        'expiration' => 'Esteka honek 15 minutu barru iraungiko du.',
        'no_request' => 'Ez baduzu esteka hau eskatu, mezu hau ezikusi dezakezu.',
        'sent' => 'Sarbide esteka bat bidali dizugu posta elektronikoz.',
        'failed' => 'Ezin izan da sarbide esteka bidali. Mesedez, saiatu berriro.',
        'invalid_token' => 'Sarbide esteka ez da baliozkoa edo iraungi da.',
        'success' => 'Saioa zuzen hasi duzu.',
    ],

    // Email verification
    'email_verification' => [
        'subject' => 'Egiaztatu zure helbide elektronikoa',
        'intro' => 'Eskerrik asko erregistratzeagatik. Mesedez, egiaztatu zure helbide elektronikoa beheko botoian klik eginez.',
        'action' => 'Helbide elektronikoa egiaztatu',
        'no_request' => 'Ez baduzu konturik sortu, ez da ekintzarik egin behar.',
        'verified' => 'Zure helbide elektronikoa zuzen egiaztatu da.',
        'already_verified' => 'Zure helbide elektronikoa dagoeneko egiaztatu da.',
        'sent' => 'Egiaztapen esteka berri bat bidali da zure helbide elektronikora.',
        'verification_link_sent' => 'Egiaztapen esteka berri bat bidali da zure helbide elektronikora.',
    ],

    // Authentication
    'auth' => [
        'failed' => 'Emandako kredentzialak okerrak dira.',
        'password' => 'Emandako pasahitza okerra da.',
        'throttle' => 'Saio hasteko saiakera gehiegi. Mesedez, saiatu berriro :seconds segundo barru.',
        'logout_success' => 'Saioa zuzen itxi da',
        'login_success' => 'Saioa zuzen hasi duzu.',
        'invalid_token' => 'Autentifikazio tokena ez da baliozkoa.',
        'expired_token' => 'Autentifikazio tokena iraungi da.',
        'user_not_found' => 'Ez da erabiltzailea aurkitu.',
        'password_updated' => 'Pasahitza zuzen eguneratu da.',
    ],

    // Account notifications
    'account' => [
        'created' => 'Zure kontua zuzen sortu da.',
        'updated' => 'Zure kontua zuzen eguneratu da.',
        'deleted' => 'Zure kontua zuzen ezabatu da.',
        'profile_updated' => 'Zure profila zuzen eguneratu da.',
    ],

    // Security changes
    'security' => [
        'password_changed' => 'Zure pasahitza aldatu da. Zu ez bazara izan, mesedez jarri gurekin harremanetan berehala.',
        'email_changed' => 'Zure helbide elektronikoa aldatu da. Zu ez bazara izan, mesedez jarri gurekin harremanetan berehala.',
        'suspicious_activity' => 'Jarduera susmagarria detektatu dugu zure kontuan. Zu ez bazara izan, mesedez jarri gurekin harremanetan berehala.',
    ],

    // 2FA
    'two_factor' => [
        'enabled' => 'Bi faktoreko autentifikazioa zuzen gaitu da.',
        'disabled' => 'Bi faktoreko autentifikazioa zuzen desgaitu da.',
        'code_sent' => 'Egiaztapen kode bat bidali da zure gailura.',
        'recovery_codes' => 'Hona hemen zure berreskuratze kodeak. Gorde itzazu toki seguru batean.',
    ],

    // System notifications
    'system' => [
        'maintenance' => 'Gunea mantentze lanetan egongo da :date-an :duration orduz.',
        'update' => 'Gunea funtzionalitate berriekin eguneratu da.',
        'welcome' => 'Ongi etorri Renegados-era. Eskerrik asko gure komunitatera batzeagatik!',
    ],

    // Account approval
    'account_approval' => [
        'approved' => [
            'subject' => 'Zure kontua onartu da',
            'intro' => 'Albiste onak! Zure erregistroa gure administratzaileek onartu dute.',
            'next_steps' => 'Dagoeneko saioa hasi eta gure komunitatean parte hartzen hasi zaitezke.',
            'action' => 'Saioa hasi',
            'welcome' => 'Ongi etorri Renegados-era! Pozik gaude gure komunitatean izateagatik.',
        ],
        'rejected' => [
            'subject' => 'Zure erregistroari buruzko eguneratzea',
            'intro' => 'Sentitzen dugu jakinaraztea zure kontu erregistroa ez dela onartu.',
            'reason_label' => 'Arrazoia:',
            'contact' => 'Galderarik baduzu edo erabaki hau eztabaidatu nahi baduzu, ez izan zalantzarik gure laguntza taldearekin harremanetan jartzeko.',
        ],
    ],

    // Notificaciones de Karma y Logros
    'karma_level_up_title' => 'Karma maila berria!',
    'karma_level_up_body' => 'Maila hau lortu duzu: :level.',
    'benefits' => 'Onurak',
    'total_karma' => 'Karma osoa: :karma puntu',
    'achievement_unlocked_title' => 'Lorpena desblokeatu da!',
    'achievement_unlocked_congrats' => 'Zorionak!',
    'achievement_unlocked_body' => 'Hau desblokeatu duzu: :achievement.',
    'karma_bonus' => 'Karma bonusa',
    'karma_earned' => ':karma karma puntu irabazi dituzu',

    // Mensajes motivadores de logros
    'achievement_motivation_welcome' => 'Ongi etorri komunitatera! Jarraitu parte hartzen lorpen gehiago desblokeatzeko.',
    'achievement_motivation_first' => 'Hasiera bikaina! Hau lehena da lorpen askoren artean. Jarraitu horrela.',
    'achievement_motivation_posts' => 'Bikaina! Zure edukiak komunitatea aberasten du. Jarraitu partekatzen.',
    'achievement_motivation_comments' => 'Zoragarria! Zure iruzkinak elkarrizketa sortzen dute. Jarraitu parte hartzen.',
    'achievement_motivation_votes' => 'Primeran! Zure parte-hartzeak eduki onena nabarmentzen laguntzen du. Jarraitu bozketan.',
    'achievement_motivation_streak' => 'Etengabetasun harrigarria! Zure eguneko dedikazioa miretsgarria da. Mantendu sekuentzia.',
    'achievement_motivation_karma' => 'Sinesgaitza! Zure karma hazten jarraitzen du. Jarraitu komunitatean ekarpena egiten.',
    'achievement_motivation_community' => 'Komunitatearen zutabea zara! Zure ekarpenak aldea egiten du.',

    // Notificaciones de Reportes
    'report_resolved_subject' => 'Zure txostenari buruzko eguneratzea',
    'report_resolved_title' => 'Txostena artatu da',
    'report_resolved_body' => 'Zure txostena berrikusi da eta neurri egokiak hartu dira.',
    'report_resolved_thanks' => 'Eskerrik asko komunitatea seguru mantentzen laguntzeagatik.',
    'report_dismissed_subject' => 'Zure txostenari buruzko eguneratzea',
    'report_dismissed_title' => 'Txostena berrikusi da',
    'report_dismissed_body' => 'Zure txostena berrikusi eta baztertu da.',
    'report_dismissed_explanation' => 'Edukia berrikusi ondoren, gure komunitate arauak ez dituela urratzen zehaztu dugu.',
    'report_generic_message' => 'Galderarik baduzu, ez izan zalantzarik moderazio taldearekin harremanetan jartzeko.',

    // Notificaciones de Admin
    'new_user_registration_title' => 'Erregistro berria zain',
    'new_user_registration_body' => ':username erabiltzailea erregistratu da eta onarpenaren zain dago',

    // Legal reports
    'legal_report' => [
        'new_title' => 'Txosten legal berria',
        'new_body' => ':type txostena jaso da (Ref: :reference)',
        'received_subject' => 'Zure txostena jaso dugu',
        'received_intro' => 'Zure txostena :reference erreferentzia zenbakiarekin erregistratu da.',
        'received_details' => 'Txosten mota: :type',
        'received_timeline' => 'Gure lege taldeak 24-48 orduko epean berrikusiko du.',
    ],
];
