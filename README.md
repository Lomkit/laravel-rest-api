<p align="center"><img src="https://raw.githubusercontent.com/Lomkit/art/master/laravel-rest-api/cover.png" alt="Social Card of Laravel Permission"></p>

# Laravel Rest Api

Laravel Rest Api is an elegant way to expose your app through an API, it takes full advantage of the Laravel ecosystem such as Policies, Controllers, Eloquent, ...

## Requirements

PHP 7.3+ and Laravel 8.0+

# BETA
Please note that this package is under beta and is not recommended to use for production environment for now. End of beta should be by the end of 2023.

## Documentation, Installation, and Usage Instructions

See the [documentation](https://laravel-rest-api.lomkit.com) for detailed installation and usage instructions.

## What It Does

You'll find multiple endpoints exposed when using this package such as mutating, searching, showing, deleting, ...

Here is a quick look at what you can do using API search method:
```
// POST api/posts/search
{
    "scopes": [
        {"name": "withTrashed", "parameters": [true]}
    ],
    "filters": [
        {
            "field": "id", "operator": ">", "value": 1, "type": "or"
        },
        {
            "nested": [
                {"field": "user.posts.id", "operator": "<", "value": 2},
                {"field": "user.id", "operator": ">", "value": 3, "type": "or"}
            ]
        }
    ],
    "sorts": [
        {"field": "user_id", "direction": "desc"},
        {"field": "id", "direction": "asc"}
    ],
    "selects": [
        {"field": "id"}
    ],
    "includes": [
        {
            "relation": "posts",
            "filters": [
                 {"field": "id", "operator": "in", "value": [1, 3]}
            ],
            "limit": 2
        },
        {
            "relation": "user",
            "filters": [
                {
                    "field": "languages.pivot.boolean",
                    "operator": "=",
                    "value": true
                }
            ]
        }
    ],
    "page": 2,
    "limit": 10
}
```

## Changelog

TODO

## Contributing

TODO

## Roadmap

- Morph support
- Custom directives (Filters / sorting)
- Actions / Metrics
- Automatic Gates
- Aggregating
- Automatic documentation with extension possible