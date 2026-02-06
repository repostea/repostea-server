<?php

declare(strict_types=1);

return [
    // Authentication
    'auth' => [
        'failed' => 'Kredentzial hauek ez datoz bat gure erregistroekin.',
        'logout_success' => 'Saioa arrakastaz itxi da.',
        'login_required' => 'Saioa hasi behar duzu edukia inportatzeko.',
        'magic_link_sent' => 'Esteka magikoa zure helbide elektronikora bidali da.',
        'user_not_found' => 'Erabiltzailea ez da aurkitu.',
    ],

    // Passwords
    'passwords' => [
        'updated' => 'Pasahitza arrakastaz eguneratu da.',
        'update_error' => 'Errorea pasahitza eguneratzean.',
    ],

    // Comments
    'comments' => [
        'deleted' => 'Iruzkina arrakastaz ezabatu da.',
    ],

    // Agora
    'agora' => [
        'message_not_found' => 'Mezua ez da aurkitu.',
        'unauthorized' => 'Ez zaude baimenduta.',
        'deleted' => 'Mezua arrakastaz ezabatu da.',
        'vote_registered' => 'Botoa arrakastaz erregistratu da.',
        'vote_removed' => 'Botoa arrakastaz ezabatu da.',
    ],

    // External Content
    'external' => [
        'rss_error' => 'Errorea RSS jarioa eskuratzean.',
        'data_error' => 'Errorea Mediatize-ko datuak eskuratzean.',
        'source_not_implemented' => 'Iturria ez dago proxy-an inplementatuta.',
        'mediatize_fetch_error' => 'Errorea Mediatize-ko albisteak eskuratzean.',
        'mediatize_processing_error' => 'Errorea Mediatize-ko RSS jarioa prozesatzean.',
        'techcrunch_fetch_error' => 'Errorea TechCrunch-eko albisteak eskuratzean.',
        'techcrunch_processing_error' => 'Errorea TechCrunch-eko RSS jarioa prozesatzean.',
        'import_error' => 'Errorea kanpoko edukia inportatzean.',
        'no_title' => 'Titulurik gabe',
    ],

    // Media
    'media' => [
        'youtube_id_extraction_error' => 'Ezin izan da YouTube bideoaren IDa atera.',
        'vimeo_id_extraction_error' => 'Ezin izan da Vimeo bideoaren IDa atera.',
    ],

    // Posts
    'posts' => [
        'not_found' => 'Argitalpena ez da aurkitu.',
        'removed_or_not_found' => 'Eduki hau ezabatu da edo ez dago.',
        'cannot_change_hidden_status' => 'Ezin duzu moderatzaile batek ezkutatu duen argitalpenaren egoera aldatu.',
        'cannot_delete_with_comments_after_hours' => 'Argitalpen honek iruzkinak ditu eta :hours ordu baino gehiago igaro dira sortu zenetik. Ezabatu behar baduzu, jarri harremanetan administratzaile batekin.',
        'no_permission_to_delete' => 'Ez duzu baimenik argitalpen hau ezabatzeko.',
        'deleted' => 'Argitalpena arrakastaz ezabatu da.',
        'imported_successfully' => 'Edukia arrakastaz inportatu da.',
        'import_error' => 'Errorea edukia inportatzean: :error',
        'url_already_imported' => 'URL hau dagoeneko inportatu da.',
    ],

    // Profile
    'profile' => [
        'email_change_disabled' => 'Helbide elektronikoaren aldaketa aldi baterako desgaituta dago.',
        'email_not_allowed' => 'Helbide elektronikoaren aldaketa ez dago baimenduta une honetan.',
        'updated' => 'Profila arrakastaz eguneratu da.',
    ],

    // Validation
    'validation' => [
        'invalid_data' => 'Emandako datuak ez dira baliozkoak.',
    ],

    // Settings
    'settings' => [
        'updated' => 'Erabiltzailearen konfigurazioa arrakastaz eguneratu da.',
    ],

    // Karma
    'karma' => [
        'streak_updated' => 'Sekuentzia arrakastaz eguneratu da.',
        'streak_update_failed' => 'Errorea sekuentzia eguneratzean.',
    ],

    // Votes
    'votes' => [
        'invalid_type' => 'Botoaren mota baliogabea balio honetarako.',
        'invalid_type_allowed' => 'Emandako botoaren mota ez da baliozkoa. Baimendutako motak: :types',
        'already_voted' => 'Dagoeneko botoa eman duzu mota honekin.',
        'updated' => 'Botoa eguneratu da.',
        'recorded' => 'Botoa erregistratu da.',
        'removed' => 'Botoa ezabatu da.',
        'too_old' => 'Argitalpen hau zaharregia da botoak jasotzeko.',
        'cannot_update_others' => 'Ezin duzu beste erabiltzaile baten botoa eguneratu.',
        'cannot_delete_others' => 'Ezin duzu beste erabiltzaile baten botoa ezabatu.',
    ],

    // Saved Lists
    'savedlists' => [
        'type_exists' => 'Dagoeneko badago mota honetako zerrenda bat.',
        'cannot_change_special_type' => 'Ezin da zerrenda berezien mota aldatu.',
        'cannot_delete_special' => 'Ezin dira sistemako zerrenda bereziak ezabatu.',
        'deleted' => 'Zerrenda arrakastaz ezabatu da.',
        'post_already_in_list' => 'Argitalpena dagoeneko zerrenda honetan dago.',
        'post_added' => 'Argitalpena zerrendara arrakastaz gehitu da.',
        'post_removed' => 'Argitalpena zerrendatik arrakastaz ezabatu da.',
        'removed_from_favorites' => 'Argitalpena gogokoetatik ezabatu da.',
        'added_to_favorites' => 'Argitalpena gogokoetara gehitu da.',
        'removed_from_read_later' => 'Argitalpena geroago irakurtzekoetatik ezabatu da.',
        'added_to_read_later' => 'Argitalpena geroago irakurtzera gehitu da.',
        'post_not_in_list' => 'Argitalpena ez da aurkitu zerrenda honetan.',
        'notes_updated' => 'Oharrak arrakastaz eguneratu dira.',
        'cannot_clear_special' => 'Ezin dira sistemako zerrenda bereziak garbitu.',
        'cleared' => 'Zerrenda arrakastaz garbitu da.',
    ],

    // Notifications
    'notifications' => [
        'magic_link' => [
            'invalid_token' => 'Token baliogabea edo iraungia.',
        ],
    ],

    // Admin
    'admin' => [
        'backup_created' => 'Babeskopia arrakastaz sortu da.',
        'backup_failed' => 'Errorea babeskopia sortzean',
    ],

    // Email footer
    'All rights reserved.' => 'Eskubide guztiak erreserbatuak.',
    'If you have any questions, contact us at' => 'Galderarik baduzu, jarri gurekin harremanetan hemen',

    // Email template defaults
    'Hello!' => 'Kaixo!',
    'Whoops!' => 'Ai ene!',
    'Regards,' => 'Agurrak,',
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n" .
    'into your web browser:' => '":actionText" botoian klik egiteko arazoak badituzu, kopiatu eta itsatsi beheko URLa zure nabigatzailean:',
];
