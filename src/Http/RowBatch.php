<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class RowBatch
{
    /** @param Collection<int, Model> $rows */
    private function __construct(
        private Resource $resource,
        private Collection $rows,
    ) {}

    /** @param Collection<int, Model> $rows */
    public static function authorize(Resource $resource, Collection $rows, string $ability): self
    {
        $resource->context()
            ->authorizer()
            ->authorizeEach($resource, $ability, $rows, 'resources');

        return new self($resource, $rows);
    }

    /**
     * @param  Closure(Model): void  $write
     * @return Collection<int, Model>
     */
    public function write(Closure $write): Collection
    {
        $this->resource::newModel()
            ->getConnection()
            ->transaction(function () use ($write): void {
                foreach ($this->rows as $row) {
                    $write($row);
                }
            });

        return $this->rows;
    }
}
