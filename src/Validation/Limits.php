<?php

namespace Lomkit\Rest\Validation;

use InvalidArgumentException;

final readonly class Limits
{
    public function __construct(
        public int $filters,
        public int $sorts,
        public int $selects,
        public int $scopes,
        public int $aggregates,
        public int $includes,
        public int $instructions,
        public int $gates,
        public int $fields,
        public int $values,
        public int $mutate,
        public int $relationEntries,
        public int $resources,
        public int $includeDepth,
        public int $mutationDepth,
        public int $filterNodes,
        public int $nodes,
    ) {}

    public static function fromConfig(mixed $config): self
    {
        $config = is_array($config) ? $config : [];

        return new self(
            filters: self::read($config, 'filters'),
            sorts: self::read($config, 'sorts'),
            selects: self::read($config, 'selects'),
            scopes: self::read($config, 'scopes'),
            aggregates: self::read($config, 'aggregates'),
            includes: self::read($config, 'includes'),
            instructions: self::read($config, 'instructions'),
            gates: self::read($config, 'gates'),
            fields: self::read($config, 'fields'),
            values: self::read($config, 'values'),
            mutate: self::read($config, 'mutate'),
            relationEntries: self::read($config, 'relation_entries'),
            resources: self::read($config, 'resources'),
            includeDepth: self::read($config, 'include_depth'),
            mutationDepth: self::read($config, 'mutation_depth'),
            filterNodes: self::read($config, 'filter_nodes'),
            nodes: self::read($config, 'nodes'),
        );
    }

    /** @param array<array-key, mixed> $config */
    private static function read(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("The [rest.limits.{$key}] option must be an integer of 0 or more.");
    }
}
