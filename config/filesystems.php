<?php

// ponytail: Vercel's filesystem is read-only outside /tmp, so every disk that
// used to write to public_path()/storage_path() switches to S3-compatible
// storage (e.g. Cloudflare R2) when FILESYSTEM_DRIVER=s3. Local dev is unaffected.
$s3Disk = fn (string $prefix) => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'root' => $prefix,
    'url' => env('AWS_URL').($prefix ? '/'.$prefix : ''),
    'visibility' => 'public',
    'throw' => false,
];

$useS3 = env('FILESYSTEM_DRIVER', 'local') === 's3';

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'borrowers'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'borrowers' => $useS3 ? $s3Disk('BORROWERS') : [
            'driver' => 'local',
            'root' => public_path('BORROWERS'),
            'throw' => false,
            'url' => env('APP_URL').'/BORROWERS',
            'visibility' => 'public',
        ],

        'loan_types' => $useS3 ? $s3Disk('LOAN_TYPES') : [
            'driver' => 'local',
            'root' => public_path('LOAN_TYPES'),
            'throw' => false,
            'url' => env('APP_URL').'/LOAN_TYPES',
            'visibility' => 'public',
        ],

       'loans' => $useS3 ? $s3Disk('LOANS') : [
            'driver' => 'local',
            'root' => public_path('LOANS'),
            'throw' => false,
            'url' => env('APP_URL').'/LOANS',
            'visibility' => 'public',
        ],

        'repayments' => $useS3 ? $s3Disk('REPAYMENTS') : [
            'driver' => 'local',
            'root' => public_path('REPAYMENTS'),
            'throw' => false,
            'url' => env('APP_URL').'/REPAYMENTS',
            'visibility' => 'public',
        ],

        'expenses' => $useS3 ? $s3Disk('EXPENSES') : [
            'driver' => 'local',
            'root' => public_path('EXPENSES'),
            'throw' => false,
            'url' => env('APP_URL').'/EXPENSES',
            'visibility' => 'public',
        ],

        'loan_agreement_forms' => $useS3 ? $s3Disk('LOAN_AGREEMENT_FORMS') : [
            'driver' => 'local',
            'root' => public_path('LOAN_AGREEMENT_FORMS'),
            'throw' => false,
            'url' => env('APP_URL').'/LOAN_AGREEMENT_FORMS',
            'visibility' => 'public',
        ],

        'public' => $useS3 ? $s3Disk('public') : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
