
# Laravel Rest Api

This project is under construction. It has been made to cover nowadays problem with constructing API.
This project aims to be as powerfull as GraphQL with the full integration of Laravel (Policies / Eloquent / etc)

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

Estimate time for Beta delivery: July 2023

## Roadmap

- Unit testing
- Documentation
- Morph support for relationing
- Custom directives (Filters / sorting)
- Actions / Metrics
- Automatic Gates
- Aggregating (V2)
- Stubs
- Create entries (Including distant ones)
- Update entries (Including distant ones)
- Automatic documentation with extending possible