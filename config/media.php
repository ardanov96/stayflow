<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage
    |--------------------------------------------------------------------------
    |
    | Two disks, on purpose:
    |
    |  - the **private** disk holds the files nobody may see (an invoice,
    |    a document of a guest). It is not served by the web server, and
    |    a file on it is only reachable through a signed URL that expires.
    |  - the **public** disk holds the files of the hotel (the photos of
    |    a room type). It is served by the CDN, and the URL is the CDN
    |    one.
    |
    | The disks are the ones of `config/filesystems.php`; in production
    | they are both S3 (the private one with the access denied, the
    | public one behind the CDN).
    |
    */

    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),

    'public_url' => env('MEDIA_PUBLIC_URL', env('APP_URL', 'http://localhost').'/storage'),

    'signed_url_minutes' => (int) env('MEDIA_SIGNED_URL_MINUTES', 15),

    /*
     | Whether the private disk can create a temporary URL of its own.
     |
     | True for S3 (the URL of the provider is signed and expires). False
     | for the local disk: the URL of a local file is **not** signed, so
     | the private file is served by the signed route of the application
     | instead. The default is false, because a private file that is
     | reachable without a signature is a private file that is not
     | private.
     */

    'private_disk_temporary_urls' => (bool) env('MEDIA_PRIVATE_TEMPORARY_URLS', false),

];
