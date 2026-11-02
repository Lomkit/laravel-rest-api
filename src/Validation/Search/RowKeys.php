<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class RowKeys
{
    public const string PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $relations
     * @param  list<string>  $reserved
     * @param  list<string>  $aliases
     */
    private function __construct(
        private array $fields,
        private array $relations,
        private array $reserved,
        private array $aliases,
    ) {}

    /** @param list<Cursor> $includes */
    public static function of(ValidationContext $context, array $includes): self
    {
        $resource = $context->resource;
        $request = $resource->context()->request;

        return new self(
            $resource->getFields($request)->names(),
            array_column($resource->getRelations($request), 'relation'),
            [$resource->context()->config()->gatesKey],
            self::aliases($includes),
        );
    }

    public function isReserved(string $key): bool
    {
        return in_array($key, $this->reserved, true);
    }

    public function isField(string $key): bool
    {
        return in_array($key, $this->fields, true);
    }

    public function isRelation(string $key): bool
    {
        return in_array($key, $this->relations, true);
    }

    public function includeUses(string $key): int
    {
        return count(array_keys($this->aliases, $key, true));
    }

    /**
     * @param  list<Cursor>  $includes
     * @return list<string>
     */
    private static function aliases(array $includes): array
    {
        $aliases = [];

        foreach ($includes as $include) {
            $alias = $include->at('alias')->value;

            if (is_string($alias)) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }
}
