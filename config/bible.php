<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de la Biblia
    |--------------------------------------------------------------------------
    |
    | Texto en dominio público (Reina Valera 1909) servido por helloao.org.
    | Los fragmentos de los Evangelios se importan con `php artisan bible:import`.
    |
    */

    'base_url' => env('BIBLE_API_URL', 'https://bible.helloao.org/api'),

    'translation' => env('BIBLE_TRANSLATION', 'spa_r09'),

    // Ruta a un bundle de certificados (cacert.pem) si PHP no tiene uno configurado.
    'ca_bundle' => env('BIBLE_CA_BUNDLE'),

    'books' => [
        'MAT' => 'Mateo',
        'MRK' => 'Marcos',
        'LUK' => 'Lucas',
        'JHN' => 'Juan',
    ],

    'chapters' => [
        'MAT' => 28,
        'MRK' => 16,
        'LUK' => 24,
        'JHN' => 21,
    ],

];
