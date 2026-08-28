<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile camera scanner
    |--------------------------------------------------------------------------
    |
    | Document scanning is handled in the browser on iPhone/Android (camera,
    | crop, multi-page PDF). Desktop keeps file import only. No local TWAIN
    | bridge is used.
    |
    */

    'disk' => env('MANAGED_DOCUMENT_DISK', 'local'),

    'max_kilobytes' => 10240,

    'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

    'cheque_statuses' => [
        'prepared' => 'Préparé',
        'handed' => 'Remis',
        'in_circulation' => 'En circulation',
        'cashed' => 'Encaissé',
        'rejected' => 'Rejeté',
        'cancelled' => 'Annulé',
    ],
];
