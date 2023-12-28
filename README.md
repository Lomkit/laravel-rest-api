<p align="center"><img src="https://raw.githubusercontent.com/Lomkit/art/master/laravel-rest-api/cover.png" alt="Social Card of Laravel Permission"></p>

# Laravel Rest Api

Laravel Rest Api is an elegant way to expose your app through an API, it takes full advantage of the Laravel ecosystem such as Policies, Controllers, Eloquent, ...

## Requirements

PHP 8.1+ and Laravel 10.0+

## Documentation, Installation, and Usage Instructions

See the [documentation](https://laravel-rest-api.lomkit.com) for detailed installation and usage instructions.

## What It Does

You'll find multiple endpoints exposed when using this package such as mutating, searching, showing, deleting, ...

Here is a quick look at what you can do using API search method:
```
// POST api/posts/search
{
    "search": {
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
        "aggregates": [
            {
                "relation": "stars",
                "type": "max",
                "field": "rate",
                "filters": [
                    {"field": "approved", "value": true}
                ]
            },
        ],
        "instructions": [
          {
            "name": "odd-even-id",
            "fields": [
              { "name": "type", "value": "odd" }
            ]
          }
        ],
        "gates": ["create", "view"],
        "page": 2,
        "limit": 10
    }
}
```

## Roadmap

- Metrics support
- Refactor the response class
- Plain text search using Laravel Scout
- Alias for includes / aggregates
