<?php

return [
    'citoyen' => [
        'host' => env('IMAP_CITOYEN_HOST', env('LDAP_CITOYEN_URL')),
        'user' => env('IMAP_CITOYEN_USER'),
        'password' => env('IMAP_CITOYEN_PWD'),
    ],
];
