<?php

return [
    'enabled' => env('LOAN_APPROVAL_ALERTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Loan Approval Step Owners
    |--------------------------------------------------------------------------
    |
    | Each key is a loan_status value. When a loan enters that status, the
    | configured role holders and email addresses are alerted by email.
    |
    */
    'steps' => [
        'requested' => [
            'label' => 'Loan Request Review',
            'roles' => [
                'Loan Officer',
                'Loans Officer',
                'Credit Officer',
            ],
            'emails' => [],
        ],

        'processing' => [
            'label' => 'Loan Approval Review',
            'roles' => [
                'Loan Manager',
                'Credit Manager',
                'Manager',
            ],
            'emails' => [],
        ],

        'approved' => [
            'label' => 'Approved Loan Disbursement Notice',
            'roles' => [
                'Accountant',
                'Finance',
                'Finance Officer',
            ],
            'emails' => [],
        ],
    ],
];
