<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local scanner bridge
    |--------------------------------------------------------------------------
    |
    | Browsers cannot drive USB scanners (TWAIN/WIA) directly. When a local
    | scan helper is installed, Libromart can call this endpoint. Otherwise the
    | UI falls back to importing the PDF produced by the scanner software.
    |
    */
    'scanner_bridge_url' => env('MANAGED_DOCUMENT_SCANNER_BRIDGE_URL', 'http://127.0.0.1:18765/scan'),

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
