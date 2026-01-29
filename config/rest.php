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
        'key'     => 'gates',
        'message' => [
            'enabled' => false,
        ],
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
            'default' => 5, // Cache minutes by default
        ],
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
            'summary'        => 'This is my project\'s documentation',
            'description'    => 'Find out all about my project\'s API',
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
            //            [
            //                "api_key" => []
            //            ],
            //            [
            //                "auth" => [
            //                    'write:users',
            //                    'read:users'
            //                ]
            //            ]
        ],
        // See https://spec.openapis.org/oas/v3.1.0#security-scheme-object
        'securitySchemes' => [
            //            "api_key" => [
            //                "description" => "Authentication via API key",
            //                "type" => "apiKey",
            //                "name" => "x-api-key",
            //                "in" => "header"
            //            ],
            //            "http_bearer" => [
            //                "description" => "HTTP authentication with bearer token",
            //                "type" => "http",
            //                "scheme" => "bearer",
            //                "bearerFormat" => "JWT"
            //            ],
            //            "oauth_authcode" => [
            //                "description" => "Authentication via OAuth2 with authorization code flow",
            //                "type" => "oauth2",
            //                "flows" => [
            //                    "authorizationCode" => [
            //                        "authorizationUrl" => "https://example.com/api/oauth/dialog",
            //                        "tokenUrl" => "https://example.com/api/oauth/token",
            //                        "refreshUrl" => "https://example.com/api/oauth/refresh",
            //                        "scopes" => [
            //                            "do:something" => "do something"
            //                        ]
            //                    ]
            //                ]
            //            ],
            //            "oauth_clientcredentials" => [
            //                "description" => "Authentication via OAuth2 with client credentials flow",
            //                "type" => "oauth2",
            //                "flows" => [
            //                    "clientCredentials" => [
            //                        "tokenUrl" => "https://example.com/api/oauth/token",
            //                        "refreshUrl" => "https://example.com/api/oauth/refresh",
            //                        "scopes" => [
            //                            "do:something" => "do something"
            //                        ]
            //                    ]
            //                ]
            //            ],
            //            "oauth_implicit" => [
            //                "description" => "Authentication via OAuth2 with implicit flow",
            //                "type" => "oauth2",
            //                "flows" => [
            //                    "implicit" => [
            //                        "authorizationUrl" => "https://example.com/api/oauth/dialog",
            //                        "refreshUrl" => "https://example.com/api/oauth/refresh",
            //                        "scopes" => [
            //                            "write:foo" => "modify foo",
            //                            "read:foo" => "read foo"
            //                        ]
            //                    ]
            //                ]
            //            ],
            //            "oauth_password" => [
            //                "description" => "Authentication via OAuth2 with resource owner password flow",
            //                "type" => "oauth2",
            //                "flows" => [
            //                    "password" => [
            //                        "tokenUrl" => "https://example.com/api/oauth/token",
            //                        "refreshUrl" => "https://example.com/api/oauth/refresh",
            //                        "scopes" => [
            //                            "do:something" => "do something"
            //                        ]
            //                    ]
            //                ]
            //            ],
            //            "open_id" => [
            //                "description" => "Authentication via OpenID Connect",
            //                "type" => "openIdConnect",
            //                "openIdConnectUrl" => "https://example.com/openid/issuer/location"
            //            ]
        ],
    ],
];
