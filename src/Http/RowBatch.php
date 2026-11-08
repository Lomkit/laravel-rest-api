<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceRestored;

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
     * @param  class-string<ResourceDestroyed|ResourceRestored|ResourceForceDestroyed>  $written
     * @return Collection<int, Model>
     */
    public function write(Closure $before, Closure $write, Closure $after, string $written): Collection
    {
        WriteTransaction::of($this->resource)
            ->over($this->rows)
            ->run(function () use ($before, $write, $after): void {
                $before($this->rows);

                foreach ($this->rows as $row) {
                    $write($row);
                }

                $after($this->rows);
            });

        $this->resource->context()->events()->dispatch(new $written($this->resource, $this->rows));

        return $this->rows;
    }
}
