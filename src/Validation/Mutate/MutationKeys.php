<?php

namespace Lomkit\Rest\Validation\Mutate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\KeyLookup;
use Lomkit\Rest\Validation\KeysGrammar;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;

final class MutationKeys
{
    /** @var array<string, ID> */
    private array $ids = [];

    /** @param Closure(Resource): Builder<Model> $query */
    public function __construct(
        private readonly ParsedRules $rules,
        private readonly PayloadReader $reader,
        private readonly int $maxValues,
        private readonly Closure $query,
        private readonly RowSet $rows,
    ) {}

    public function scalar(Cursor $key, Resource $resource): int|string|null
    {
        return is_array($key->value) ? null : $this->id($resource)->parseKey($key->value);
    }

    /** @return int|string|list<int|string>|null */
    public function read(Cursor $key, Resource $resource, ?MutationOperation $operation, bool $list, int $runs): int|string|array|null
    {
        if ($operation === MutationOperation::Create) {
            return null;
        }

        if ($list && is_array($key->value)) {
            return $this->keyList($key, $resource, $runs);
        }

        $this->rules->add($key->path, $this->id($resource)->baseRules());
        $parsed = $this->parsed($this->scalar($key, $resource));

        if ($parsed !== null) {
            $this->rules->add($key->path, [$this->lookup($resource)->expect($parsed, $key->path)]);
        }

        return $parsed;
    }

    /** @return list<int|string>|null */
    private function keyList(Cursor $key, Resource $resource, int $runs): ?array
    {
        $items = $this->reader->entries($key, $this->maxValues, runs: $runs);

        if ($items === null) {
            return null;
        }

        $id = $this->id($resource);
        $keys = [];

        foreach ($items as $item) {
            $this->rules->add($item->path, $id->baseRules());
            $parsed = $this->parsed($id->parseKey($item->value));

            if ($parsed !== null) {
                $keys[$item->path] = $parsed;
            }
        }

        if ($keys !== []) {
            $this->rules->add($key->path, [$this->lookup($resource)->expectAll($keys)]);
        }

        return array_values($keys);
    }

    private function parsed(int|string|null $key): int|string|null
    {
        if ($key === null) {
            $this->rules->partial();
        }

        return $key;
    }

    private function id(Resource $resource): ID
    {
        return $this->ids[$resource::class] ??= KeysGrammar::idOf($resource);
    }

    private function lookup(Resource $resource): KeyLookup
    {
        return $this->rows->lookup($resource, fn (): Builder => ($this->query)($resource), $this->id($resource));
    }
}
