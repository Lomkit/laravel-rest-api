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
    | Rest Search
    |--------------------------------------------------------------------------
    |
    | How deep filters may nest into each other. Each "nested" key counts as one
    | level: the default of 1 allows a single group of filters (the historical
    | behavior), while a higher value allows that many groups nested inside one
    | another. A text search run by a search engine never nests filters, whatever
    | this is set to. Keep it modest, since deeply nested filters build large,
    | complex boolean queries.
    |
    */

    'search' => [
        'max_nesting_depth' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Limits
    |--------------------------------------------------------------------------
    |
    | The most entries that each list of a request accepts, how deep includes
    | and relation operations nest, and two totals. "filters" also applies to
    | each "nested" group and to the filters of includes and aggregates.
    | "fields" applies to the fields of an action and of each instruction.
    | "values" applies to the values of an "in", "not in", "between" or "not
    | between" filter and to the key list of one mutation entry.
    | "relation_entries" applies to each to-many relation of a mutation.
    | "resources" applies to destroy, restore and force destroy; an action
    | uses its $maxResources property instead.
    | "filter_nodes" counts the filters of one search, at every depth and in
    | its includes and aggregates. "nodes" counts the entries of every list
    | above in one request, except "resources". A request over a limit gets a
    | 422 on the list that goes over it. These limits are not the page sizes
    | of a resource, which limits() returns.
    |
    */

    'limits' => [
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
        'include_depth' => 3,
        'mutation_depth' => 3,
        'filter_nodes' => 500,
        'nodes' => 1000,
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
