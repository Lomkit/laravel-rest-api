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
    | A resource lists in its authorizationCache() method the decisions
    | to keep between requests, and for how long. They go to the cache
    | "store", which must match one of your cache stores; null uses the
    | default store. A metric declared with cacheFor() keeps its result
    | in the same store. Set "enabled" to false to keep no decision and
    | no metric result between requests.
    |
    */

    'authorizations' => [
        'strict' => env('REST_AUTHORIZATIONS_STRICT', false),

        'cache' => [
            'enabled' => env('REST_AUTHORIZATIONS_CACHE_ENABLED', true),
            'store' => env('REST_AUTHORIZATIONS_CACHE_STORE') ?: null,
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
    | the response. Each answer holds "allowed" and "message". Pick a key
    | that no field or include uses.
    |
    | "names" renames the answers. Each key is the default name of an
    | answer: "authorized_to_" and the ability in snake case. Add a key to
    | rename a gate that a resource declares in gates(). A resource can
    | turn its gates off: override isGatingEnabled() and return false.
    |
    */

    'gates' => [
        'key' => 'gates',

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
    | Morph Type Key
    |--------------------------------------------------------------------------
    |
    | Each model loaded through a MorphTo relation holds its type under
    | this key, before its other attributes. A resource that a MorphTo
    | relation can load cannot declare a field with this name.
    |
    */

    'morph' => [
        'type_key' => '@type',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | A search loads only the columns it needs: the selected fields, the
    | keys of its includes and the columns that each resource always
    | selects. When a policy, an accessor or map() reads another column,
    | add it to the resource's alwaysSelect() method or to the field's
    | requires(). Set "select_columns" to false to load every column.
    |
    | A to-many include returns at most its limit of rows for each parent.
    | Each row of a level with such an include lists under "more_key" the
    | includes that have more rows than they returned. A resource cannot
    | declare a field with this name.
    |
    */

    'search' => [
        'select_columns' => true,

        'more_key' => '@more',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transactions
    |--------------------------------------------------------------------------
    |
    | Each write runs in a database transaction: a mutation, a destroy, a
    | restore, a force destroy, and each chunk or whole run of an action.
    | "attempts" is how many times a transaction runs when the database
    | reports a deadlock or a lock timeout; 1 runs it once. Each attempt
    | runs the whole write again, with its events and the handle() method
    | of the action, right after the failed one. A transaction inside a
    | transaction of your application is not run again.
    |
    */

    'transactions' => [
        'attempts' => env('REST_TRANSACTION_ATTEMPTS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Limits
    |--------------------------------------------------------------------------
    |
    | A request over one of these limits gets a 422 on the list or the
    | value that goes over it. A limit of 0 refuses every entry, or the
    | first level of a depth. These are not the page sizes of a resource,
    | which its limits() method returns.
    |
    | "sizes" is the most entries that each list of a request accepts, at
    | every level. "filters" also covers each "nested" group and the
    | filters of includes and aggregates. "fields" covers the fields of
    | an action and of each instruction. "values" covers the values of an
    | "in", "not in", "between" or "not between" filter, and the keys of
    | one mutation entry. "relation_entries" covers each to-many relation
    | of a mutation. "resources" covers destroy, restore and force
    | destroy; an action uses its $maxResources property instead. "jobs"
    | is the most jobs that one run of a queued action creates, at least
    | 1. When its rows need more jobs, each job takes more rows than its
    | $chunkCount: the number of rows divided by "jobs", rounded up.
    | "rows" is the most rows that one search page or the "response" of
    | a mutation renders, at least 1: its rows and every included row.
    | It is counted from the limits that the request asks for, before
    | any query: the page limit, and for each to-many include its limit
    | for each parent row, nested includes multiplying. A to-one include
    | counts one row for each parent.
    | "groups" is the most parts of a partition metric and the most
    | splits of a trend in a response, at least 1. A metric sets its own
    | with limit(). The other groups fold into "other".
    |
    | "depths" is how many levels nest: "nested" filter groups, includes,
    | and relations in a mutation. A text search run by a search engine
    | never nests filters. "includes" also bounds the relations of a
    | dotted filter field, such as "author.posts.title" (two relations).
    |
    | "totals" count across lists. "filters" is the most filters in one
    | search, at every level and in its includes and aggregates.
    | "entries" is the most entries of all the lists above and of the
    | "gates" lists in one request, except "resources". In a mutation,
    | a relation list and the key lists of its entries count once for
    | each row of the parent entry, nested levels multiplying. It also
    | bounds the attached rows that one "sync" detaches.
    | "relations" is the most relation filters in one search, at least
    | 1: each filter on a dotted field path and each "relation" group,
    | at every level and in its includes and aggregates. Each one runs a
    | subquery.
    | "patterns" is the most "like", "not like", "ilike" and "not ilike"
    | filters in one search, at least 1, at every level and in its
    | includes and aggregates.
    |
    | "lengths" is the most characters of one value. "patterns" covers the
    | value of a "like", "not like", "ilike" or "not ilike" filter, at
    | every level. "values" covers every string value of a filter, each
    | item of a list too, at every level. A pattern takes the smaller of
    | the two. "values" is at least 1.
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
            'fields' => 100,
            'values' => 100,
            'mutate' => 100,
            'relation_entries' => 100,
            'resources' => 1000,
            'jobs' => 100,
            'rows' => 10000,
            'groups' => 100,
        ],

        'depths' => [
            'filters' => 1,
            'includes' => 3,
            'mutations' => 3,
        ],

        'totals' => [
            'filters' => 500,
            'entries' => 1000,
            'relations' => 20,
            'patterns' => 10,
        ],

        'lengths' => [
            'patterns' => 255,
            'values' => 1000,
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

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | To register the routes of the actions and metrics, the package builds
    | each resource and reads its actions() and metrics(). When "remember"
    | is true, it keeps the route keys and middleware that it read for each
    | resource class until the PHP process ends, and a later registration
    | of that resource class reuses them without building the resource.
    | It helps a process that registers the routes more than once, such as
    | a test suite. The routes reflect whatever actions() and metrics() read
    | at the first registration: config, env, static properties, container
    | bindings. In a test that changes them, forget the routes before
    | parent::setUp() and in tearDown(), or use the
    | Lomkit\Rest\Testing\ForgetsRoutes trait, which does both. In
    | production, cache the routes with "php artisan route:cache" instead.
    |
    */

    'routes' => [
        'remember' => env('REST_ROUTES_REMEMBER', false),
    ],

];
