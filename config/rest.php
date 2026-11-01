<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authorizations
    |--------------------------------------------------------------------------
    |
    | A search checks "viewAny" on each resource it reads. A write or a
    | delete checks the policy of each record it changes. Each distinct
    | check runs once per request.
    |
    | In strict mode, a check that the request did not plan before
    | validation throws an UnplannedCheckException. Turn it on in your
    | tests, for example with REST_AUTHORIZATIONS_STRICT=true in
    | phpunit.xml.
    |
    | The cache keeps the decisions between requests for "default"
    | minutes. It is off by default. Turn it on with "enabled", for
    | example with REST_AUTHORIZATIONS_CACHE_ENABLED=true.
    |
    */

    'authorizations' => [
        'strict' => env('REST_AUTHORIZATIONS_STRICT', false),

        'cache' => [
            'enabled' => env('REST_AUTHORIZATIONS_CACHE_ENABLED', false),
            'default' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gates In Responses
    |--------------------------------------------------------------------------
    |
    | A search can list abilities in "gates" to learn, for each row,
    | whether the user may perform them. The answers go under "key" in
    | each row, and the "create" answer goes under "key" in the meta of
    | the response. Pick a key that no field or include uses.
    |
    | When "message.enabled" is true, each answer holds "allowed" and
    | "message". When it is false, each answer is true or false, a
    | deprecated format.
    |
    | "names" renames the answers. Each key is the default name of an
    | answer. A resource can turn gates off with the DisableGates trait.
    |
    */

    'gates' => [
        'key' => 'gates',

        'message' => [
            'enabled' => false,
        ],

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
    | Request Limits
    |--------------------------------------------------------------------------
    |
    | A request over one of these limits gets a 422 on the list that goes
    | over it. A limit of 0 refuses every entry, or the first level of a
    | depth. These are not the page sizes of a resource, which its
    | limits() method returns.
    |
    | "sizes" is the most entries that each list of a request accepts, at
    | every level. "filters" also covers each "nested" group and the
    | filters of includes and aggregates. "fields" covers the fields of
    | an action and of each instruction. "values" covers the values of an
    | "in", "not in", "between" or "not between" filter, and the keys of
    | one mutation entry. "relation_entries" covers each to-many relation
    | of a mutation. "resources" covers destroy, restore and force
    | destroy; an action uses its $maxResources property instead.
    |
    | "depths" is how many levels nest: "nested" filter groups, includes,
    | and relations in a mutation. A text search run by a search engine
    | never nests filters.
    |
    | "totals" count across lists. "filters" is the most filters in one
    | search, at every level and in its includes and aggregates.
    | "entries" is the most entries of all the lists above in one
    | request, except "resources".
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
    | Precognition
    |--------------------------------------------------------------------------
    |
    | Supports Laravel Precognition. A request with the "Precognition"
    | header runs its middleware, the policy checks made before
    | validation and its validation, then stops before the controller.
    | Use it for live form validation. The middleware is added when the
    | routes are registered, so clear the route cache after you change
    | this option.
    |
    */

    'precognition' => [
        'enabled' => false,
    ],

];
