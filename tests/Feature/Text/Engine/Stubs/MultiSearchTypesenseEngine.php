<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

class MultiSearchTypesenseEngine extends FakeTypesenseEngine
{
    /** @var list<array{string, array<mixed>}> */
    public array $requests = [];

    /** @param array<mixed> $result */
    public function __construct(public array $result = ['hits' => [['document' => ['id' => 'V-a', 'code' => 'V-a']]], 'found' => 1])
    {
        parent::__construct();
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  array<mixed>  $options
     */
    #[\Override]
    protected function performSearch(Builder $builder, array $options = []): mixed
    {
        if ($builder->callback !== null) {
            return call_user_func($builder->callback, null, $builder->query, $options);
        }

        $this->requests[] = ['search', $options];

        return $this->result;
    }

    /** @param array<mixed> $parameters */
    #[\Override]
    public function __call($method, $parameters)
    {
        return $method === 'getMultiSearch' ? $this : parent::__call($method, $parameters);
    }

    /**
     * @param  array<mixed>  $searches
     * @return array<mixed>
     */
    public function perform(array $searches): array
    {
        $this->requests[] = ['multi_search', $searches];

        return ['results' => [$this->result]];
    }
}
