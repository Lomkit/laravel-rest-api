<?php

namespace Lomkit\Rest\Validation;

final readonly class ListSizes
{
    public const array KEYS = [
        'filters',
        'sorts',
        'selects',
        'scopes',
        'aggregates',
        'includes',
        'instructions',
        'fields',
        'values',
        'mutate',
        'relation_entries',
        'resources',
        'jobs',
        'rows',
    ];

    public function __construct(
        public int $filters,
        public int $sorts,
        public int $selects,
        public int $scopes,
        public int $aggregates,
        public int $includes,
        public int $instructions,
        public int $fields,
        public int $values,
        public int $mutate,
        public int $relationEntries,
        public int $resources,
        public int $jobs,
        public int $rows,
    ) {}

    public static function from(LimitGroup $group): self
    {
        return new self(
            filters: $group->read('filters'),
            sorts: $group->read('sorts'),
            selects: $group->read('selects'),
            scopes: $group->read('scopes'),
            aggregates: $group->read('aggregates'),
            includes: $group->read('includes'),
            instructions: $group->read('instructions'),
            fields: $group->read('fields'),
            values: $group->read('values'),
            mutate: $group->read('mutate'),
            relationEntries: $group->read('relation_entries'),
            resources: $group->read('resources'),
            jobs: $group->read('jobs', 1),
            rows: $group->read('rows', 1),
        );
    }
}
