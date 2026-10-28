<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rest Gates
    |--------------------------------------------------------------------------
    |
    | The following configuration option contains gates customisation. You might
    | want to adapt this feature to your needs.
    |
    */

    'gates' => [
        'enabled' => true,
        'key' => 'gates',
        'message' => [
            'enabled' => false,
        ],
        // Here you can customize the keys for each gate
        'names' => [
            'authorized_to_view' => 'authorized_to_view',
            'authorized_to_create' => 'authorized_to_create',
            'authorized_to_update' => 'authorized_to_update',
            'authorized_to_delete' => 'authorized_to_delete',
            'authorized_to_restore' => 'authorized_to_restore',
            'authorized_to_force_delete' => 'authorized_to_force_delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Authorizations
    |--------------------------------------------------------------------------
    |
    | This is the feature that automatically binds to policies to validate incoming requests.
    | Laravel Rest Api will validate each models searched / mutated / deleted to avoid leaks in your API.
    |
    */

    'authorizations' => [
        'enabled' => true,
        'cache' => [
            'enabled' => true,
            'default' => 5, // Cache minutes by default
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Search
    |--------------------------------------------------------------------------
    |
    | How deep filters may nest into each other. Each "nested" key counts as one
    | level: the default of 1 allows a single group of filters (the historical
    | behavior), while a higher value allows that many groups nested inside one
    | another. Nesting stays disabled in Scout mode whatever this is set to. Keep
    | it modest, since deeply nested filters build large, complex boolean queries.
    |
    */

    'search' => [
        'max_nesting_depth' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Precognition Support
    |--------------------------------------------------------------------------
    |
    | This option enables support for Laravel Precognition, which allows
    | frontend applications to perform validation requests without executing
    | controller logic. When enabled, requests containing the "Precognition"
    | header will only trigger middleware and validation, skipping the actual
    | controller method. This is especially useful for live form validation.
    |
    */

    'precognition' => [
        'enabled' => false,
    ],
];
