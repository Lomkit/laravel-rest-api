<?php

namespace Lomkit\Rest\Search\Run;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Search\SearchContext;
use Lomkit\Rest\Search\SearchNode;
use stdClass;

final readonly class SearchMeta
{
    public const string INSTRUCTIONS = 'instructions';

    private function __construct(
        private Resource $resource,
        private SearchNode $search,
    ) {}

    public static function of(Resource $resource, SearchNode $search): self
    {
        return new self($resource, $search);
    }

    /**
     * @param  LengthAwarePaginator<int, Model>  $page
     * @return array<string, mixed>
     */
    public function compute(SearchContext $context, LengthAwarePaginator $page): array
    {
        $meta = $this->resource->searchMeta($context, $page);

        foreach ([$this->resource->context()->config()->gatesKey, self::INSTRUCTIONS] as $key) {
            if (array_key_exists($key, $meta)) {
                throw InvalidResourceException::reservedSearchMetaKey($this->resource::class, $key);
            }
        }

        $instructions = $this->instructions();

        if ($instructions === []) {
            return $meta;
        }

        return [...$meta, self::INSTRUCTIONS => (object) $instructions];
    }

    /** @return array<string, stdClass> */
    private function instructions(): array
    {
        $meta = [];

        foreach ($this->search->instructions as $node) {
            $values = $node->instruction->searchMeta(InstructionFields::of($node->instruction, $node->fields));

            if ($values !== []) {
                $meta[$node->key()] = (object) $values;
            }
        }

        return $meta;
    }
}
