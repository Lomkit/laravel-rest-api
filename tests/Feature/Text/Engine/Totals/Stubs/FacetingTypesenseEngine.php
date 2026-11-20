<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MultiSearchTypesenseEngine;
use RuntimeException;

class FacetingTypesenseEngine extends MultiSearchTypesenseEngine
{
    public ?string $refusal = null;

    public TypesenseSchemas $schemas;

    /**
     * @param  array<mixed>  $result
     * @param  array<mixed>|null  $answer
     */
    public function __construct(array $result = ['hits' => [], 'found' => 0], ?array $answer = null)
    {
        parent::__construct($result, $answer);

        $this->schemas = new TypesenseSchemas;
    }

    /** @param array<mixed> $parameters */
    #[\Override]
    public function __call($method, $parameters)
    {
        return $method === 'getCollections' ? $this->schemas : parent::__call($method, $parameters);
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<mixed>  $options
     */
    #[\Override]
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        if ($this->refusal !== null && $builder->callback === null) {
            throw new RuntimeException($this->refusal, 404);
        }

        return parent::performSearch($builder, $options);
    }
}
