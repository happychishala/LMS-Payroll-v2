<?php

return [
    'it_roles' => [
        'IT',
        'IT Support',
        'Administrator',
        'Super Admin',
        'super_admin',
    ],

    'it_emails' => array_filter(array_map(
        'trim',
        explode(',', env('SUPPORT_TICKET_IT_EMAILS', ''))
    )),
];
