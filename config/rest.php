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
    | Within a request, each distinct check runs once. The cache keeps the
    | decisions across requests for "default" minutes; it is off by default.
    |
    */

    'authorizations' => [
        'enabled' => true,
        // When true, a class-level check that the request did not plan before
        // validation throws a LogicException instead of being evaluated. Turn it
        // on in your test suite to catch a relation the request does not cover.
        'strict' => false,
        'cache' => [
            'enabled' => false,
            'default' => 5, // Cache minutes by default
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Limits
    |--------------------------------------------------------------------------
    |
    | These limits bound the size of a request. A request over a limit gets a
    | 422 on the list that goes over it. They are not the page sizes of a
    | resource, which limits() returns.
    |
    | "sizes" sets the most entries of each list. A key applies to the list of
    | that name. "filters" also applies to each "nested" group and to the
    | filters of includes and aggregates. "fields" applies to the fields of an
    | action and of each instruction. "values" applies to the values of an
    | "in", "not in", "between" or "not between" filter, and to the key list
    | of one mutation entry. "relation_entries" applies to each to-many
    | relation of a mutation. "resources" applies to destroy, restore and
    | force destroy. An action uses its $maxResources property instead.
    |
    | "depths" sets how deep filters, includes and relation operations nest.
    | Each "nested" key counts as one filter level. A filter depth of 1 allows
    | a single group of filters, the historical behavior. A higher value allows
    | that many groups nested inside one another. A text search run by a
    | search engine never nests filters, whatever the filter depth. Keep the
    | filter depth modest: deeply nested filters build large, complex boolean
    | queries.
    |
    | "totals" sets two totals. "filters" counts the filters of one search, at
    | every depth and in its includes and aggregates. "entries" counts the
    | entries of every list in "sizes" in one request, except "resources".
    |
    */

    'limits' => [
        'sizes' => [
            'filters' => 100,
            'sorts' => 20,
            'selects' => 100,
            'scopes' => 10,
            'aggregates' => 20,
            'includes' => 20,
            'instructions' => 10,
            'gates' => 7,
            'fields' => 100,
            'values' => 100,
            'mutate' => 100,
            'relation_entries' => 100,
            'resources' => 1000,
        ],
        'depths' => [
            'filters' => 1,
            'includes' => 3,
            'mutations' => 3,
        ],
        'totals' => [
            'filters' => 500,
            'entries' => 1000,
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
    | The middleware is attached when the routes are registered: clear the
    | route cache after changing this option.
    |
    */

    'precognition' => [
        'enabled' => false,
    ],
];
