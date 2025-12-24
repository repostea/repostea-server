<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'El camp :attribute ha de ser acceptat.',
    'accepted_if' => 'El camp :attribute ha de ser acceptat quan :other sigui :value.',
    'active_url' => 'El camp :attribute no és una URL vàlida.',
    'after' => 'El camp :attribute ha de ser una data posterior a :date.',
    'after_or_equal' => 'El camp :attribute ha de ser una data posterior o igual a :date.',
    'alpha' => 'El camp :attribute només pot contenir lletres.',
    'alpha_dash' => 'El camp :attribute només pot contenir lletres, números, guions i guions baixos.',
    'alpha_num' => 'El camp :attribute només pot contenir lletres i números.',
    'array' => 'El camp :attribute ha de ser un array.',
    'ascii' => 'El camp :attribute només pot contenir caràcters alfanumèrics i símbols d\'un sol byte.',
    'before' => 'El camp :attribute ha de ser una data anterior a :date.',
    'before_or_equal' => 'El camp :attribute ha de ser una data anterior o igual a :date.',
    'between' => [
        'array' => 'El camp :attribute ha de tenir entre :min i :max elements.',
        'file' => 'L\'arxiu :attribute ha de pesar entre :min i :max kilobytes.',
        'numeric' => 'El camp :attribute ha d\'estar entre :min i :max.',
        'string' => 'El camp :attribute ha de tenir entre :min i :max caràcters.',
    ],
    'boolean' => 'El camp :attribute ha de ser vertader o fals.',
    'can' => 'El camp :attribute conté un valor no autoritzat.',
    'confirmed' => 'La confirmació de :attribute no coincideix.',
    'contains' => 'El camp :attribute no conté un valor requerit.',
    'current_password' => 'La contrasenya és incorrecta.',
    'date' => 'El camp :attribute no és una data vàlida.',
    'date_equals' => 'El camp :attribute ha de ser una data igual a :date.',
    'date_format' => 'El camp :attribute no coincideix amb el format :format.',
    'decimal' => 'El camp :attribute ha de tenir :decimal decimals.',
    'declined' => 'El camp :attribute ha de ser rebutjat.',
    'declined_if' => 'El camp :attribute ha de ser rebutjat quan :other sigui :value.',
    'different' => 'Els camps :attribute i :other han de ser diferents.',
    'digits' => 'El camp :attribute ha de tenir :digits dígits.',
    'digits_between' => 'El camp :attribute ha de tenir entre :min i :max dígits.',
    'dimensions' => 'El camp :attribute té dimensions d\'imatge invàlides.',
    'distinct' => 'El camp :attribute té un valor duplicat.',
    'doesnt_end_with' => 'El camp :attribute no pot acabar amb un dels següents valors: :values.',
    'doesnt_start_with' => 'El camp :attribute no pot començar amb un dels següents valors: :values.',
    'email' => 'El camp :attribute ha de ser una adreça de correu electrònic vàlida.',
    'ends_with' => 'El camp :attribute ha d\'acabar amb un dels següents valors: :values.',
    'enum' => 'El valor seleccionat per a :attribute és invàlid.',
    'exists' => 'El valor seleccionat per a :attribute és invàlid.',
    'extensions' => 'El camp :attribute ha de tenir una de les següents extensions: :values.',
    'file' => 'El camp :attribute ha de ser un arxiu.',
    'filled' => 'El camp :attribute ha de tenir un valor.',
    'gt' => [
        'array' => 'El camp :attribute ha de tenir més de :value elements.',
        'file' => 'L\'arxiu :attribute ha de pesar més de :value kilobytes.',
        'numeric' => 'El camp :attribute ha de ser més gran que :value.',
        'string' => 'El camp :attribute ha de tenir més de :value caràcters.',
    ],
    'gte' => [
        'array' => 'El camp :attribute ha de tenir :value elements o més.',
        'file' => 'L\'arxiu :attribute ha de pesar :value kilobytes o més.',
        'numeric' => 'El camp :attribute ha de ser més gran o igual que :value.',
        'string' => 'El camp :attribute ha de tenir :value caràcters o més.',
    ],
    'hex_color' => 'El camp :attribute ha de ser un color hexadecimal vàlid.',
    'image' => 'El camp :attribute ha de ser una imatge.',
    'in' => 'El valor seleccionat per a :attribute és invàlid.',
    'in_array' => 'El camp :attribute no existeix a :other.',
    'integer' => 'El camp :attribute ha de ser un número enter.',
    'ip' => 'El camp :attribute ha de ser una adreça IP vàlida.',
    'ipv4' => 'El camp :attribute ha de ser una adreça IPv4 vàlida.',
    'ipv6' => 'El camp :attribute ha de ser una adreça IPv6 vàlida.',
    'json' => 'El camp :attribute ha de ser una cadena JSON vàlida.',
    'list' => 'El camp :attribute ha de ser una llista.',
    'lowercase' => 'El camp :attribute ha d\'estar en minúscules.',
    'lt' => [
        'array' => 'El camp :attribute ha de tenir menys de :value elements.',
        'file' => 'L\'arxiu :attribute ha de pesar menys de :value kilobytes.',
        'numeric' => 'El camp :attribute ha de ser menor que :value.',
        'string' => 'El camp :attribute ha de tenir menys de :value caràcters.',
    ],
    'lte' => [
        'array' => 'El camp :attribute ha de tenir :value elements o menys.',
        'file' => 'L\'arxiu :attribute ha de pesar :value kilobytes o menys.',
        'numeric' => 'El camp :attribute ha de ser menor o igual que :value.',
        'string' => 'El camp :attribute ha de tenir :value caràcters o menys.',
    ],
    'mac_address' => 'El camp :attribute ha de ser una adreça MAC vàlida.',
    'max' => [
        'array' => 'El camp :attribute no ha de tenir més de :max elements.',
        'file' => 'L\'arxiu :attribute no ha de pesar més de :max kilobytes.',
        'numeric' => 'El camp :attribute no ha de ser més gran que :max.',
        'string' => 'El camp :attribute no ha de tenir més de :max caràcters.',
    ],
    'max_digits' => 'El camp :attribute no ha de tenir més de :max dígits.',
    'mimes' => 'El camp :attribute ha de ser un arxiu de tipus: :values.',
    'mimetypes' => 'El camp :attribute ha de ser un arxiu de tipus: :values.',
    'min' => [
        'array' => 'El camp :attribute ha de tenir almenys :min elements.',
        'file' => 'L\'arxiu :attribute ha de pesar almenys :min kilobytes.',
        'numeric' => 'El camp :attribute ha de ser almenys :min.',
        'string' => 'El camp :attribute ha de tenir almenys :min caràcters.',
    ],
    'min_digits' => 'El camp :attribute ha de tenir almenys :min dígits.',
    'missing' => 'El camp :attribute ha de faltar.',
    'missing_if' => 'El camp :attribute ha de faltar quan :other sigui :value.',
    'missing_unless' => 'El camp :attribute ha de faltar tret que :other sigui :value.',
    'missing_with' => 'El camp :attribute ha de faltar quan :values estigui present.',
    'missing_with_all' => 'El camp :attribute ha de faltar quan :values estiguin presents.',
    'multiple_of' => 'El camp :attribute ha de ser múltiple de :value.',
    'not_in' => 'El valor seleccionat per a :attribute és invàlid.',
    'not_regex' => 'El format del camp :attribute és invàlid.',
    'numeric' => 'El camp :attribute ha de ser un número.',
    'password' => [
        'letters' => 'El camp :attribute ha de contenir almenys una lletra.',
        'mixed' => 'El camp :attribute ha de contenir almenys una lletra majúscula i una minúscula.',
        'numbers' => 'El camp :attribute ha de contenir almenys un número.',
        'symbols' => 'El camp :attribute ha de contenir almenys un símbol.',
        'uncompromised' => 'El :attribute proporcionat ha aparegut en una filtració de dades. Si us plau, tria un :attribute diferent.',
    ],
    'present' => 'El camp :attribute ha d\'estar present.',
    'present_if' => 'El camp :attribute ha d\'estar present quan :other sigui :value.',
    'present_unless' => 'El camp :attribute ha d\'estar present tret que :other sigui :value.',
    'present_with' => 'El camp :attribute ha d\'estar present quan :values estigui present.',
    'present_with_all' => 'El camp :attribute ha d\'estar present quan :values estiguin presents.',
    'prohibited' => 'El camp :attribute està prohibit.',
    'prohibited_if' => 'El camp :attribute està prohibit quan :other sigui :value.',
    'prohibited_unless' => 'El camp :attribute està prohibit tret que :other estigui a :values.',
    'prohibits' => 'El camp :attribute prohibeix que :other estigui present.',
    'regex' => 'El format del camp :attribute és invàlid.',
    'required' => 'El camp :attribute és obligatori.',
    'required_array_keys' => 'El camp :attribute ha de contenir entrades per a: :values.',
    'required_if' => 'El camp :attribute és obligatori quan :other sigui :value.',
    'required_if_accepted' => 'El camp :attribute és obligatori quan :other sigui acceptat.',
    'required_if_declined' => 'El camp :attribute és obligatori quan :other sigui rebutjat.',
    'required_unless' => 'El camp :attribute és obligatori tret que :other estigui a :values.',
    'required_with' => 'El camp :attribute és obligatori quan :values estigui present.',
    'required_with_all' => 'El camp :attribute és obligatori quan :values estiguin presents.',
    'required_without' => 'El camp :attribute és obligatori quan :values no estigui present.',
    'required_without_all' => 'El camp :attribute és obligatori quan cap de :values estigui present.',
    'same' => 'Els camps :attribute i :other han de coincidir.',
    'size' => [
        'array' => 'El camp :attribute ha de contenir :size elements.',
        'file' => 'L\'arxiu :attribute ha de pesar :size kilobytes.',
        'numeric' => 'El camp :attribute ha de ser :size.',
        'string' => 'El camp :attribute ha de tenir :size caràcters.',
    ],
    'starts_with' => 'El camp :attribute ha de començar amb un dels següents valors: :values.',
    'string' => 'El camp :attribute ha de ser una cadena de text.',
    'timezone' => 'El camp :attribute ha de ser una zona horària vàlida.',
    'unique' => 'El valor del camp :attribute ja està en ús.',
    'uploaded' => 'El camp :attribute no s\'ha pogut pujar.',
    'uppercase' => 'El camp :attribute ha d\'estar en majúscules.',
    'url' => 'El camp :attribute ha de ser una URL vàlida.',
    'ulid' => 'El camp :attribute ha de ser un ULID vàlid.',
    'uuid' => 'El camp :attribute ha de ser un UUID vàlid.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'username' => 'nom d\'usuari',
        'email' => 'correu electrònic',
        'password' => 'contrasenya',
        'password_confirmation' => 'confirmació de contrasenya',
        'invitation' => 'codi d\'invitació',
        'cf-turnstile-response' => 'verificació captcha',
    ],

    // Post validation (existing custom messages)
    'post' => [
        'title_required' => 'El títol és obligatori',
        'title_min' => 'El títol ha de tenir almenys :min caràcters',
        'title_max' => 'El títol no pot excedir :max caràcters',
        'url_invalid' => 'La URL no és vàlida',
        'url_required' => 'La URL és obligatòria per a aquest tipus de contingut',
        'content_required' => 'El contingut és obligatori per a publicacions de text',
        'category_required' => 'Has de seleccionar una categoria',
        'category_exists' => 'La categoria seleccionada no existeix',
        'content_type_in' => 'El tipus de contingut ha de ser enllaç, text, vídeo, àudio, imatge o enquesta',
    ],
];
