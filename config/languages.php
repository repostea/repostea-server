<?php

declare(strict_types=1);

// Parse ACTIVE_LANGUAGES from env (comma-separated list)
$activeLanguages = array_filter(
    array_map('trim', explode(',', env('ACTIVE_LANGUAGES', 'es'))),
);

// config/languages.php
return [
    'active_languages' => $activeLanguages,

    'available' => [
        // Europa
        'es' => [
            'name' => 'Spanish',
            'native' => 'Español',
            'flag' => '🇪🇸',
            'active' => true,
        ],
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'flag' => '🇬🇧',
            'active' => false,
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'flag' => '🇫🇷',
            'active' => false,
        ],
        'de' => [
            'name' => 'German',
            'native' => 'Deutsch',
            'flag' => '🇩🇪',
            'active' => false,
        ],
        'it' => [
            'name' => 'Italian',
            'native' => 'Italiano',
            'flag' => '🇮🇹',
            'active' => false,
        ],
        'pt' => [
            'name' => 'Portuguese',
            'native' => 'Português',
            'flag' => '🇵🇹',
            'active' => false,
        ],
        'nl' => [
            'name' => 'Dutch',
            'native' => 'Nederlands',
            'flag' => '🇳🇱',
            'active' => false,
        ],
        'ru' => [
            'name' => 'Russian',
            'native' => 'Русский',
            'flag' => '🇷🇺',
            'active' => false,
        ],
        'pl' => [
            'name' => 'Polish',
            'native' => 'Polski',
            'flag' => '🇵🇱',
            'active' => false,
        ],
        'sv' => [
            'name' => 'Swedish',
            'native' => 'Svenska',
            'flag' => '🇸🇪',
            'active' => false,
        ],
        'da' => [
            'name' => 'Danish',
            'native' => 'Dansk',
            'flag' => '🇩🇰',
            'active' => false,
        ],
        'no' => [
            'name' => 'Norwegian',
            'native' => 'Norsk',
            'flag' => '🇳🇴',
            'active' => false,
        ],
        'fi' => [
            'name' => 'Finnish',
            'native' => 'Suomi',
            'flag' => '🇫🇮',
            'active' => false,
        ],
        'ro' => [
            'name' => 'Romanian',
            'native' => 'Română',
            'flag' => '🇷🇴',
            'active' => false,
        ],
        'cs' => [
            'name' => 'Czech',
            'native' => 'Čeština',
            'flag' => '🇨🇿',
            'active' => false,
        ],
        'hu' => [
            'name' => 'Hungarian',
            'native' => 'Magyar',
            'flag' => '🇭🇺',
            'active' => false,
        ],
        'el' => [
            'name' => 'Greek',
            'native' => 'Ελληνικά',
            'flag' => '🇬🇷',
            'active' => false,
        ],
        'ca' => [
            'name' => 'Catalan',
            'native' => 'Català',
            'flag' => '🏴',
            'active' => true,
        ],
        'eu' => [
            'name' => 'Basque',
            'native' => 'Euskara',
            'flag' => '🏴',
            'active' => true,
        ],
        'gl' => [
            'name' => 'Galician',
            'native' => 'Galego',
            'flag' => '🏴',
            'active' => true,
        ],
        'ast' => [
            'name' => 'Asturian',
            'native' => 'Asturianu',
            'flag' => '🏴',
            'active' => false,
        ],
        'an' => [
            'name' => 'Aragonese',
            'native' => 'Aragonés',
            'flag' => '🏴',
            'active' => false,
        ],
        'uk' => [
            'name' => 'Ukrainian',
            'native' => 'Українська',
            'flag' => '🇺🇦',
            'active' => false,
        ],
        'bg' => [
            'name' => 'Bulgarian',
            'native' => 'Български',
            'flag' => '🇧🇬',
            'active' => false,
        ],
        'sr' => [
            'name' => 'Serbian',
            'native' => 'Српски',
            'flag' => '🇷🇸',
            'active' => false,
        ],
        'hr' => [
            'name' => 'Croatian',
            'native' => 'Hrvatski',
            'flag' => '🇭🇷',
            'active' => false,
        ],
        'sk' => [
            'name' => 'Slovak',
            'native' => 'Slovenčina',
            'flag' => '🇸🇰',
            'active' => false,
        ],
        'sl' => [
            'name' => 'Slovenian',
            'native' => 'Slovenščina',
            'flag' => '🇸🇮',
            'active' => false,
        ],

        // Asia
        'zh' => [
            'name' => 'Chinese (Simplified)',
            'native' => '中文',
            'flag' => '🇨🇳',
            'active' => false,
        ],
        'ja' => [
            'name' => 'Japanese',
            'native' => '日本語',
            'flag' => '🇯🇵',
            'active' => false,
        ],
        'ko' => [
            'name' => 'Korean',
            'native' => '한국어',
            'flag' => '🇰🇷',
            'active' => false,
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'flag' => '🇸🇦',
            'active' => false,
        ],
        'hi' => [
            'name' => 'Hindi',
            'native' => 'हिन्दी',
            'flag' => '🇮🇳',
            'active' => false,
        ],
        'tr' => [
            'name' => 'Turkish',
            'native' => 'Türkçe',
            'flag' => '🇹🇷',
            'active' => false,
        ],
        'he' => [
            'name' => 'Hebrew',
            'native' => 'עברית',
            'flag' => '🇮🇱',
            'active' => false,
        ],

        // Otras regiones
        'id' => [
            'name' => 'Indonesian',
            'native' => 'Bahasa Indonesia',
            'flag' => '🇮🇩',
            'active' => false,
        ],
        'vi' => [
            'name' => 'Vietnamese',
            'native' => 'Tiếng Việt',
            'flag' => '🇻🇳',
            'active' => false,
        ],
        'th' => [
            'name' => 'Thai',
            'native' => 'ไทย',
            'flag' => '🇹🇭',
            'active' => false,
        ],
        'fa' => [
            'name' => 'Persian',
            'native' => 'فارسی',
            'flag' => '🇮🇷',
            'active' => false,
        ],
        'ur' => [
            'name' => 'Urdu',
            'native' => 'اردو',
            'flag' => '🇵🇰',
            'active' => false,
        ],
        'bn' => [
            'name' => 'Bengali',
            'native' => 'বাংলা',
            'flag' => '🇧🇩',
            'active' => false,
        ],
        'ms' => [
            'name' => 'Malay',
            'native' => 'Bahasa Melayu',
            'flag' => '🇲🇾',
            'active' => false,
        ],
        'tl' => [
            'name' => 'Filipino',
            'native' => 'Filipino',
            'flag' => '🇵🇭',
            'active' => false,
        ],

        // Americas
        'pt-br' => [
            'name' => 'Portuguese (Brazil)',
            'native' => 'Português (Brasil)',
            'flag' => '🇧🇷',
            'active' => false,
        ],

        // Africa
        'sw' => [
            'name' => 'Swahili',
            'native' => 'Kiswahili',
            'flag' => '🇰🇪',
            'active' => false,
        ],
        'af' => [
            'name' => 'Afrikaans',
            'native' => 'Afrikaans',
            'flag' => '🇿🇦',
            'active' => false,
        ],
    ],
];
