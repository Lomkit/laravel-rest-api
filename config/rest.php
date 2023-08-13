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
        'key' => 'gates',
        // Here you can customize the keys for each gate
        'names' => [
            'authorized_to_view' => 'authorized_to_view',
            'authorized_to_create' => 'authorized_to_create',
            'authorized_to_update' => 'authorized_to_update',
            'authorized_to_delete' => 'authorized_to_delete',
            'authorized_to_restore' => 'authorized_to_restore',
            'authorized_to_force_delete' => 'authorized_to_force_delete',
        ]
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
        'enabled' => true
    ],
];
