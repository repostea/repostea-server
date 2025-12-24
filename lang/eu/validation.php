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

    'accepted' => ':attribute eremua onartu egin behar da.',
    'accepted_if' => ':attribute eremua onartu egin behar da :other :value denean.',
    'active_url' => ':attribute eremua ez da URL baliozkoa.',
    'after' => ':attribute eremua :date ondorengo data izan behar da.',
    'after_or_equal' => ':attribute eremua :date ondorengo edo berdineko data izan behar da.',
    'alpha' => ':attribute eremua letrak bakarrik eduki ditzake.',
    'alpha_dash' => ':attribute eremua letrak, zenbakiak, marratxoak eta azpiko marratxoak bakarrik eduki ditzake.',
    'alpha_num' => ':attribute eremua letrak eta zenbakiak bakarrik eduki ditzake.',
    'array' => ':attribute eremua array bat izan behar da.',
    'ascii' => ':attribute eremua karaktere alfanumerikoak eta byte bakarreko ikurrak bakarrik eduki ditzake.',
    'before' => ':attribute eremua :date aurreko data izan behar da.',
    'before_or_equal' => ':attribute eremua :date aurreko edo berdineko data izan behar da.',
    'between' => [
        'array' => ':attribute eremua :min eta :max elementu artean izan behar ditu.',
        'file' => ':attribute fitxategiak :min eta :max kilobyte artean pisatu behar du.',
        'numeric' => ':attribute eremua :min eta :max artean egon behar da.',
        'string' => ':attribute eremua :min eta :max karaktere artean izan behar ditu.',
    ],
    'boolean' => ':attribute eremua egia edo gezurra izan behar da.',
    'can' => ':attribute eremua balio baimenik gabea dauka.',
    'confirmed' => ':attribute berrespenak ez du bat egiten.',
    'contains' => ':attribute eremua beharrezko balio bat ez dauka.',
    'current_password' => 'Pasahitza okerra da.',
    'date' => ':attribute eremua ez da data baliozkoa.',
    'date_equals' => ':attribute eremua :date dataren berdina izan behar da.',
    'date_format' => ':attribute eremua ez dator bat :format formatuarekin.',
    'decimal' => ':attribute eremua :decimal dezimal izan behar ditu.',
    'declined' => ':attribute eremua baztertu egin behar da.',
    'declined_if' => ':attribute eremua baztertu egin behar da :other :value denean.',
    'different' => ':attribute eta :other eremuak desberdinak izan behar dira.',
    'digits' => ':attribute eremua :digits digitu izan behar ditu.',
    'digits_between' => ':attribute eremua :min eta :max digitu artean izan behar ditu.',
    'dimensions' => ':attribute eremuak irudi dimentsio baliogabeak ditu.',
    'distinct' => ':attribute eremua balio bikoiztua dauka.',
    'doesnt_end_with' => ':attribute eremua ezin da amaitu honako balio hauekin: :values.',
    'doesnt_start_with' => ':attribute eremua ezin da hasi honako balio hauekin: :values.',
    'email' => ':attribute eremua posta elektroniko helbide baliozkoa izan behar da.',
    'ends_with' => ':attribute eremua honako balio hauekin amaitu behar da: :values.',
    'enum' => 'Hautatutako :attribute baliogabea da.',
    'exists' => 'Hautatutako :attribute baliogabea da.',
    'extensions' => ':attribute eremua honako luzapen hauetako bat izan behar du: :values.',
    'file' => ':attribute eremua fitxategi bat izan behar da.',
    'filled' => ':attribute eremua balio bat izan behar du.',
    'gt' => [
        'array' => ':attribute eremua :value elementu baino gehiago izan behar ditu.',
        'file' => ':attribute fitxategiak :value kilobyte baino gehiago pisatu behar du.',
        'numeric' => ':attribute eremua :value baino handiagoa izan behar da.',
        'string' => ':attribute eremua :value karaktere baino gehiago izan behar ditu.',
    ],
    'gte' => [
        'array' => ':attribute eremua :value elementu edo gehiago izan behar ditu.',
        'file' => ':attribute fitxategiak :value kilobyte edo gehiago pisatu behar du.',
        'numeric' => ':attribute eremua :value edo handiagoa izan behar da.',
        'string' => ':attribute eremua :value karaktere edo gehiago izan behar ditu.',
    ],
    'hex_color' => ':attribute eremua kolore hexadezimal baliozkoa izan behar da.',
    'image' => ':attribute eremua irudi bat izan behar da.',
    'in' => 'Hautatutako :attribute baliogabea da.',
    'in_array' => ':attribute eremua ez dago :other-n.',
    'integer' => ':attribute eremua zenbaki oso bat izan behar da.',
    'ip' => ':attribute eremua IP helbide baliozkoa izan behar da.',
    'ipv4' => ':attribute eremua IPv4 helbide baliozkoa izan behar da.',
    'ipv6' => ':attribute eremua IPv6 helbide baliozkoa izan behar da.',
    'json' => ':attribute eremua JSON kate baliozkoa izan behar da.',
    'list' => ':attribute eremua zerrenda bat izan behar da.',
    'lowercase' => ':attribute eremua minuskuletan egon behar da.',
    'lt' => [
        'array' => ':attribute eremua :value elementu baino gutxiago izan behar ditu.',
        'file' => ':attribute fitxategiak :value kilobyte baino gutxiago pisatu behar du.',
        'numeric' => ':attribute eremua :value baino txikiagoa izan behar da.',
        'string' => ':attribute eremua :value karaktere baino gutxiago izan behar ditu.',
    ],
    'lte' => [
        'array' => ':attribute eremua :value elementu edo gutxiago izan behar ditu.',
        'file' => ':attribute fitxategiak :value kilobyte edo gutxiago pisatu behar du.',
        'numeric' => ':attribute eremua :value edo txikiagoa izan behar da.',
        'string' => ':attribute eremua :value karaktere edo gutxiago izan behar ditu.',
    ],
    'mac_address' => ':attribute eremua MAC helbide baliozkoa izan behar da.',
    'max' => [
        'array' => ':attribute eremua ez du :max elementu baino gehiago izan behar.',
        'file' => ':attribute fitxategiak ez du :max kilobyte baino gehiago pisatu behar.',
        'numeric' => ':attribute eremua ez da :max baino handiagoa izan behar.',
        'string' => ':attribute eremua ez du :max karaktere baino gehiago izan behar.',
    ],
    'max_digits' => ':attribute eremua ez du :max digitu baino gehiago izan behar.',
    'mimes' => ':attribute eremua mota honetako fitxategia izan behar da: :values.',
    'mimetypes' => ':attribute eremua mota honetako fitxategia izan behar da: :values.',
    'min' => [
        'array' => ':attribute eremua gutxienez :min elementu izan behar ditu.',
        'file' => ':attribute fitxategiak gutxienez :min kilobyte pisatu behar du.',
        'numeric' => ':attribute eremua gutxienez :min izan behar da.',
        'string' => ':attribute eremua gutxienez :min karaktere izan behar ditu.',
    ],
    'min_digits' => ':attribute eremua gutxienez :min digitu izan behar ditu.',
    'missing' => ':attribute eremua falta izan behar da.',
    'missing_if' => ':attribute eremua falta izan behar da :other :value denean.',
    'missing_unless' => ':attribute eremua falta izan behar da :other :value ez bada.',
    'missing_with' => ':attribute eremua falta izan behar da :values presente dagoenean.',
    'missing_with_all' => ':attribute eremua falta izan behar da :values presente daudenean.',
    'multiple_of' => ':attribute eremua :value-ren multiploa izan behar da.',
    'not_in' => 'Hautatutako :attribute baliogabea da.',
    'not_regex' => ':attribute eremuaren formatua baliogabea da.',
    'numeric' => ':attribute eremua zenbaki bat izan behar da.',
    'password' => [
        'letters' => ':attribute eremua gutxienez letra bat eduki behar du.',
        'mixed' => ':attribute eremua gutxienez letra larri bat eta letra xehe bat eduki behar ditu.',
        'numbers' => ':attribute eremua gutxienez zenbaki bat eduki behar du.',
        'symbols' => ':attribute eremua gutxienez ikur bat eduki behar du.',
        'uncompromised' => 'Emandako :attribute datu ihesaldi batean agertu da. Mesedez, aukeratu :attribute desberdin bat.',
    ],
    'present' => ':attribute eremua presente egon behar da.',
    'present_if' => ':attribute eremua presente egon behar da :other :value denean.',
    'present_unless' => ':attribute eremua presente egon behar da :other :value ez bada.',
    'present_with' => ':attribute eremua presente egon behar da :values presente dagoenean.',
    'present_with_all' => ':attribute eremua presente egon behar da :values presente daudenean.',
    'prohibited' => ':attribute eremua debekatuta dago.',
    'prohibited_if' => ':attribute eremua debekatuta dago :other :value denean.',
    'prohibited_unless' => ':attribute eremua debekatuta dago :other :values-n ez badago.',
    'prohibits' => ':attribute eremuak :other presente egotea debekatzen du.',
    'regex' => ':attribute eremuaren formatua baliogabea da.',
    'required' => ':attribute eremua beharrezkoa da.',
    'required_array_keys' => ':attribute eremua sarrerak eduki behar ditu: :values-rentzat.',
    'required_if' => ':attribute eremua beharrezkoa da :other :value denean.',
    'required_if_accepted' => ':attribute eremua beharrezkoa da :other onartuta dagoenean.',
    'required_if_declined' => ':attribute eremua beharrezkoa da :other baztertuta dagoenean.',
    'required_unless' => ':attribute eremua beharrezkoa da :other :values-n ez badago.',
    'required_with' => ':attribute eremua beharrezkoa da :values presente dagoenean.',
    'required_with_all' => ':attribute eremua beharrezkoa da :values presente daudenean.',
    'required_without' => ':attribute eremua beharrezkoa da :values presente ez dagoenean.',
    'required_without_all' => ':attribute eremua beharrezkoa da :values inor presente ez dagoenean.',
    'same' => ':attribute eta :other eremuak bat etorri behar dute.',
    'size' => [
        'array' => ':attribute eremua :size elementu eduki behar ditu.',
        'file' => ':attribute fitxategiak :size kilobyte pisatu behar du.',
        'numeric' => ':attribute eremua :size izan behar da.',
        'string' => ':attribute eremua :size karaktere izan behar ditu.',
    ],
    'starts_with' => ':attribute eremua honako balio hauekin hasi behar da: :values.',
    'string' => ':attribute eremua testu kate bat izan behar da.',
    'timezone' => ':attribute eremua ordu zona baliozkoa izan behar da.',
    'unique' => ':attribute eremuaren balioa dagoeneko erabiltzen ari da.',
    'uploaded' => ':attribute eremua ezin izan da igo.',
    'uppercase' => ':attribute eremua maiuskuletan egon behar da.',
    'url' => ':attribute eremua URL baliozkoa izan behar da.',
    'ulid' => ':attribute eremua ULID baliozkoa izan behar da.',
    'uuid' => ':attribute eremua UUID baliozkoa izan behar da.',

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
        'username' => 'erabiltzaile izena',
        'email' => 'posta elektronikoa',
        'password' => 'pasahitza',
        'password_confirmation' => 'pasahitzaren berrespena',
        'invitation' => 'gonbidapen kodea',
        'cf-turnstile-response' => 'captcha egiaztapena',
    ],

    // Post validation (existing custom messages)
    'post' => [
        'title_required' => 'Izenburua beharrezkoa da',
        'title_min' => 'Izenburuak gutxienez :min karaktere izan behar ditu',
        'title_max' => 'Izenburuak ezin du :max karaktere gainditu',
        'url_invalid' => 'URLa ez da baliozkoa',
        'url_required' => 'URLa beharrezkoa da eduki mota honetarako',
        'content_required' => 'Edukia beharrezkoa da testu argitalpenetarako',
        'category_required' => 'Kategoria bat aukeratu behar duzu',
        'category_exists' => 'Hautatutako kategoria ez dago',
        'content_type_in' => 'Eduki mota esteka, testua, bideoa, audioa, irudia edo inkesta izan behar da',
    ],
];
