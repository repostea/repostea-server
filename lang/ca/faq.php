<?php

declare(strict_types=1);

return [
    'categories' => [
        'karma' => 'Karma',
        'seals' => 'Segells',
        'voting' => 'Votacions',
        'posts' => 'Enviaments',
        'lists' => 'Llistes',
        'community' => 'Comunitat',
        'account' => 'Compte',
        'relationships' => 'Relacions entre Enviaments',
        'search' => 'Cerca',
    ],

    'karma' => [
        'what_is' => [
            'question' => 'Què és el karma?',
            'answer' => 'El karma és un sistema de punts que reflecteix la teva participació i contribució a la comunitat. Guanyes karma quan altres usuaris voten positivament els teus enviaments i comentaris. El karma t\'ajuda a desbloquejar assoliments i demostra la teva reputació a la plataforma.',
        ],
        'how_to_earn' => [
            'question' => 'Com puc guanyar karma?',
            'answer' => 'Pots guanyar karma de diverses maneres: publicant contingut de qualitat que altres usuaris votin positivament, escrivint comentaris útils o interessants, i participant activament a la comunitat. Cada vot positiu a els teus enviaments i comentaris suma punts al teu karma.',
        ],
    ],

    'seals' => [
        'what_are' => [
            'question' => 'Què són els segells?',
            'answer' => 'Els segells són marques especials que pots atorgar a enviaments i comentaris per destacar-los. Existeixen diferents tipus de segells com "M\'encanta", "Útil", "Important", etc. Els segells ajuden a la comunitat a identificar contingut valuós d\'una ullada.',
        ],
        'how_to_use' => [
            'question' => 'Com faig servir els segells?',
            'answer' => 'Per utilitzar un segell, fes clic al botó de segells que apareix a cada publicació o comentari, després selecciona el tipus de segell que vols atorgar. Pots treure un segell fent clic novament. L\'ús de segells està limitat per evitar abusos.',
        ],
    ],

    'voting' => [
        'types' => [
            'question' => 'Quins tipus de vots existeixen?',
            'answer' => 'Existeixen diversos tipus de vots: vots de qualitat (positius i negatius) que afecten el karma de l\'autor, vots d\'interès personal que no afecten al karma, i vots en enquestes. Els vots de qualitat només estan disponibles per a usuaris registrats.',
        ],
        'comments' => [
            'question' => 'Puc votar comentaris?',
            'answer' => 'Sí, pots votar tant enviaments com comentaris. Els vots en comentaris funcionen de la mateixa manera que a els enviaments: un vot positiu suma karma a l\'autor del comentari, mentre que un vot negatiu el resta. Això ajuda a destacar els comentaris més valuosos.',
        ],
    ],

    'posts' => [
        'types' => [
            'question' => 'Quins tipus d\'enviaments puc fer?',
            'answer' => 'Pots crear diversos tipus d\'enviaments: enllaços (URLs), text, imatges, vídeos, àudio, i enquestes. Cada tipus té les seves pròpies característiques. Per exemple, els enviaments de text permeten format markdown, mentre que les enquestes permeten que altres usuaris votin entre diverses opcions.',
        ],
        'anonymous' => [
            'question' => 'Puc enviar de forma anònima?',
            'answer' => 'Sí, pots marcar un enviament com a anònima en crear-la. Quan enviïs de forma anònima, el teu nom d\'usuari no apareixerà associat a la publicació. No obstant això, tingues en compte que el contingut ha de seguir complint les normes de la comunitat.',
        ],
    ],

    'lists' => [
        'favorites' => [
            'question' => 'Què són les llistes de favorits?',
            'answer' => 'La llista de favorits és una col·lecció personal on pots guardar enviaments que t\'agraden o vols tornar a veure més tard. També existeix la llista "Llegir més tard" per a enviaments que vols revisar quan tinguis temps. Aquestes llistes són privades per defecte.',
        ],
        'custom' => [
            'question' => 'Puc crear les meves pròpies llistes?',
            'answer' => 'Sí, pots crear llistes personalitzades per organitzar enviaments per temes o categories que triïs. Per exemple, pots crear llistes com "Tutorials", "Notícies importants", etc. Pots afegir notes a cada publicació dins d\'una llista.',
        ],
        'public' => [
            'question' => 'Puc fer les meves llistes públiques?',
            'answer' => 'Sí, quan crees una llista personalitzada pots marcar-la com a pública. Les llistes públiques poden ser vistes per altres usuaris, la qual cosa et permet compartir col·leccions de contingut interessant. Només tu pots afegir o treure enviaments de les teves llistes, fins i tot si són públiques.',
        ],
    ],

    'community' => [
        'subs' => [
            'question' => 'Què són els subs?',
            'answer' => 'Els subs són subcomunitats temàtiques on pots enviar contingut específic. Cada sub té el seu propi tema, regles i moderadors. Pots unir-te als subs que t\'interessin per veure el seu contingut al teu feed personalitzat.',
        ],
        'moderation' => [
            'question' => 'Com funciona la moderació?',
            'answer' => 'Cada sub té moderadors que s\'encarreguen de revisar les enviaments i assegurar que compleixin les regles. Els moderadors poden aprovar, rebutjar o eliminar publicacions, així com gestionar informes d\'usuaris. La moderació ajuda a mantenir la qualitat del contingut.',
        ],
        'rules' => [
            'question' => 'On puc veure les regles?',
            'answer' => 'Cada sub té les seves pròpies regles que pots veure a la seva pàgina principal. També existeixen regles generals de la plataforma que apliquen a tots els usuaris. És important llegir les regles abans de enviar per evitar que el teu contingut sigui rebutjat.',
        ],
    ],

    'account' => [
        'privacy' => [
            'question' => 'Com protegeixo la meva privadesa?',
            'answer' => 'Pots controlar la teva privadesa de diverses maneres: enviar de forma anònima, fer les teves llistes privades, i gestionar quina informació comparteixes al teu perfil. La teva adreça de correu electrònic mai és visible públicament i pots triar si mostrar o no el teu karma i assoliments.',
        ],
        'delete' => [
            'question' => 'Puc eliminar el meu compte?',
            'answer' => 'Sí, pots eliminar el teu compte des de la configuració del teu perfil. En eliminar el teu compte, s\'esborraran totes les teves dades personals. Tingues en compte que els teus enviaments i comentaris poden romandre però apareixeran com d\'usuari eliminat.',
        ],
        'notifications' => [
            'question' => 'Com gestiono les notificacions?',
            'answer' => 'Pots configurar les teves preferències de notificacions des del teu perfil. Pots triar rebre notificacions per nous comentaris als teus posts, respostes als teus comentaris, mencions, i missatges del sistema. També pots desactivar totes les notificacions si ho prefereixes.',
        ],
    ],

    'relationships' => [
        'what_are' => [
            'question' => 'Què són les relacions entre enviaments?',
            'answer' => 'Les relacions entre enviaments et permeten vincular enviaments relacionades. Per exemple, pots marcar un post com a "continuació" d\'un altre, o com "relacionat amb" un altre tema. Això ajuda a crear fils de conversa i permet seguir temes que es desenvolupen en múltiples posts.',
        ],
        'types' => [
            'question' => 'Quins tipus de relacions existeixen?',
            'answer' => 'Existeixen diversos tipus de relacions: "Continuació" per a posts que segueixen una història, "Relacionat amb" per a temes similars, "Actualització" per a noves versions, "Respon a" per a rèpliques, i més. Els usuaris poden votar les relacions per validar si són apropiades.',
        ],
    ],

    'search' => [
        'how_to' => [
            'question' => 'Com cerco contingut?',
            'answer' => 'Pots fer servir la barra de cerca a la part superior per trobar posts per títol, contingut o autor. La cerca suporta paraules clau i frases. També pots filtrar els resultats per tipus de contingut, data, sub, i més.',
        ],
        'filters' => [
            'question' => 'Quins filtres de cerca hi ha disponibles?',
            'answer' => 'Pots filtrar per tipus de contingut (text, imatge, vídeo, àudio), per sub, per rang de dates, per puntuació mínima, i per autor. També pots ordenar els resultats per rellevància, data, o popularitat. Els filtres t\'ajuden a trobar exactament el que cerques.',
        ],
    ],
];
