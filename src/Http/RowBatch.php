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
     * @param  Closure(Collection<int, Model>): void  $before
     * @param  Closure(Model): void  $write
     * @param  Closure(Collection<int, Model>): void  $after
     * @return Collection<int, Model>
     */
    public function write(Closure $before, Closure $write, Closure $after): Collection
    {
        $this->resource::newModel()
            ->getConnection()
            ->transaction(function () use ($before, $write, $after): void {
                $before($this->rows);

                foreach ($this->rows as $row) {
                    $write($row);
                }

                $after($this->rows);
            });

        return $this->rows;
    }
}
