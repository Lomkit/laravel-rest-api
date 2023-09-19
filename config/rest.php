<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rest Automatic Gates
    |--------------------------------------------------------------------------
    |
    | The following configuration option contains gates customisation. You might
    | want to adapt this feature to your needs.
    |
    */

    'automatic_gates' => [
        'enabled' => true,
        'key'     => 'gates',
        // Here you can customize the keys for each gate
        'names' => [
            'authorized_to_view'         => 'authorized_to_view',
            'authorized_to_create'       => 'authorized_to_create',
            'authorized_to_update'       => 'authorized_to_update',
            'authorized_to_delete'       => 'authorized_to_delete',
            'authorized_to_restore'      => 'authorized_to_restore',
            'authorized_to_force_delete' => 'authorized_to_force_delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Resources
    |--------------------------------------------------------------------------
    |
    | This is the core of the Laravel Rest Api package, resources allows you
    | to define your own logic and take full advantage of your API. Here
    | you can disable the cache on all basic resource functions
    |
    */

    'resources' => [
        'cache' => [
            'enabled' => true,
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
        'cache'   => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Documentation
    |--------------------------------------------------------------------------
    |
    | This is the feature that generates automatically your API documentation for you.
    | Laravel Rest Api will validate each models searched / mutated / deleted to avoid leaks in your API.
    | This feature is based on OpenApi, for more detail see: https://swagger.io/specification/
    |
    */

    'documentation' => [
        'routing' => [
            'enabled'     => true,
            'domain'      => null,
            'path'        => '/api-documentation',
            'middlewares' => [
                'web',
            ],
        ],
        'info' => [
            'title'          => config('app.name'),
            'summary'        => 'This is my projet\'s documentation',
            'description'    => 'Find out all about my projet\'s API',
            'termsOfService' => null, // (Optional) Url to terms of services
            'contact'        => [
                'name'  => 'My Company',
                'email' => 'email@company.com',
                'url'   => 'https://company.com',
            ],
            'license' => [
                'url'        => null,
                'name'       => 'Apache 2.0',
                'identifier' => 'Apache-2.0',
            ],
            'version' => '1.0.0',
        ],
        // See https://spec.openapis.org/oas/v3.1.0#server-object
        'servers' => [
            [
                'url'         => '/', // Relative to current
                'description' => 'The current server',
            ],
            //  [
            //      'url' => '"https://my-server.com:{port}/{basePath}"',
            //      'description' => 'Production server',
            //      'variables' => [
            //          'port' => [
            //              'enum' => ['80', '443'],
            //              'default' => '443'
            //           ],
            //           'basePath' => [
            //              'default' => 'v2',
            //              'enum' => ['v1', 'v2'],
            //           ]
            //       ]
            //  ]
        ],
        // See https://spec.openapis.org/oas/v3.1.0#security-scheme-object
        'security' => [
            //  [
            //      'type' => 'http',
            //      'description' => 'description',
            //      'scheme' => 'Bearer',
            //      'bearerFormat' => 'JWT'
            //  ],
            //  [
            //       'type' => 'oauth2',
            //       'flows' => [
            //          'authorizationCode' => [
            //              'scopes' => ['write:pets'],
            //              'tokenUrl' => 'https://example.com/api/oauth/token',
            //              'authorizationUrl' => 'https://example.com/api/oauth/dialog',
            //              'refreshUrl' => 'https://example.com/api/oauth/refresh',
            //          ]
            //       ]
            //  ]
        ],
    ],
];
