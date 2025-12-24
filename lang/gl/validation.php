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

    'accepted' => 'O campo :attribute debe ser aceptado.',
    'accepted_if' => 'O campo :attribute debe ser aceptado cando :other sexa :value.',
    'active_url' => 'O campo :attribute non é unha URL válida.',
    'after' => 'O campo :attribute debe ser unha data posterior a :date.',
    'after_or_equal' => 'O campo :attribute debe ser unha data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute só pode conter letras.',
    'alpha_dash' => 'O campo :attribute só pode conter letras, números, guións e guións baixos.',
    'alpha_num' => 'O campo :attribute só pode conter letras e números.',
    'array' => 'O campo :attribute debe ser un array.',
    'ascii' => 'O campo :attribute só pode conter caracteres alfanuméricos e símbolos dun só byte.',
    'before' => 'O campo :attribute debe ser unha data anterior a :date.',
    'before_or_equal' => 'O campo :attribute debe ser unha data anterior ou igual a :date.',
    'between' => [
        'array' => 'O campo :attribute debe ter entre :min e :max elementos.',
        'file' => 'O arquivo :attribute debe pesar entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute debe estar entre :min e :max.',
        'string' => 'O campo :attribute debe ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute debe ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contén un valor non autorizado.',
    'confirmed' => 'A confirmación de :attribute non coincide.',
    'contains' => 'O campo :attribute non contén un valor requirido.',
    'current_password' => 'O contrasinal é incorrecto.',
    'date' => 'O campo :attribute non é unha data válida.',
    'date_equals' => 'O campo :attribute debe ser unha data igual a :date.',
    'date_format' => 'O campo :attribute non coincide co formato :format.',
    'decimal' => 'O campo :attribute debe ter :decimal decimais.',
    'declined' => 'O campo :attribute debe ser rexeitado.',
    'declined_if' => 'O campo :attribute debe ser rexeitado cando :other sexa :value.',
    'different' => 'Os campos :attribute e :other deben ser diferentes.',
    'digits' => 'O campo :attribute debe ter :digits díxitos.',
    'digits_between' => 'O campo :attribute debe ter entre :min e :max díxitos.',
    'dimensions' => 'O campo :attribute ten dimensións de imaxe inválidas.',
    'distinct' => 'O campo :attribute ten un valor duplicado.',
    'doesnt_end_with' => 'O campo :attribute non pode rematar con un dos seguintes valores: :values.',
    'doesnt_start_with' => 'O campo :attribute non pode comezar con un dos seguintes valores: :values.',
    'email' => 'O campo :attribute debe ser un enderezo de correo electrónico válido.',
    'ends_with' => 'O campo :attribute debe rematar con un dos seguintes valores: :values.',
    'enum' => 'O valor seleccionado para :attribute é inválido.',
    'exists' => 'O valor seleccionado para :attribute é inválido.',
    'extensions' => 'O campo :attribute debe ter unha das seguintes extensións: :values.',
    'file' => 'O campo :attribute debe ser un arquivo.',
    'filled' => 'O campo :attribute debe ter un valor.',
    'gt' => [
        'array' => 'O campo :attribute debe ter máis de :value elementos.',
        'file' => 'O arquivo :attribute debe pesar máis de :value kilobytes.',
        'numeric' => 'O campo :attribute debe ser maior que :value.',
        'string' => 'O campo :attribute debe ter máis de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute debe ter :value elementos ou máis.',
        'file' => 'O arquivo :attribute debe pesar :value kilobytes ou máis.',
        'numeric' => 'O campo :attribute debe ser maior ou igual que :value.',
        'string' => 'O campo :attribute debe ter :value caracteres ou máis.',
    ],
    'hex_color' => 'O campo :attribute debe ser unha cor hexadecimal válida.',
    'image' => 'O campo :attribute debe ser unha imaxe.',
    'in' => 'O valor seleccionado para :attribute é inválido.',
    'in_array' => 'O campo :attribute non existe en :other.',
    'integer' => 'O campo :attribute debe ser un número enteiro.',
    'ip' => 'O campo :attribute debe ser un enderezo IP válido.',
    'ipv4' => 'O campo :attribute debe ser un enderezo IPv4 válido.',
    'ipv6' => 'O campo :attribute debe ser un enderezo IPv6 válido.',
    'json' => 'O campo :attribute debe ser unha cadea JSON válida.',
    'list' => 'O campo :attribute debe ser unha lista.',
    'lowercase' => 'O campo :attribute debe estar en minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute debe ter menos de :value elementos.',
        'file' => 'O arquivo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'O campo :attribute debe ser menor que :value.',
        'string' => 'O campo :attribute debe ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute debe ter :value elementos ou menos.',
        'file' => 'O arquivo :attribute debe pesar :value kilobytes ou menos.',
        'numeric' => 'O campo :attribute debe ser menor ou igual que :value.',
        'string' => 'O campo :attribute debe ter :value caracteres ou menos.',
    ],
    'mac_address' => 'O campo :attribute debe ser un enderezo MAC válido.',
    'max' => [
        'array' => 'O campo :attribute non debe ter máis de :max elementos.',
        'file' => 'O arquivo :attribute non debe pesar máis de :max kilobytes.',
        'numeric' => 'O campo :attribute non debe ser maior que :max.',
        'string' => 'O campo :attribute non debe ter máis de :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute non debe ter máis de :max díxitos.',
    'mimes' => 'O campo :attribute debe ser un arquivo de tipo: :values.',
    'mimetypes' => 'O campo :attribute debe ser un arquivo de tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute debe ter polo menos :min elementos.',
        'file' => 'O arquivo :attribute debe pesar polo menos :min kilobytes.',
        'numeric' => 'O campo :attribute debe ser polo menos :min.',
        'string' => 'O campo :attribute debe ter polo menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute debe ter polo menos :min díxitos.',
    'missing' => 'O campo :attribute debe faltar.',
    'missing_if' => 'O campo :attribute debe faltar cando :other sexa :value.',
    'missing_unless' => 'O campo :attribute debe faltar agás que :other sexa :value.',
    'missing_with' => 'O campo :attribute debe faltar cando :values estea presente.',
    'missing_with_all' => 'O campo :attribute debe faltar cando :values estean presentes.',
    'multiple_of' => 'O campo :attribute debe ser múltiplo de :value.',
    'not_in' => 'O valor seleccionado para :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute debe ser un número.',
    'password' => [
        'letters' => 'O campo :attribute debe conter polo menos unha letra.',
        'mixed' => 'O campo :attribute debe conter polo menos unha letra maiúscula e unha minúscula.',
        'numbers' => 'O campo :attribute debe conter polo menos un número.',
        'symbols' => 'O campo :attribute debe conter polo menos un símbolo.',
        'uncompromised' => 'O :attribute proporcionado apareceu nunha filtración de datos. Por favor, escolle un :attribute diferente.',
    ],
    'present' => 'O campo :attribute debe estar presente.',
    'present_if' => 'O campo :attribute debe estar presente cando :other sexa :value.',
    'present_unless' => 'O campo :attribute debe estar presente agás que :other sexa :value.',
    'present_with' => 'O campo :attribute debe estar presente cando :values estea presente.',
    'present_with_all' => 'O campo :attribute debe estar presente cando :values estean presentes.',
    'prohibited' => 'O campo :attribute está prohibido.',
    'prohibited_if' => 'O campo :attribute está prohibido cando :other sexa :value.',
    'prohibited_unless' => 'O campo :attribute está prohibido agás que :other estea en :values.',
    'prohibits' => 'O campo :attribute prohibe que :other estea presente.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatorio.',
    'required_array_keys' => 'O campo :attribute debe conter entradas para: :values.',
    'required_if' => 'O campo :attribute é obrigatorio cando :other sexa :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatorio cando :other sexa aceptado.',
    'required_if_declined' => 'O campo :attribute é obrigatorio cando :other sexa rexeitado.',
    'required_unless' => 'O campo :attribute é obrigatorio agás que :other estea en :values.',
    'required_with' => 'O campo :attribute é obrigatorio cando :values estea presente.',
    'required_with_all' => 'O campo :attribute é obrigatorio cando :values estean presentes.',
    'required_without' => 'O campo :attribute é obrigatorio cando :values non estea presente.',
    'required_without_all' => 'O campo :attribute é obrigatorio cando ningún de :values estea presente.',
    'same' => 'Os campos :attribute e :other deben coincidir.',
    'size' => [
        'array' => 'O campo :attribute debe conter :size elementos.',
        'file' => 'O arquivo :attribute debe pesar :size kilobytes.',
        'numeric' => 'O campo :attribute debe ser :size.',
        'string' => 'O campo :attribute debe ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute debe comezar cun dos seguintes valores: :values.',
    'string' => 'O campo :attribute debe ser unha cadea de texto.',
    'timezone' => 'O campo :attribute debe ser unha zona horaria válida.',
    'unique' => 'O valor do campo :attribute xa está en uso.',
    'uploaded' => 'O campo :attribute non se puido subir.',
    'uppercase' => 'O campo :attribute debe estar en maiúsculas.',
    'url' => 'O campo :attribute debe ser unha URL válida.',
    'ulid' => 'O campo :attribute debe ser un ULID válido.',
    'uuid' => 'O campo :attribute debe ser un UUID válido.',

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
        'username' => 'nome de usuario',
        'email' => 'correo electrónico',
        'password' => 'contrasinal',
        'password_confirmation' => 'confirmación de contrasinal',
        'invitation' => 'código de invitación',
        'cf-turnstile-response' => 'verificación captcha',
    ],

    // Post validation (existing custom messages)
    'post' => [
        'title_required' => 'O título é obrigatorio',
        'title_min' => 'O título debe ter polo menos :min caracteres',
        'title_max' => 'O título non pode exceder :max caracteres',
        'url_invalid' => 'A URL non é válida',
        'url_required' => 'A URL é obrigatoria para este tipo de contido',
        'content_required' => 'O contido é obrigatorio para publicacións de texto',
        'category_required' => 'Debes seleccionar unha categoría',
        'category_exists' => 'A categoría seleccionada non existe',
        'content_type_in' => 'O tipo de contido debe ser ligazón, texto, vídeo, audio, imaxe ou enquisa',
    ],
];
