<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | The `private` disk stores audit-sensitive uploads (engine request
    | documents, SWIFT, customs/FX PDFs) outside the public web root. It must
    | never be served via a public URL — downloads go through authorized
    | controller endpoints only.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
        ],

        // Isolated from 'private': holds pre-submission temporary uploads only.
        // Never shares a root with engine-requests/ so a stray sweep of one
        // can't touch the other. Same non-throwing behavior as 'private' so
        // both disks are handled identically by promotion/compensation code.
        'private-tmp' => [
            'driver' => 'local',
            'root' => storage_path('app/private-tmp'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
